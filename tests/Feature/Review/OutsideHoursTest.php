<?php

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\ValidatePage;
use App\Domain\Review\Clocks;
use App\Domain\Settings\SettingsRepository;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Support\Box;
use Illuminate\Auth\Access\AuthorizationException;

require_once __DIR__.'/Helpers.php';

test('with the setting on, people act outside hours while clocks stay frozen', function () {
    openHours();
    $page = foodPage();
    $valeria = userWithRole(Role::Marketing);
    closedHours();

    expect(fn () => app(CreateAdjustment::class)->handle($valeria, $page, AdjustmentType::Precio, 'x', Box::defectDefault()))
        ->toThrow(AuthorizationException::class, 'Fuera de horario');

    app(SettingsRepository::class)->putActionsOutsideHours(true);
    $adj = app(CreateAdjustment::class)->handle($valeria, $page, AdjustmentType::Precio, 'x', Box::defectDefault());
    expect($adj->exists)->toBeTrue()
        ->and(app(Clocks::class)->pageClock($page->fresh())->frozen)->toBeTrue();

    app(SettingsRepository::class)->putActionsOutsideHours(false);
    expect(fn () => app(ValidatePage::class)->handle($valeria, foodPage()))->toThrow(AuthorizationException::class, 'Fuera de horario');
});

test('Ogilvy toggles the setting from the schedule screen', function () {
    openHours();
    $admin = userWithRole(Role::Admin);
    $week = collect(range(1, 7))->map(fn ($d) => ['day' => $d, 'open' => $d <= 5, 'from' => '08:30', 'to' => '18:00'])->all();
    $this->actingAs($admin)->put(route('schedule.update'), ['week' => $week, 'actions_outside_hours' => true])->assertSessionHas('success');
    expect(app(SettingsRepository::class)->actionsOutsideHours())->toBeTrue();
    $this->actingAs($admin)->get(route('schedule.edit'))->assertInertia(fn ($p) => $p->where('actions_outside_hours', true)->has('week', 7));
});
