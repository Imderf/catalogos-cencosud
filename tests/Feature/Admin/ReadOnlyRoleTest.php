<?php

use App\Enums\Role;
use App\Models\User;

require_once __DIR__.'/../Review/Helpers.php';

beforeEach(fn () => openHours());

test('an Ogilvy read-only user sees catalogs, pages, reports and the calendar but can act on nothing', function () {
    $reader = userWithRole(Role::Lectura);
    $page = foodPage();
    $slug = $page->catalog->slug;
    expect($reader->isReadOnly())->toBeTrue()->and($reader->isOgilvySide())->toBeTrue()->and($reader->actsAsAgency())->toBeFalse()->and($reader->isClient())->toBeFalse();

    $this->actingAs($reader)->get(route('catalogs.index'))->assertOk()->assertInertia(fn ($p) => $p->where('auth.user.read_only', true)->where('auth.user.can.manage_catalogs', false)->where('auth.user.can.projects', false));
    $this->actingAs($reader)->get(route('catalogs.show', $slug))->assertOk();
    $this->actingAs($reader)->get(route('pages.show', [$slug, $page->number]))->assertOk()->assertInertia(fn ($p) => $p
        ->where('page.can.comment.allowed', false)->where('page.can.upload_version.allowed', false)->where('page.can.submit.allowed', false)
        ->where('page.can.approve.allowed', false)->where('page.can.approve_print.allowed', false)->where('page.can.reopen.allowed', false));
    $this->actingAs($reader)->get(route('reports.monthly'))->assertOk();
    $this->actingAs($reader)->get(route('catalogs.report', $slug))->assertOk();
    $this->actingAs($reader)->get(route('calendar'))->assertOk()->assertInertia(fn ($p) => $p->where('read_only', true));

    // nothing that changes the review goes through
    $this->actingAs($reader)->get(route('catalogs.create'))->assertForbidden();
    $this->actingAs($reader)->get(route('panel'))->assertForbidden();
    $this->actingAs($reader)->post(route('pages.adjustments.store', [$slug, $page->number]), ['types' => ['precio'], 'text' => 'x', 'box' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2]])->assertSessionHas('error');
    expect($page->adjustments()->count())->toBe(0);
    $marketing = userWithRole(Role::Marketing);
    $this->actingAs($marketing)->post(route('pages.adjustments.store', [$slug, $page->number]), ['types' => ['precio'], 'text' => 'x', 'box' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2]]);
    $a = $page->adjustments()->first();
    $this->actingAs($reader)->post(route('adjustments.reply', $a), ['text' => 'hola'])->assertSessionHas('error');
    expect($a->replies()->count())->toBe(0);

    // the admin can create such a user
    $admin = userWithRole(Role::Admin);
    $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'Juliana Vélez', 'email' => 'juliana@ogilvy.example', 'organization' => 'Ogilvy Colombia', 'role' => 'lectura', 'sectors' => [], 'password' => 'Secreto-1234', 'is_active' => true])
        ->assertSessionHasNoErrors();
    expect(User::where('email', 'juliana@ogilvy.example')->first()?->role())->toBe(Role::Lectura);
});
