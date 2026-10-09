<?php

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\ReopenAsDefect;
use App\Actions\Review\UploadVersion;
use App\Domain\Review\PageStatus;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Support\Box;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

$box = fn () => Box::fromArray(['x' => .1, 'y' => .2, 'w' => .3, 'h' => .1]);

test('a new version stores the image, closes the chosen adjustments and restarts the chain', function () use ($box) {
    Storage::fake('local');
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    $a1 = app(CreateAdjustment::class)->handle($marketing, $page, AdjustmentType::Imagen, 'Foto vieja', $box());
    $a2 = app(CreateAdjustment::class)->handle($marketing, $page->refresh(), AdjustmentType::Precio, 'Precio', $box());

    $version = app(UploadVersion::class)->handle(userWithRole(Role::Agencia), $page->refresh(), UploadedFile::fake()->image('p1-v2.jpg', 760, 1400), [$a1->id]);

    $page->refresh();
    expect($version->number)->toBe(2)
        ->and($page->current_version)->toBe(2)
        ->and($a1->refresh()->status)->toBe(AdjustmentStatus::Resolved)
        ->and($a1->resolved_in_version)->toBe(2)
        ->and($a2->refresh()->status)->toBe(AdjustmentStatus::Open)
        ->and(app(PageStatus::class)->approvalStage($page))->toBe(Role::Marketing);
    Storage::disk('local')->assertExists($version->image_path);
});

test('only Ogilvy uploads versions, and never on a closed page', function () {
    $page = foodPage();
    expect(fn () => app(UploadVersion::class)->handle(userWithRole(Role::Marketing), $page, 'x.jpg'))
        ->toThrow(AuthorizationException::class, 'Solo Ogilvy carga versiones');

    closeWholeChain($page);
    expect(fn () => app(UploadVersion::class)->handle(userWithRole(Role::Agencia), $page, 'x.jpg'))
        ->toThrow(AuthorizationException::class, 'cerrada');
});

test('an administrator counts as Ogilvy for uploads', function () {
    $page = foodPage();
    $v = app(UploadVersion::class)->handle(userWithRole(Role::Admin), $page, 'catalogos/x/p01-v2.jpg');
    expect($v->number)->toBe(2);
});

test('a Post Production Defect voids the approvals, reopens the page and goes straight to Ogilvy', function () {
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    closeWholeChain($page, $marketing);
    $status = app(PageStatus::class);
    expect($status->isApproved($page))->toBeTrue();

    $defect = app(ReopenAsDefect::class)->handle($marketing, $page, AdjustmentType::Imagen, 'Salió con el empaque viejo');

    $page->refresh();
    expect($status->isApproved($page))->toBeFalse()
        ->and($status->validationStage($page))->toBe(Role::Marketing)
        ->and($page->approvals()->where('version', 1)->count())->toBe(0)
        ->and($page->defects)->toHaveCount(1)
        ->and($defect->adjustment->is_defect)->toBeTrue()
        ->and($defect->adjustment->route)->toBe(['agencia'])
        ->and($status->location($page)->label)->toBe('En Ogilvy');
});

test('only Marketing reopens as defect, and only once the page is approved for print', function () {
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    expect(fn () => app(ReopenAsDefect::class)->handle($marketing, $page->fresh(), AdjustmentType::Precio, 'x'))
        ->toThrow(AuthorizationException::class, 'aprobada a imprenta');

    closeWholeChain($page, $marketing);
    expect(fn () => app(ReopenAsDefect::class)->handle(userWithRole(Role::Comercial, ['liquidos']), $page->fresh(), AdjustmentType::Precio, 'x'))
        ->toThrow(AuthorizationException::class, 'Solo Marketing');

    $defect = app(ReopenAsDefect::class)->handle($marketing, $page->fresh(), AdjustmentType::Precio, 'Precio mal');
    expect($defect->role)->toBe(Role::Marketing)
        ->and($page->refresh()->approvals()->where('version', 1)->count())->toBe(0)
        ->and(app(PageStatus::class)->phase($page))->toBe(PageStatus::VALIDATION);
});

test('a page still in review is not reopened as defect', function () {
    $page = foodPage();
    expect(fn () => app(ReopenAsDefect::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Precio, 'x'))
        ->toThrow(AuthorizationException::class, 'aprobada a imprenta');
});
