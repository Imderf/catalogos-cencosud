<?php

use App\Enums\Role;
use App\Models\User;

test('a user flagged for password change is sent to the change screen from anywhere', function () {
    $user = User::factory()->role(Role::Marketing)->mustChangePassword()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.force.edit'));
    $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('password.force.edit'));
    $this->actingAs($user)->get(route('password.force.edit'))->assertOk();
});

test('the user can still log out while the change is pending', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

test('changing the password clears the flag and lets the user in', function () {
    $user = User::factory()->role(Role::Marketing)->mustChangePassword()->create();

    $this->actingAs($user)->put(route('password.force.update'), [
        'current_password' => 'password',
        'password' => 'Mi-Clave-Nueva-1',
        'password_confirmation' => 'Mi-Clave-Nueva-1',
    ])->assertRedirect(route('catalogs.index'));

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and(password_verify('Mi-Clave-Nueva-1', $user->password))->toBeTrue();

    $this->actingAs($user)->get(route('catalogs.index'))->assertOk();
});

test('the new password must differ from the handed-over one', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)->put(route('password.force.update'), [
        'current_password' => 'password',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password');
});

test('users without the flag are redirected away from the change screen', function () {
    $user = User::factory()->role(Role::Marketing)->create();

    $this->actingAs($user)->get(route('password.force.edit'))->assertRedirect(route('catalogs.index'));
});
