<?php

use App\Actions\Review\CreateAdjustment;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Notifications\Review\DailyDigestMail;
use App\Notifications\Review\ReviewAlert;
use App\Notifications\Review\ReviewAlertMail;
use App\Support\Box;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

test('users in daily mode get the inbox alert but no instant email', function () {
    Notification::fake();
    $page = foodPage();
    $daniela = userWithRole(Role::Agencia);
    $daniela->update(['notification_mode' => 'daily']);
    $other = userWithRole(Role::Agencia);

    app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Precio, 'x', Box::defectDefault());

    Notification::assertSentTo($daniela, ReviewAlert::class);
    Notification::assertNotSentTo($daniela, ReviewAlertMail::class);
    Notification::assertSentTo($other, ReviewAlertMail::class);
});

test('the morning digest bundles pending items and yesterday activity, and skips people with nothing', function () {
    $page = foodPage();
    $daniela = userWithRole(Role::Agencia);
    $daniela->update(['notification_mode' => 'daily']);
    $idle = userWithRole(Role::Planificacion);
    $idle->update(['notification_mode' => 'daily']);
    app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Precio, 'Precio mal', Box::defectDefault());

    Notification::fake();
    $this->artisan('review:send-digest')->expectsOutputToContain('Resúmenes enviados: 1');

    Notification::assertSentTo($daniela, DailyDigestMail::class, fn ($n) => count($n->pending) === 1
        && $n->pending[0]['title'] === 'Ejecutar ajuste de precio'
        && count($n->recent) === 1);
    Notification::assertNotSentTo($idle, DailyDigestMail::class);
});

test('users choose the mode from their profile', function () {
    $user = userWithRole(Role::Marketing);
    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'notification_mode' => 'daily'])->assertSessionHasNoErrors();
    expect($user->fresh()->wantsDailyDigest())->toBeTrue();
    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'notification_mode' => 'weekly'])->assertSessionHasErrors('notification_mode');
});
