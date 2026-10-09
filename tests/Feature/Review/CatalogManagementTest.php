<?php

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\ManageCatalog;
use App\Actions\Review\SubmitPages;
use App\Actions\Review\ValidatePage;
use App\Domain\Projects\CatalogCards;
use App\Domain\Review\Inbox;
use App\Enums\AdjustmentType;
use App\Enums\CatalogKind;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Card;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Project;
use App\Models\Sector;
use App\Notifications\Review\ReviewAlert;
use App\Support\Box;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

test('only Ogilvy manages catalogs', function () {
    $catalog = Catalog::factory()->create();
    expect(fn () => app(ManageCatalog::class)->archive(userWithRole(Role::Marketing), $catalog))->toThrow(AuthorizationException::class);
    $this->actingAs(userWithRole(Role::Marketing))->get(route('catalogs.edit', $catalog))->assertForbidden();
});

test('Ogilvy renames a catalog and changes its brand and kind', function () {
    $catalog = Catalog::factory()->create(['name' => 'Viejo']);
    $metro = Brand::where('slug', 'metro')->first();
    app(ManageCatalog::class)->update(userWithRole(Role::Agencia), $catalog, 'Metro · Volante 25–27 Sep', $metro, CatalogKind::Volante);
    expect($catalog->fresh()->name)->toBe('Metro · Volante 25–27 Sep')->and($catalog->fresh()->brand_id)->toBe($metro->id)
        ->and($catalog->fresh()->kind)->toBe(CatalogKind::Volante);
});

test('added pages go after the last one, get their sectors and alert Marketing', function () {
    Storage::fake('local');
    Queue::fake();
    Notification::fake();
    $catalog = Catalog::factory()->create();
    Page::factory()->create(['catalog_id' => $catalog->id, 'number' => 1]);
    Page::factory()->create(['catalog_id' => $catalog->id, 'number' => 2]);
    $marketing = userWithRole(Role::Marketing);
    $dph = Sector::where('slug', 'dph')->value('id');

    app(ManageCatalog::class)->addPages(userWithRole(Role::Agencia), $catalog, [
        ['image' => UploadedFile::fake()->image('p3.jpg', 100, 200), 'sectors' => [$dph]],
    ]);

    $page = $catalog->pages()->reorder('number', 'desc')->first();
    expect($catalog->pages()->count())->toBe(3)->and($page->number)->toBe(3)
        ->and($page->sectors->pluck('slug')->all())->toBe(['dph'])
        ->and($page->versions()->count())->toBe(1)
        ->and($page->isSubmitted())->toBeFalse();
    Notification::assertNotSentTo($marketing, ReviewAlert::class);

    app(SubmitPages::class)->handle(userWithRole(Role::Agencia), $catalog->fresh(), [$page->id]);
    Notification::assertSentTo($marketing, ReviewAlert::class, fn ($n) => $n->payload->kind === 'carga' && $n->payload->pageNumber === 3);
});

test('reordering renumbers the pages and rejects partial orders', function () {
    $catalog = Catalog::factory()->create();
    [$a, $b, $c] = collect([1, 2, 3])->map(fn ($n) => Page::factory()->create(['catalog_id' => $catalog->id, 'number' => $n]))->all();
    $agency = userWithRole(Role::Agencia);

    app(ManageCatalog::class)->reorder($agency, $catalog, [$c->id, $a->id, $b->id]);
    expect($c->fresh()->number)->toBe(1)->and($a->fresh()->number)->toBe(2)->and($b->fresh()->number)->toBe(3);

    expect(fn () => app(ManageCatalog::class)->reorder($agency, $catalog, [$a->id]))->toThrow(InvalidArgumentException::class);
});

test('changing the sectors of a page changes who may act on it', function () {
    $page = foodPage();
    $agency = userWithRole(Role::Agencia);
    $dphUser = userWithRole(Role::Comercial, ['dph']);
    $dph = Sector::where('slug', 'dph')->value('id');
    expect($dphUser->canActOn($page))->toBeFalse();

    app(ManageCatalog::class)->setSectors($agency, $page, [$dph]);
    expect($dphUser->canActOn($page->fresh()))->toBeTrue();
    expect(fn () => app(ManageCatalog::class)->setSectors($agency, $page, []))->toThrow(InvalidArgumentException::class);
});

test('an archived catalog leaves the list and the inbox, blocks every action, and comes back when reactivated', function () {
    $page = foodPage();
    $catalog = $page->catalog;
    $agency = userWithRole(Role::Agencia);
    $marketing = userWithRole(Role::Marketing);
    expect(app(Inbox::class)->counts($marketing)['approvals'])->toBe(1);

    app(ManageCatalog::class)->archive($agency, $catalog);

    expect(app(Inbox::class)->counts($marketing)['approvals'])->toBe(0);
    $this->actingAs($marketing)->get(route('catalogs.index'))->assertInertia(fn ($p) => $p->has('catalogs', 0));
    $this->actingAs($marketing)->get(route('catalogs.index', ['archivados' => 1]))->assertInertia(fn ($p) => $p->has('catalogs', 1)->where('catalogs.0.archived_at', fn ($v) => $v !== null));
    expect(fn () => app(ValidatePage::class)->handle($marketing, $page->fresh()))->toThrow(AuthorizationException::class, 'archivado');
    expect(fn () => app(CreateAdjustment::class)->handle($marketing, $page->fresh(), AdjustmentType::Precio, 'x', Box::defectDefault()))->toThrow(AuthorizationException::class, 'archivado');
    expect(fn () => app(ManageCatalog::class)->reorder($agency, $catalog->fresh(), [$page->id]))->toThrow(InvalidArgumentException::class, 'archivado');

    app(ManageCatalog::class)->unarchive($agency, $catalog->fresh());
    expect(app(Inbox::class)->counts($marketing)['approvals'])->toBe(1);
    app(ValidatePage::class)->handle($marketing, $page->fresh());
});

test('only an administrator deletes a catalog, typing its name; files and the board card go with it', function () {
    Storage::fake('local');
    $admin = userWithRole(Role::Admin);
    $agency = userWithRole(Role::Agencia);
    $catalog = Catalog::factory()->create(['name' => 'Volante Prueba']);
    $page = Page::factory()->create(['catalog_id' => $catalog->id, 'number' => 1]);
    Storage::disk('local')->put('catalogos/'.$catalog->slug.'/p01-v1.jpg', 'x');
    $project = Project::factory()->create(['owner_id' => $admin->id]);
    $project->lists()->create(['name' => 'Backlog', 'kind' => 'backlog', 'position' => 1024]);
    app(CatalogCards::class)->link($catalog, $project, $admin);
    expect(Card::where('catalog_id', $catalog->id)->exists())->toBeTrue();

    $this->actingAs($agency)->delete(route('catalogs.destroy', $catalog), ['confirm' => 'Volante Prueba'])->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('catalogs.destroy', $catalog), ['confirm' => 'otro nombre'])->assertSessionHas('error');
    expect(Catalog::find($catalog->id))->not->toBeNull();

    $this->actingAs($admin)->delete(route('catalogs.destroy', $catalog), ['confirm' => 'Volante Prueba'])->assertRedirect(route('catalogs.index'));
    expect(Catalog::find($catalog->id))->toBeNull()
        ->and(Page::find($page->id))->toBeNull()
        ->and(Card::where('catalog_id', $catalog->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('catalogos/'.$catalog->slug.'/p01-v1.jpg');
});
