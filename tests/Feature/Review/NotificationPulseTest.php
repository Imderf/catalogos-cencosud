<?php

use App\Domain\Review\Alerts;
use App\Enums\Role;
use App\Notifications\Review\AlertPayload;

require_once __DIR__.'/Helpers.php';

test('the pulse tells the browser how many unread notifications there are and which is the newest', function () {
    openHours();
    $this->get(route('notifications.pulse'))->assertRedirect(route('login')); // signed out
    $daniela = userWithRole(Role::Agencia);
    $page = foodPage();
    $this->actingAs($daniela)->getJson(route('notifications.pulse'))->assertOk()->assertExactJson(['unread' => 0, 'latest' => null]);

    app(Alerts::class)->send(collect([$daniela]), AlertPayload::about('ajuste', 'Ajuste de precio en la Página 01', 'Valeria', $page->catalog, $page));
    $this->actingAs($daniela)->getJson(route('notifications.pulse'))->assertOk()
        ->assertJsonPath('unread', 1)->assertJsonPath('latest.text', 'Ajuste de precio en la Página 01')
        ->assertJsonPath('latest.url', route('pages.show', [$page->catalog->slug, $page->number]))
        ->assertHeader('Cache-Control', 'no-store, private');

});

test('each person chooses whether notifications make a sound', function () {
    $daniela = userWithRole(Role::Agencia);
    expect($daniela->fresh()->notification_sound)->toBeTrue();
    $this->actingAs($daniela)->patch(route('profile.update'), ['name' => $daniela->name, 'notification_mode' => 'instant', 'notification_sound' => false])->assertSessionHasNoErrors();
    expect($daniela->fresh()->notification_sound)->toBeFalse();
    $this->actingAs($daniela)->get(route('catalogs.index'))->assertInertia(fn ($p) => $p->where('auth.user.notification_sound', false));
});
