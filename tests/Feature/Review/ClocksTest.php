<?php

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\ValidateAdjustment;
use App\Actions\Review\ValidatePage;
use App\Domain\Review\Clocks;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Support\Box;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

$box = fn () => Box::fromArray(['x' => .1, 'y' => .2, 'w' => .3, 'h' => .1]);

test('a page without adjustments runs the approval clock of the role whose turn it is', function () {
    $page = foodPage();
    $clock = app(Clocks::class)->pageClock($page);
    expect($clock->type)->toBe('aprobacion')
        ->and($clock->total)->toBe(240.0)
        ->and($clock->remaining)->toBe(240.0)
        ->and($clock->state)->toBe('corriendo')
        ->and($clock->where)->toBe('Marketing debe validar');

    test()->travelTo(now()->addMinutes(200)); // 40 left < 25 %
    expect(app(Clocks::class)->pageClock($page)->state)->toBe('urgente');

    test()->travelTo(now()->addMinutes(60));  // overdue by 20
    $clock = app(Clocks::class)->pageClock($page);
    expect($clock->state)->toBe('vencido')->and($clock->remaining)->toBe(-20.0)
        ->and(app(Clocks::class)->approvalOverdue($page))->toBeTrue();
});

test('the approval clock is suspended while adjustments are open; the adjustment clock runs instead', function () use ($box) {
    $page = foodPage();
    $adj = app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Precio, 'x', $box());
    $clocks = app(Clocks::class);

    expect($clocks->approvalRemaining($page->refresh()))->toBeNull()
        ->and($clocks->adjustmentSla($adj))->toBe(30)   // precio, executed by Ogilvy
        ->and($clocks->adjustmentWhere($adj))->toBe('Ogilvy ejecuta');

    $clock = $clocks->pageClock($page);
    expect($clock->type)->toBe('ajuste')->and($clock->total)->toBe(30.0)->and($clock->openCount)->toBe(1);
});

test('an adjustment parked at a client link uses the validation SLA and the clock restarts on each hop', function () use ($box) {
    $page = foodPage();
    $adj = app(CreateAdjustment::class)->handle(userWithRole(Role::Comercial, ['liquidos']), $page, AdjustmentType::Diseno, 'x', $box());
    $clocks = app(Clocks::class);
    expect($clocks->adjustmentSla($adj))->toBe(120)->and($clocks->adjustmentWhere($adj))->toBe('Planificación valida');

    test()->travelTo(now()->addMinutes(100));
    expect($clocks->adjustmentRemaining($adj))->toBe(20.0);

    $adj = app(ValidateAdjustment::class)->handle(userWithRole(Role::Planificacion), $adj);
    expect($clocks->adjustmentRemaining($adj))->toBe(120.0);

    $adj = app(ValidateAdjustment::class)->handle(userWithRole(Role::Marketing), $adj);
    expect($clocks->adjustmentSla($adj))->toBe(240)->and($clocks->adjustmentWhere($adj))->toBe('Ogilvy ejecuta');
});

test('clocks freeze outside business hours', function () use ($box) {
    $page = foodPage();
    $adj = app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Imagen, 'x', $box()); // 120 min, 10:00
    test()->travelTo(now()->setTime(20, 0));
    $clocks = app(Clocks::class);
    $clock = $clocks->pageClock($page->refresh());
    expect($clocks->adjustmentRemaining($adj))->toBe(120.0 - 480.0)   // 10:00 → 18:00 counted, evening not
        ->and($clock->frozen)->toBeTrue();
});

test('the page clock sums all open adjustments and reports the overdue ones', function () use ($box) {
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    app(CreateAdjustment::class)->handle($marketing, $page, AdjustmentType::Precio, 'a', $box());       // 30
    test()->travelTo(now()->addMinutes(45));
    app(CreateAdjustment::class)->handle($marketing, $page->refresh(), AdjustmentType::Imagen, 'b', $box()); // 120

    $clock = app(Clocks::class)->pageClock($page->refresh());
    expect($clock->total)->toBe(150.0)->and($clock->consumed)->toBe(45.0)
        ->and($clock->openCount)->toBe(2)->and($clock->overdueCount)->toBe(1)->and($clock->state)->toBe('vencido')
        ->and($clock->where)->toBe('2 en Ogilvy · 0 en el cliente');
});

test('after an approval the next role starts with a fresh clock', function () {
    $page = foodPage();
    test()->travelTo(now()->addMinutes(100));
    app(ValidatePage::class)->handle(userWithRole(Role::Marketing), $page);
    expect(app(Clocks::class)->pageClock($page->refresh())->remaining)->toBe(240.0);
});
