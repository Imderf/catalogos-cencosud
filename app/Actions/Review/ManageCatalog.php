<?php

namespace App\Actions\Review;

use App\Domain\Projects\CatalogCards;
use App\Enums\CatalogKind;
use App\Events\Review\CatalogPublished;
use App\Jobs\ProcessPageVersionImage;
use App\Models\Brand;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** What Ogilvy can change on a catalog after publishing it. Every method needs catalogs.manage. */
class ManageCatalog
{
    public function update(User $by, Catalog $catalog, string $name, Brand $brand, ?CatalogKind $kind = null): Catalog
    {
        Gate::forUser($by)->authorize('catalogs.manage');
        $catalog->update(['name' => trim($name), 'brand_id' => $brand->id, ...($kind ? ['kind' => $kind->value] : [])]);

        return $catalog;
    }

    /**
     * Appends pages after the last one; Marketing is told there is new material.
     *
     * @param  list<array{image: mixed, sectors: list<int>}>  $pages
     */
    public function addPages(User $by, Catalog $catalog, array $pages): Catalog
    {
        Gate::forUser($by)->authorize('catalogs.manage');
        $this->notArchived($catalog);
        if ($pages === []) {
            throw new InvalidArgumentException('Sube al menos una página.');
        }

        return DB::transaction(function () use ($by, $catalog, $pages) {
            $next = ($catalog->pages()->max('number') ?? 0) + 1;
            foreach ($pages as $p) {
                if (empty($p['sectors'])) {
                    throw new InvalidArgumentException('Toda página necesita al menos un sector.');
                }
                $page = Page::create(['catalog_id' => $catalog->id, 'number' => $next, 'current_version' => 1, 'submitted_at' => null, 'waiting_since' => null]);
                $page->sectors()->sync($p['sectors']);
                $version = $page->versions()->create([
                    'number' => 1, 'uploaded_by' => $by->id,
                    'image_path' => PublishCatalog::storeImage($catalog, $next, 1, $p['image']),
                ]);
                ProcessPageVersionImage::dispatch($version);
                $next++;
            }
            $catalog->unsetRelation('pages');
            CatalogPublished::dispatch($catalog->load('pages'), $by);

            return $catalog;
        });
    }

    /** @param  list<int>  $pageIds  every page of the catalog, in the new order */
    public function reorder(User $by, Catalog $catalog, array $pageIds): Catalog
    {
        Gate::forUser($by)->authorize('catalogs.manage');
        $this->notArchived($catalog);
        $current = $catalog->pages()->pluck('id')->all();
        if (count($pageIds) !== count($current) || array_diff($current, $pageIds) !== []) {
            throw new InvalidArgumentException('El orden debe incluir todas las páginas del catálogo, una sola vez.');
        }

        DB::transaction(function () use ($pageIds) {
            /* Two passes: numbers are unique per catalog, so park them out of range first. */
            foreach ($pageIds as $i => $id) {
                Page::whereKey($id)->update(['number' => 1000 + $i]);
            }
            foreach ($pageIds as $i => $id) {
                Page::whereKey($id)->update(['number' => $i + 1]);
            }
        });

        return $catalog->unsetRelation('pages');
    }

    /** @param  list<int>  $sectorIds */
    public function setSectors(User $by, Page $page, array $sectorIds): Page
    {
        Gate::forUser($by)->authorize('catalogs.manage');
        $this->notArchived($page->catalog);
        if ($sectorIds === []) {
            throw new InvalidArgumentException('Toda página necesita al menos un sector.');
        }
        $page->sectors()->sync($sectorIds);

        return $page->unsetRelation('sectors');
    }

    /** Archived catalogs leave the list and the inbox; nothing can be done on them until reactivated. */
    public function archive(User $by, Catalog $catalog): Catalog
    {
        Gate::forUser($by)->authorize('catalogs.manage');
        $catalog->update(['archived_at' => now()]);

        return $catalog;
    }

    /**
     * Only an administrator deletes a catalog, and everything goes with it: pages, versions, artwork files,
     * adjustments, approvals and its card on the board. Archiving is the reversible alternative.
     */
    public function delete(User $by, Catalog $catalog): void
    {
        if (! $by->isAdmin()) {
            throw new AuthorizationException('Solo un administrador puede eliminar un catálogo. Puedes archivarlo.');
        }
        DB::transaction(function () use ($by, $catalog) {
            app(CatalogCards::class)->link($catalog, null, $by);
            $catalog->delete();
        });
        Storage::disk('local')->deleteDirectory('catalogos/'.$catalog->slug);
    }

    public function unarchive(User $by, Catalog $catalog): Catalog
    {
        Gate::forUser($by)->authorize('catalogs.manage');
        $catalog->update(['archived_at' => null]);

        return $catalog;
    }

    private function notArchived(Catalog $catalog): void
    {
        if ($catalog->isArchived()) {
            throw new InvalidArgumentException('Este catálogo está archivado. Reactívalo para modificarlo.');
        }
    }
}
