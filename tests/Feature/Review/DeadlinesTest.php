<?php

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\ValidateAdjustment;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Notifications\Review\ReviewAlert;
use App\Support\Box;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    openHours();
    Notification::fake();
});

$box = fn () => Box::fromArray(['x' => .1, 'y' => .2, 'w' => .3, 'h' => .1]);

test('an approval deadline warns at 25 % and again when it expires, once each', function () {
    $page = foodPage(); // marketing has 240 min from 10:00
    $marketing = userWithRole(Role::Marketing);
    $agency = userWithRole(Role::Agencia);

    $this->artisan('review:check-deadlines')->assertSuccessful();
    Notification::assertNothingSent();

    test()->travelTo(now()->addMinutes(200));
    $this->artisan('review:check-deadlines');
    $this->artisan('review:check-deadlines');
    Notification::assertSentToTimes($marketing, ReviewAlert::class, 1);
    Notification::assertSentTo($marketing, ReviewAlert::class, fn ($n) => $n->payload->kind === 'urgente');
    Notification::assertNotSentTo($agency, ReviewAlert::class);

    test()->travelTo(now()->addMinutes(60));
    $this->artisan('review:check-deadlines');
    $this->artisan('review:check-deadlines');
    Notification::assertSentToTimes($marketing, ReviewAlert::class, 2);
    Notification::assertSentTo($agency, ReviewAlert::class, fn ($n) => $n->payload->kind === 'vencido' && str_contains($n->payload->text, 'Venció el plazo de Marketing para que valide'));
});

test('an adjustment deadline follows the stage and resets on each hop', function () use ($box) {
    $page = foodPage();
    $commercial = userWithRole(Role::Comercial, ['liquidos']);
    $planning = userWithRole(Role::Planificacion);
    $marketing = userWithRole(Role::Marketing);
    $agency = userWithRole(Role::Agencia);
    $adj = app(CreateAdjustment::class)->handle($commercial, $page, AdjustmentType::Precio, 'x', $box()); // 120 min in Planificación

    test()->travelTo(now()->addMinutes(100));
    $this->artisan('review:check-deadlines');
    Notification::assertSentTo($planning, ReviewAlert::class, fn ($n) => $n->payload->kind === 'urgente');
    expect($adj->fresh()->alert_level)->toBe('urgente');

    $adj = app(ValidateAdjustment::class)->handle($planning, $adj); // now in Marketing, fresh 120 min
    expect($adj->alert_level)->toBeNull();
    $this->artisan('review:check-deadlines');
    Notification::assertNotSentTo($marketing, ReviewAlert::class, fn ($n) => $n->payload->kind === 'urgente');

    test()->travelTo(now()->addMinutes(130));
    $this->artisan('review:check-deadlines');
    Notification::assertSentTo($marketing, ReviewAlert::class, fn ($n) => $n->payload->kind === 'vencido');
    Notification::assertSentTo($agency, ReviewAlert::class, fn ($n) => $n->payload->kind === 'vencido');
});

test('nothing fires outside business hours', function () {
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    test()->travelTo(now()->addMinutes(300));
    closedHours();
    $this->artisan('review:check-deadlines');
    Notification::assertNotSentTo($marketing, ReviewAlert::class);
});
