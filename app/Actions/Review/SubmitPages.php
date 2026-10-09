<?php

namespace App\Actions\Review;

use App\Events\Review\PagesSubmitted;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * "Enviar a Marketing": a page leaves Ogilvy's preparation and its validation chain starts.
 * Without a page list, every page still in preparation is sent.
 *
 * @return Collection<int, Page> the pages that were sent
 */
class SubmitPages
{
    public function handle(User $by, Catalog $catalog, ?array $pageIds = null): Collection
    {
        Gate::forUser($by)->authorize('catalogs.manage');
        if ($catalog->isArchived()) {
            throw new InvalidArgumentException('Este catálogo está archivado.');
        }

        return DB::transaction(function () use ($by, $catalog, $pageIds) {
            $pages = $catalog->pages()->whereNull('submitted_at')
                ->when($pageIds !== null, fn ($q) => $q->whereIn('id', $pageIds))
                ->get();
            if ($pages->isEmpty()) {
                throw new InvalidArgumentException('No hay páginas en preparación para enviar.');
            }
            $now = now();
            foreach ($pages as $page) {
                $page->forceFill(['submitted_at' => $now, 'waiting_since' => $now, 'approval_alert_level' => null])->save();
            }
            $catalog->unsetRelation('pages');

            PagesSubmitted::dispatch($catalog, $pages, $by);

            return $pages;
        });
    }
}
