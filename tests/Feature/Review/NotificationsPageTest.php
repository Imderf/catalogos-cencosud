<?php

use App\Actions\Review\CreateAdjustment;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Notifications\Review\AlertPayload;
use App\Notifications\Review\ReviewAlert;
use App\Support\Box;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

test('the notifications page lists what waits for the role and marks alerts as read', function () {
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    $agency = userWithRole(Role::Agencia);

    // Real (unfaked) pipeline: the adjustment alerts Ogilvy in the database and by mail.
    app(CreateAdjustment::class)->handle($marketing, $page, AdjustmentType::Precio, 'x', Box::fromArray(['x' => .1, 'y' => .2, 'w' => .3, 'h' => .1]));

    expect($agency->unreadNotifications()->count())->toBe(1)
        ->and($agency->notifications()->first()->type)->toBe(ReviewAlert::class);

    $this->actingAs($agency)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('notifications/index')
            ->has('pending', 1)
            ->where('pending.0.title', 'Ejecutar ajuste de precio')
            ->has('recent', 1)
            ->where('recent.0.kind', 'ajuste')
            ->where('recent.0.read', false));

    expect($agency->fresh()->unreadNotifications()->count())->toBe(0);
});

test('the sidebar receives the unread count', function () {
    $agency = userWithRole(Role::Agencia);
    $agency->notify(new ReviewAlert(new AlertPayload('ajuste', 'x', 'y', 'slug', 'Cat', 1)));

    $this->actingAs($agency)->get(route('catalogs.index'))
        ->assertInertia(fn ($p) => $p->where('unread', 1));
});
