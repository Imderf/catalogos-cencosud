<?php

use App\Actions\Review\CreateAdjustment;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Time\BusinessHours;
use App\Domain\Time\BusinessTime;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Models\ScheduleException;
use App\Support\Box;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

require_once __DIR__.'/Helpers.php';

$bogota = fn (string $s) => CarbonImmutable::parse($s, 'America/Bogota');
$box = fn () => Box::defectDefault();

test('the weekly schedule allows a different window per day and closed days', function () use ($bogota) {
    $admin = userWithRole(Role::Admin);
    $week = [
        ['day' => 1, 'open' => true, 'from' => '08:30', 'to' => '18:00'],
        ['day' => 2, 'open' => true, 'from' => '08:30', 'to' => '18:00'],
        ['day' => 3, 'open' => true, 'from' => '08:30', 'to' => '18:00'],
        ['day' => 4, 'open' => true, 'from' => '08:30', 'to' => '18:00'],
        ['day' => 5, 'open' => true, 'from' => '08:30', 'to' => '15:00'],
        ['day' => 6, 'open' => true, 'from' => '09:00', 'to' => '12:00'],
        ['day' => 7, 'open' => false, 'from' => '08:30', 'to' => '18:00'],
    ];
    $this->actingAs($admin)->put(route('schedule.update'), ['week' => $week, 'actions_outside_hours' => false])->assertSessionHas('success');

    $t = app(BusinessTime::class)->refresh();
    expect($t->hours()->label())->toBe('L–J 08:30–18:00 · V 08:30–15:00 · S 09:00–12:00')
        ->and($t->isOpen($bogota('2026-09-18 16:00')))->toBeFalse()   // friday closes at 15:00
        ->and($t->isOpen($bogota('2026-09-19 10:00')))->toBeTrue()    // saturday morning open
        ->and($t->workingMinutesBetween($bogota('2026-09-18 14:00'), $bogota('2026-09-19 10:00')))->toBe(120.0); // 60 fri + 60 sat
});

test('the closing hour must be after the opening hour', function () {
    $admin = userWithRole(Role::Admin);
    $week = collect(range(1, 7))->map(fn ($d) => ['day' => $d, 'open' => true, 'from' => '10:00', 'to' => '09:00'])->all();
    $this->actingAs($admin)->put(route('schedule.update'), ['week' => $week, 'actions_outside_hours' => false])->assertSessionHas('error');
});

test('an extended day counts as working time for everyone and lets everyone act', function () use ($bogota, $box) {
    $admin = userWithRole(Role::Admin);
    $page = foodPage();
    $valeria = userWithRole(Role::Marketing);
    test()->travelTo($bogota('2026-09-22 20:00')); // tuesday night

    expect(fn () => app(CreateAdjustment::class)->handle($valeria, $page, AdjustmentType::Precio, 'x', $box()))->toThrow(AuthorizationException::class, 'Fuera de horario');

    $this->actingAs($admin)->post(route('schedule.exceptions.store'), ['date' => '2026-09-22', 'kind' => 'extend', 'from' => '08:30', 'to' => '22:00', 'note' => 'Cierre de octavilla'])
        ->assertSessionHas('success');

    $t = app(BusinessTime::class)->refresh();
    expect($t->isOpen())->toBeTrue()
        ->and($t->workingMinutesBetween($bogota('2026-09-22 17:00'), $bogota('2026-09-22 21:00')))->toBe(240.0);
    app(CreateAdjustment::class)->handle($valeria, $page, AdjustmentType::Precio, 'x', $box());
});

test('a closed day freezes everything even on a weekday', function () use ($bogota) {
    $admin = userWithRole(Role::Admin);
    $this->actingAs($admin)->post(route('schedule.exceptions.store'), ['date' => '2026-09-23', 'kind' => 'closed', 'note' => 'Festivo']);
    $t = app(BusinessTime::class)->refresh();
    expect($t->isOpen($bogota('2026-09-23 10:00')))->toBeFalse()
        ->and($t->workingMinutesBetween($bogota('2026-09-22 17:00'), $bogota('2026-09-24 09:00')))->toBe(90.0) // 60 tue + 30 thu
        ->and($t->nextOpening($bogota('2026-09-23 10:00'))->format('Y-m-d H:i'))->toBe('2026-09-24 08:30');
});

test('a personal exception lets only the listed people act, with clocks still frozen', function () use ($bogota, $box) {
    $admin = userWithRole(Role::Admin);
    $page = foodPage();
    $valeria = userWithRole(Role::Marketing);
    $tomas = userWithRole(Role::Planificacion);
    test()->travelTo($bogota('2026-09-22 20:00'));

    $this->actingAs($admin)->post(route('schedule.exceptions.store'), ['date' => '2026-09-22', 'kind' => 'users', 'from' => '18:00', 'to' => '22:00', 'users' => [$valeria->id]])
        ->assertSessionHas('success');
    app(BusinessTime::class)->refresh();

    expect(app(BusinessTime::class)->isOpen())->toBeFalse()
        ->and(app(BusinessTime::class)->actionsAllowedFor($valeria))->toBeTrue()
        ->and(app(BusinessTime::class)->actionsAllowedFor($tomas))->toBeFalse();
    app(CreateAdjustment::class)->handle($valeria, $page, AdjustmentType::Precio, 'x', $box());
    expect(fn () => app(CreateAdjustment::class)->handle($tomas, $page->fresh(), AdjustmentType::Precio, 'y', $box()))->toThrow(AuthorizationException::class, 'Fuera de horario');

    test()->travelTo($bogota('2026-09-22 22:30')); // window over
    expect(app(BusinessTime::class)->actionsAllowedFor($valeria))->toBeFalse();
});

test('people exceptions need at least one person and can be deleted', function () {
    $admin = userWithRole(Role::Admin);
    $this->actingAs($admin)->post(route('schedule.exceptions.store'), ['date' => '2026-09-22', 'kind' => 'users', 'from' => '18:00', 'to' => '22:00', 'users' => []])
        ->assertSessionHasErrors('users');
    $this->actingAs($admin)->post(route('schedule.exceptions.store'), ['date' => '2026-09-22', 'kind' => 'closed']);
    $e = ScheduleException::first();
    $this->actingAs($admin)->delete(route('schedule.exceptions.destroy', $e))->assertSessionHas('success');
    expect(ScheduleException::count())->toBe(0);
});

test('the old uniform setting still loads', function () {
    $h = BusinessHours::fromArray(['days' => [1, 2, 3], 'from' => 9, 'to' => 17]);
    expect($h->window(1))->toBe(['from' => 9.0, 'to' => 17.0])->and($h->window(5))->toBeNull()->and($h->label())->toBe('L–X de 09:00 a 17:00');
    app(SettingsRepository::class)->putBusinessHours($h);
    expect(app(SettingsRepository::class)->businessHours()->days())->toBe([1, 2, 3]);
});

test('only Ogilvy manages the schedule', function () {
    $this->actingAs(userWithRole(Role::Marketing))->get(route('schedule.edit'))->assertForbidden();
});
