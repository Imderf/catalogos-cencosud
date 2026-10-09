<?php

namespace App\Domain\Review;

use App\Domain\Time\BusinessTime;
use App\Enums\CardPriority;
use App\Enums\Role;
use App\Models\Adjustment;
use App\Models\Card;
use Illuminate\Support\Collection;

/**
 * Ogilvy does not run every clock at once. Adjustments waiting for Ogilvy start in order of the
 * priority of the catalog's task on the board: only those at the highest priority among everything
 * pending for Ogilvy get their clock started; the rest wait ("en cola") until those are resolved.
 * A clock that already started keeps running. Catalogs without a task count as medium priority.
 */
final class AgencyQueue
{
    private const RANK = [CardPriority::Urgent->value => 4, CardPriority::High->value => 3, CardPriority::Medium->value => 2, CardPriority::Low->value => 1];

    public function __construct(private readonly BusinessTime $time) {}

    /** Start the clocks that may start now. Returns the adjustments that just started. */
    public function refresh(): Collection
    {
        $pending = Adjustment::open()->whereNotNull('received_by_agency_at')
            ->whereHas('page.catalog', fn ($q) => $q->active())
            ->with('page:id,catalog_id')->get()
            ->filter(fn (Adjustment $a) => $a->currentStage() === Role::Agencia);
        if ($pending->isEmpty()) {
            return collect();
        }
        $priorities = Card::whereIn('catalog_id', $pending->pluck('page.catalog_id')->unique())->get(['catalog_id', 'priority'])
            ->mapWithKeys(fn (Card $c) => [$c->catalog_id => $c->priority?->value ?? CardPriority::Medium->value]);
        $rank = fn (Adjustment $a) => self::RANK[$priorities->get($a->page->catalog_id, CardPriority::Medium->value)] ?? 2;
        $top = $pending->max($rank);
        $now = $this->time->now();

        return $pending->filter(fn (Adjustment $a) => $a->agency_started_at === null && $rank($a) === $top)
            ->each(fn (Adjustment $a) => $a->forceFill(['agency_started_at' => $now, 'stage_since' => $now, 'alert_level' => null])->save())
            ->values();
    }

    public function isQueued(Adjustment $a): bool
    {
        return $a->isOpen() && $a->currentStage() === Role::Agencia && $a->agency_started_at === null;
    }

    public function priorityLabel(Adjustment $a): ?string
    {
        return Card::where('catalog_id', $a->page->catalog_id)->first()?->priority?->label();
    }
}
