<?php

use App\Actions\Review\ApproveForPrint;
use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\UploadVersion;
use App\Actions\Review\ValidatePage;
use App\Domain\Review\PageStatus;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Models\Adjustment;
use App\Models\Page;
use App\Support\Box;
use Illuminate\Auth\Access\AuthorizationException;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

test('validation ascends Marketing → Planificación → Comercial, then print approval descends and closes', function () {
    $page = foodPage();
    $status = app(PageStatus::class);
    $marketing = userWithRole(Role::Marketing);
    $planning = userWithRole(Role::Planificacion);
    $commercial = userWithRole(Role::Comercial, ['liquidos']);

    expect($status->phase($page))->toBe(PageStatus::VALIDATION)
        ->and($status->approvalStage($page))->toBe(Role::Marketing)
        ->and($status->location($page)->label)->toBe('Validación · Marketing');

    app(ValidatePage::class)->handle($marketing, $page);
    expect($status->approvalStage($page->refresh()))->toBe(Role::Planificacion);
    app(ValidatePage::class)->handle($planning, $page);
    expect($status->approvalStage($page->refresh()))->toBe(Role::Comercial);

    // print approval is not available until the three validated
    expect(fn () => app(ApproveForPrint::class)->handle($commercial, $page))->toThrow(AuthorizationException::class, 'Se habilita cuando');

    app(ValidatePage::class)->handle($commercial, $page);
    $page->refresh();
    expect($status->phase($page))->toBe(PageStatus::PRINTING)
        ->and($status->printStage($page))->toBe(Role::Comercial)
        ->and($status->location($page)->label)->toBe('Imprenta · Comercial')
        ->and($status->isApproved($page))->toBeFalse();

    // descending: Comercial → Planificación → Marketing
    expect(fn () => app(ApproveForPrint::class)->handle($marketing, $page))->toThrow(AuthorizationException::class, 'le toca aprobar a Sector / Comercial');
    app(ApproveForPrint::class)->handle($commercial, $page);
    expect($status->printStage($page->refresh()))->toBe(Role::Planificacion);
    app(ApproveForPrint::class)->handle($planning, $page);
    expect($status->printStage($page->refresh()))->toBe(Role::Marketing);
    app(ApproveForPrint::class)->handle($marketing, $page);

    $page->refresh();
    expect($status->phase($page))->toBe(PageStatus::CLOSED)
        ->and($status->isApproved($page))->toBeTrue()
        ->and($status->location($page)->label)->toBe('Aprobada a imprenta')
        ->and($page->approvals()->count())->toBe(6);
});

test('only the role whose turn it is can approve', function () {
    $page = foodPage();
    $planning = userWithRole(Role::Planificacion);

    expect(fn () => app(ValidatePage::class)->handle($planning, $page))
        ->toThrow(AuthorizationException::class, 'Ahora le toca validar a Marketing.');
});

test('Ogilvy never approves', function () {
    $page = foodPage();
    expect(fn () => app(ValidatePage::class)->handle(userWithRole(Role::Agencia), $page))
        ->toThrow(AuthorizationException::class, 'Solo el cliente valida páginas.');
});

test('a commercial can only approve pages of their sectors', function () {
    $page = foodPage();
    app(ValidatePage::class)->handle(userWithRole(Role::Marketing), $page);
    app(ValidatePage::class)->handle(userWithRole(Role::Planificacion), $page->refresh());
    $dph = userWithRole(Role::Comercial, ['dph']);

    expect(fn () => app(ValidatePage::class)->handle($dph, $page->refresh()))
        ->toThrow(AuthorizationException::class, 'otra categoría');
});

test('a page that shares a sector with a multi-sector commercial is approvable', function () {
    $page = Page::factory()->sectors(['bazar', 'textil', 'electro'])->create();
    app(ValidatePage::class)->handle(userWithRole(Role::Marketing), $page);
    app(ValidatePage::class)->handle(userWithRole(Role::Planificacion), $page->refresh());
    $textil = userWithRole(Role::Comercial, ['textil']);

    app(ValidatePage::class)->handle($textil, $page->refresh());
    expect(app(PageStatus::class)->phase($page->refresh()))->toBe(PageStatus::PRINTING);
});

test('the chain is suspended while an adjustment is open', function () {
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    $planning = userWithRole(Role::Planificacion);
    app(ValidatePage::class)->handle($marketing, $page);
    app(CreateAdjustment::class)->handle($planning, $page->refresh(), AdjustmentType::Precio, 'Precio mal', Box::fromArray(['x' => .1, 'y' => .1, 'w' => .2, 'h' => .1]));

    $page->refresh();
    expect(app(PageStatus::class)->approvalSuspended($page))->toBeTrue()
        ->and(app(PageStatus::class)->location($page)->label)->toBe('En Marketing') // planning's adjustment descends via Marketing
        ->and(fn () => app(ValidatePage::class)->handle($planning, $page))
        ->toThrow(AuthorizationException::class, 'Hay ajustes en curso');
});

test('approvals are per version: a new version restarts the chain from Marketing', function () {
    $page = foodPage();
    app(ValidatePage::class)->handle(userWithRole(Role::Marketing), $page);
    app(ValidatePage::class)->handle(userWithRole(Role::Planificacion), $page->refresh());
    expect(app(PageStatus::class)->approvalStage($page->refresh()))->toBe(Role::Comercial);

    app(UploadVersion::class)->handle(userWithRole(Role::Agencia), $page, 'catalogos/x/p01-v2.jpg');

    $page->refresh();
    expect($page->current_version)->toBe(2)
        ->and(app(PageStatus::class)->approvalStage($page))->toBe(Role::Marketing)
        ->and($page->approvals()->count())->toBe(2); // history of v1 is kept
});

test('nobody approves outside business hours', function () {
    closedHours();
    $page = foodPage();
    expect(fn () => app(ValidatePage::class)->handle(userWithRole(Role::Marketing), $page))
        ->toThrow(AuthorizationException::class, 'Fuera de horario laboral');
});

test('approving late records the delay in working minutes', function () {
    $page = foodPage(); // waiting_since = 10:00, marketing SLA = 240 min
    test()->travelTo(now()->addMinutes(300)); // 15:00, 60 min late
    $approval = app(ValidatePage::class)->handle(userWithRole(Role::Marketing), $page);
    expect($approval->delay_minutes)->toBe(60);
});

test('agency users can make no adjustments of their own', function () {
    $page = foodPage();
    expect(fn () => app(CreateAdjustment::class)->handle(userWithRole(Role::Agencia), $page, AdjustmentType::Otro, 'x', Box::defectDefault()))
        ->toThrow(AuthorizationException::class, 'Solo el cliente pide ajustes');
    expect(Adjustment::count())->toBe(0);
});
