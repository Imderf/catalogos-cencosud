<?php

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\PublishCatalog;
use App\Actions\Review\SubmitPages;
use App\Actions\Review\ValidatePage;
use App\Domain\Review\Clocks;
use App\Domain\Review\Inbox;
use App\Domain\Review\PageStatus;
use App\Enums\AdjustmentType;
use App\Enums\CatalogKind;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Sector;
use App\Support\Box;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

test('a freshly published catalog sits in Ogilvy preparation: no clock, no validation, not in the inbox', function () {
    Notification::fake();
    $agency = userWithRole(Role::Agencia);
    $marketing = userWithRole(Role::Marketing);
    $food = Sector::where('slug', 'liquidos')->value('id');
    $catalog = app(PublishCatalog::class)->handle($agency, Brand::first(), 'Jumbo · Cuartilla', [['image' => 'catalogos/x/p01-v1.jpg', 'sectors' => [$food]]], CatalogKind::Cuartilla);
    $page = $catalog->pages->first();
    $status = app(PageStatus::class);

    expect($catalog->kind)->toBe(CatalogKind::Cuartilla)
        ->and($page->isSubmitted())->toBeFalse()
        ->and($status->phase($page))->toBe(PageStatus::PREPARATION)
        ->and($status->location($page)->label)->toBe('En Ogilvy · preparación')
        ->and(app(Clocks::class)->pageClock($page))->toBeNull()
        ->and(app(Inbox::class)->counts($marketing)['approvals'])->toBe(0);

    expect(fn () => app(ValidatePage::class)->handle($marketing, $page))->toThrow(AuthorizationException::class, 'todavía no envió');
    expect(fn () => app(CreateAdjustment::class)->handle($marketing, $page, AdjustmentType::Precio, 'x', Box::defectDefault()))->toThrow(AuthorizationException::class, 'todavía no envió');
});

test('sending to Marketing starts the chain and the clock; only Ogilvy can send, and only once', function () {
    Notification::fake();
    $agency = userWithRole(Role::Agencia);
    $marketing = userWithRole(Role::Marketing);
    $page = Page::factory()->sectors(['liquidos'])->preparing()->create();
    $catalog = $page->catalog;

    expect(fn () => app(SubmitPages::class)->handle($marketing, $catalog))->toThrow(AuthorizationException::class);

    $sent = app(SubmitPages::class)->handle($agency, $catalog);
    $page->refresh();
    expect($sent)->toHaveCount(1)
        ->and($page->isSubmitted())->toBeTrue()
        ->and(app(PageStatus::class)->phase($page))->toBe(PageStatus::VALIDATION)
        ->and(app(Clocks::class)->pageClock($page)->remaining)->toBe(240.0)
        ->and(app(Inbox::class)->counts($marketing)['approvals'])->toBe(1);
    app(ValidatePage::class)->handle($marketing, $page);

    expect(fn () => app(SubmitPages::class)->handle($agency, $catalog->fresh()))->toThrow(InvalidArgumentException::class, 'No hay páginas en preparación');
});

test('the HTTP routes send one page or the whole catalog', function () {
    Notification::fake();
    $agency = userWithRole(Role::Agencia);
    $a = Page::factory()->sectors(['liquidos'])->preparing()->create(['number' => 1]);
    $b = Page::factory()->sectors(['liquidos'])->preparing()->create(['catalog_id' => $a->catalog_id, 'number' => 2]);
    $catalog = $a->catalog;

    $this->actingAs($agency)->post(route('catalogs.pages.submit', [$catalog->slug, 1]))->assertSessionHas('success', 'Página 01 enviada a Marketing · empieza la validación.');
    expect($a->fresh()->isSubmitted())->toBeTrue()->and($b->fresh()->isSubmitted())->toBeFalse();

    $this->actingAs($agency)->post(route('catalogs.submit', $catalog->slug))->assertSessionHas('success', 'Página 02 enviada a Marketing · empieza la validación.');
    expect($b->fresh()->isSubmitted())->toBeTrue();
    $this->actingAs(userWithRole(Role::Marketing))->post(route('catalogs.submit', $catalog->slug))->assertForbidden();
});

test('the catalog list filters by deliverable kind', function () {
    Catalog::factory()->create(['name' => 'Oct', 'kind' => 'octavilla']);
    Catalog::factory()->create(['name' => 'Vol', 'kind' => 'volante']);
    $this->actingAs(userWithRole(Role::Marketing))->get(route('catalogs.index', ['tipo' => 'volante']))
        ->assertInertia(fn ($p) => $p->has('catalogs', 1)->where('catalogs.0.name', 'Vol')->where('catalogs.0.kind_label', 'Volante'));
});
