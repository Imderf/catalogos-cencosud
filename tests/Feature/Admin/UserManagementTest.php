<?php

use App\Enums\Role;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\AccountCreated;
use Illuminate\Support\Facades\Notification;

function adminUser(): User
{
    return User::factory()->admin()->create(['name' => 'Admin Ogilvy']);
}

function validUserPayload(array $overrides = []): array
{
    return [
        'name' => 'Hernán Castro',
        'email' => 'hernan.castro@cencosud.example',
        'organization' => 'Cencosud · Comercial',
        'role' => Role::Comercial->value,
        'sectors' => [Sector::where('slug', 'liquidos')->value('id')],
        'password' => 'Secreta-123',
        'is_active' => true,
        ...$overrides,
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('non-admin roles cannot manage users', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.users.store'), validUserPayload())->assertForbidden();
})->with([Role::Agencia, Role::Marketing, Role::Planificacion, Role::Comercial]);

test('admin can list users with their role and sectors', function () {
    $admin = adminUser();
    $food = Sector::where('slug', 'liquidos')->first();
    $comercial = User::factory()->role(Role::Comercial)->create(['name' => 'Hernán Castro']);
    $comercial->sectors()->attach($food);

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users', 2)
            ->where('users.1.name', 'Hernán Castro')
            ->where('users.1.role', 'comercial')
            ->where('users.1.sectors.0.slug', 'liquidos'));
});

test('admin can search users by name, email or organization', function () {
    $admin = adminUser();
    User::factory()->role(Role::Marketing)->create(['name' => 'Valeria Ortiz', 'organization' => 'Cencosud · Marketing']);
    User::factory()->role(Role::Planificacion)->create(['name' => 'Tomás Rincón']);

    $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'marketing']))
        ->assertInertia(fn ($page) => $page->has('users', 1)->where('users.0.name', 'Valeria Ortiz'));
});

test('admin creates a user with role, sectors and a forced password change', function () {
    Notification::fake();
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.users.store'), validUserPayload())
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $user = User::where('email', 'hernan.castro@cencosud.example')->firstOrFail();
    expect($user->role())->toBe(Role::Comercial)
        ->and($user->sectors->pluck('slug')->all())->toBe(['liquidos'])
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and(password_verify('Secreta-123', $user->password))->toBeTrue();

    Notification::assertSentTo($user, AccountCreated::class);
});

test('a commercial user needs at least one sector', function () {
    $admin = adminUser();

    $this->actingAs($admin)->from(route('admin.users.create'))
        ->post(route('admin.users.store'), validUserPayload(['sectors' => []]))
        ->assertRedirect(route('admin.users.create'))
        ->assertSessionHasErrors('sectors');
});

test('non-commercial roles never keep sectors', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.users.store'), validUserPayload([
        'email' => 'valeria.ortiz@cencosud.example',
        'role' => Role::Marketing->value,
    ]));

    $user = User::where('email', 'valeria.ortiz@cencosud.example')->firstOrFail();
    expect($user->sectors)->toHaveCount(0)
        ->and($user->sectorSlugs())->toBeNull();
});

test('email must be unique', function () {
    $admin = adminUser();
    User::factory()->create(['email' => 'hernan.castro@cencosud.example']);

    $this->actingAs($admin)->post(route('admin.users.store'), validUserPayload())
        ->assertSessionHasErrors('email');
});

test('admin updates role, sectors and optionally the password', function () {
    $admin = adminUser();
    $user = User::factory()->role(Role::Marketing)->create();
    $ids = Sector::whereIn('slug', ['bazar', 'textil'])->pluck('id')->all();

    $this->actingAs($admin)->put(route('admin.users.update', $user), validUserPayload([
        'email' => $user->email,
        'name' => 'Mónica Lara',
        'sectors' => $ids,
        'password' => '',
    ]))->assertRedirect(route('admin.users.index'));

    $user->refresh();
    expect($user->name)->toBe('Mónica Lara')
        ->and($user->role())->toBe(Role::Comercial)
        ->and($user->sectors->pluck('slug')->sort()->values()->all())->toBe(['bazar', 'textil'])
        ->and($user->must_change_password)->toBeFalse();

    $this->actingAs($admin)->put(route('admin.users.update', $user), validUserPayload([
        'email' => $user->email, 'sectors' => $ids, 'password' => 'Nueva-Clave-9',
    ]));

    $user->refresh();
    expect(password_verify('Nueva-Clave-9', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();
});

test('admin cannot demote or deactivate their own account', function () {
    $admin = adminUser();

    $this->actingAs($admin)->put(route('admin.users.update', $admin), validUserPayload([
        'email' => $admin->email, 'role' => Role::Marketing->value, 'sectors' => [], 'password' => '', 'is_active' => false,
    ]));

    $admin->refresh();
    expect($admin->role())->toBe(Role::Admin)->and($admin->is_active)->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');
    expect($admin->refresh()->is_active)->toBeTrue();
});

test('deactivating a user keeps the record and blocks login', function () {
    $admin = adminUser();
    $user = User::factory()->role(Role::Marketing)->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertSessionHas('success');
    expect($user->refresh()->is_active)->toBeFalse();

    auth()->logout();
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});
