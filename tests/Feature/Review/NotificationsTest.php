<?php

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\PublishCatalog;
use App\Actions\Review\ReopenAsDefect;
use App\Actions\Review\ResolveAdjustment;
use App\Actions\Review\SubmitPages;
use App\Actions\Review\UploadVersion;
use App\Actions\Review\ValidateAdjustment;
use App\Actions\Review\ValidatePage;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Sector;
use App\Notifications\Review\ReviewAlert;
use App\Notifications\Review\ReviewAlertMail;
use App\Support\Box;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    openHours();
    Notification::fake();
});

$box = fn () => Box::fromArray(['x' => .1, 'y' => .2, 'w' => .3, 'h' => .1]);

test('a new adjustment alerts the next link in the app and by email, only within its sector', function () use ($box) {
    $page = foodPage();
    $commercial = userWithRole(Role::Comercial, ['liquidos']);
    $planningA = userWithRole(Role::Planificacion);
    $marketing = userWithRole(Role::Marketing);
    $agency = userWithRole(Role::Agencia);

    app(CreateAdjustment::class)->handle($commercial, $page, AdjustmentType::Precio, 'x', $box());

    Notification::assertSentTo($planningA, ReviewAlert::class, fn ($n) => str_contains($n->payload->text, 'Ajuste de precio en la Página 01'));
    Notification::assertSentTo($planningA, ReviewAlertMail::class);
    Notification::assertNotSentTo([$marketing, $agency, $commercial], ReviewAlert::class);
});

test('commercial recipients are filtered by the page sectors', function () {
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    $planning = userWithRole(Role::Planificacion);
    app(ValidatePage::class)->handle($marketing, $page);
    Notification::assertSentTo($planning, ReviewAlert::class);

    $food = userWithRole(Role::Comercial, ['liquidos']);
    $dph = userWithRole(Role::Comercial, ['dph']);
    app(ValidatePage::class)->handle($planning, $page->refresh());

    Notification::assertSentTo($food, ReviewAlert::class, fn ($n) => str_contains($n->payload->text, 'Planificación validó'));
    Notification::assertNotSentTo($dph, ReviewAlert::class);
});

test('validation, resolution, version, closing approval and defect each alert the right role', function () use ($box) {
    $page = foodPage();
    $commercial = userWithRole(Role::Comercial, ['liquidos']);
    $planning = userWithRole(Role::Planificacion);
    $marketing = userWithRole(Role::Marketing);
    $agency = userWithRole(Role::Agencia);

    $adj = app(CreateAdjustment::class)->handle($commercial, $page, AdjustmentType::Imagen, 'x', $box());
    $adj = app(ValidateAdjustment::class)->handle($planning, $adj);
    Notification::assertSentTo($marketing, ReviewAlert::class, fn ($n) => $n->payload->kind === 'validacion');

    $adj = app(ValidateAdjustment::class)->handle($marketing, $adj);
    Notification::assertSentTo($agency, ReviewAlert::class, fn ($n) => $n->payload->kind === 'validacion');

    app(ResolveAdjustment::class)->handle($agency, $adj);
    Notification::assertSentTo($marketing, ReviewAlert::class, fn ($n) => $n->payload->kind === 'resuelto');

    app(UploadVersion::class)->handle($agency, $page->refresh(), 'catalogos/x/p01-v2.jpg');
    Notification::assertSentTo($marketing, ReviewAlert::class, fn ($n) => $n->payload->kind === 'version' && str_contains($n->payload->text, 'v2'));

    closeWholeChain($page, $marketing, $planning, $commercial);
    Notification::assertSentTo($agency, ReviewAlert::class, fn ($n) => str_contains($n->payload->text, 'aprobada a imprenta por toda la cadena'));
    Notification::assertSentTo($planning, ReviewAlert::class, fn ($n) => str_contains($n->payload->text, 'aprobó la') && str_contains($n->payload->text, 'te toca aprobar') && ! str_contains($n->payload->text, 'imprenta'));

    app(ReopenAsDefect::class)->handle($marketing, $page->refresh(), AdjustmentType::Precio, 'Salió mal');
    Notification::assertSentTo($agency, ReviewAlert::class, fn ($n) => $n->payload->kind === 'defecto');
    Notification::assertSentToTimes($agency, ReviewAlert::class, 3); // validación, cierre de cadena, defecto (no doble por el ajuste-defecto)
});

test('publishing a catalog tells nobody; sending its pages to Marketing does', function () {
    $agency = userWithRole(Role::Agencia);
    $marketing = userWithRole(Role::Marketing);
    $food = Sector::where('slug', 'liquidos')->value('id');

    $catalog = app(PublishCatalog::class)->handle($agency, Brand::first(), 'Jumbo · Prueba', [
        ['image' => 'catalogos/x/p01-v1.jpg', 'sectors' => [$food]],
        ['image' => 'catalogos/x/p02-v1.jpg', 'sectors' => [$food]],
    ]);
    Notification::assertNotSentTo($marketing, ReviewAlert::class);

    app(SubmitPages::class)->handle($agency, $catalog);
    Notification::assertSentTo($marketing, ReviewAlert::class, fn ($n) => $n->payload->kind === 'carga' && str_contains($n->payload->text, '2 páginas') && $n->payload->pageNumber === null);
});

test('inactive users receive nothing', function () use ($box) {
    $page = foodPage();
    $off = userWithRole(Role::Agencia);
    $off->update(['is_active' => false]);
    app(CreateAdjustment::class)->handle(userWithRole(Role::Marketing), $page, AdjustmentType::Precio, 'x', $box());
    Notification::assertNotSentTo($off, ReviewAlert::class);
});
