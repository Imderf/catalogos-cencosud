<?php

use App\Actions\Review\ApproveForPrint;
use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\ReplyToAdjustment;
use App\Actions\Review\ResolveAdjustment;
use App\Actions\Review\ValidateAdjustment;
use App\Actions\Review\ValidatePage;
use App\Domain\Review\PageStatus;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Support\Box;
use Illuminate\Auth\Access\AuthorizationException;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

$box = fn () => Box::fromArray(['x' => .1, 'y' => .2, 'w' => .3, 'h' => .1]);

test('a commercial adjustment descends through Planificación and Marketing to Ogilvy', function () use ($box) {
    $page = foodPage();
    $commercial = userWithRole(Role::Comercial, ['liquidos']);
    $planning = userWithRole(Role::Planificacion);
    $marketing = userWithRole(Role::Marketing);
    $agency = userWithRole(Role::Agencia);

    $adj = app(CreateAdjustment::class)->handle($commercial, $page, AdjustmentType::Precio, 'El arroz debe quedar en $5.290', $box());
    expect($adj->route)->toBe(['planificacion', 'marketing', 'agencia'])
        ->and($adj->currentStage())->toBe(Role::Planificacion)
        ->and($adj->received_by_agency_at)->toBeNull();

    // Marketing cannot skip ahead
    expect(fn () => app(ValidateAdjustment::class)->handle($marketing, $adj))
        ->toThrow(AuthorizationException::class, 'está en Planificación');

    $adj = app(ValidateAdjustment::class)->handle($planning, $adj, 'Confirmado con el proveedor');
    expect($adj->currentStage())->toBe(Role::Marketing)
        ->and($adj->validations)->toHaveCount(1)
        ->and($adj->validations->first()->note)->toBe('Confirmado con el proveedor');

    $adj = app(ValidateAdjustment::class)->handle($marketing, $adj);
    expect($adj->currentStage())->toBe(Role::Agencia)
        ->and($adj->received_by_agency_at)->not->toBeNull()
        ->and($adj->validations->last()->note)->toBeNull();

    // once in Ogilvy nobody validates; only Ogilvy resolves
    expect(fn () => app(ValidateAdjustment::class)->handle($marketing, $adj))
        ->toThrow(AuthorizationException::class, 'ya está en Ogilvy');

    $adj = app(ResolveAdjustment::class)->handle($agency, $adj);
    expect($adj->status)->toBe(AdjustmentStatus::Resolved)
        ->and($adj->resolved_in_version)->toBe(1)
        ->and(app(PageStatus::class)->hasOpenAdjustments($page->refresh()))->toBeFalse();
});

test('a marketing adjustment goes straight to Ogilvy', function () use ($box) {
    $page = foodPage();
    $adj = app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Imagen, 'Foto vieja', $box());
    expect($adj->route)->toBe(['agencia'])->and($adj->received_by_agency_at)->not->toBeNull();
});

test('Ogilvy cannot resolve an adjustment that has not reached it', function () use ($box) {
    $page = foodPage();
    $adj = app(CreateAdjustment::class)->handle(userWithRole(Role::Comercial, ['liquidos']), $page, AdjustmentType::Precio, 'x', $box());
    expect(fn () => app(ResolveAdjustment::class)->handle(userWithRole(Role::Agencia), $adj))
        ->toThrow(AuthorizationException::class, 'todavía no llegó a Ogilvy');
});

test('the client cannot resolve adjustments', function () use ($box) {
    $page = foodPage();
    $adj = app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Precio, 'x', $box());
    expect(fn () => app(ResolveAdjustment::class)->handle(userWithRole(Role::Marketing), $adj))
        ->toThrow(AuthorizationException::class, 'Solo Ogilvy resuelve');
});

test('whoever already approved this version can no longer comment', function () use ($box) {
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    app(ValidatePage::class)->handle($marketing, $page);

    expect(fn () => app(CreateAdjustment::class)->handle($marketing, $page->refresh(), AdjustmentType::Precio, 'x', $box()))
        ->toThrow(AuthorizationException::class, 'Ya validaste esta versión');
});

test('comments are blocked by sector, by hours and on approved pages', function () use ($box) {
    $page = foodPage();
    $dph = userWithRole(Role::Comercial, ['dph']);
    expect(fn () => app(CreateAdjustment::class)->handle($dph, $page, AdjustmentType::Precio, 'x', $box()))
        ->toThrow(AuthorizationException::class, 'otra categoría');

    closedHours();
    expect(fn () => app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Precio, 'x', $box()))
        ->toThrow(AuthorizationException::class, 'Fuera de horario');

    openHours();
    $m = userWithRole(Role::Marketing);
    $p = userWithRole(Role::Planificacion);
    $c = userWithRole(Role::Comercial, ['liquidos']);
    app(ValidatePage::class)->handle($m, $page);
    app(ValidatePage::class)->handle($p, $page->refresh());
    app(ValidatePage::class)->handle($c, $page->refresh());
    expect(fn () => app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page->refresh(), AdjustmentType::Precio, 'x', $box()))
        ->toThrow(AuthorizationException::class, 'aprobación a imprenta');
    app(ApproveForPrint::class)->handle($c, $page->refresh());
    app(ApproveForPrint::class)->handle($p, $page->refresh());
    app(ApproveForPrint::class)->handle($m, $page->refresh());
    expect(fn () => app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page->refresh(), AdjustmentType::Precio, 'x', $box()))
        ->toThrow(AuthorizationException::class, 'Post Production Defect');
});

test('resolving the last open adjustment restarts the client approval clock', function () use ($box) {
    $page = foodPage();
    $agency = userWithRole(Role::Agencia);
    $adj = app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Precio, 'x', $box());
    test()->travelTo(now()->addMinutes(20));
    app(ResolveAdjustment::class)->handle($agency, $adj);

    expect($page->refresh()->waiting_since->equalTo(now()))->toBeTrue();
});

test('anyone with access can reply in the thread; other sectors cannot', function () use ($box) {
    $page = foodPage();
    $adj = app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Precio, 'x', $box());

    $reply = app(ReplyToAdjustment::class)->handle(userWithRole(Role::Agencia), $adj, 'Lo revisamos hoy');
    expect($reply->text)->toBe('Lo revisamos hoy');
    app(ReplyToAdjustment::class)->handle(userWithRole(Role::Comercial, ['liquidos']), $adj, 'Gracias');
    expect($adj->replies()->count())->toBe(2);

    expect(fn () => app(ReplyToAdjustment::class)->handle(userWithRole(Role::Comercial, ['electro']), $adj, 'Hola'))
        ->toThrow(AuthorizationException::class);
});

test('a box outside the page is rejected', function () {
    expect(fn () => Box::fromArray(['x' => .9, 'y' => .1, 'w' => .3, 'h' => .1]))->toThrow(InvalidArgumentException::class);
});
