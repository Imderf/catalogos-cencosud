<?php

namespace App\Listeners\Projects;

use App\Domain\Projects\CatalogCards;
use App\Domain\Projects\ProjectAlerts;
use App\Events\Review\AdjustmentCreated;
use App\Events\Review\AdjustmentResolved;
use App\Events\Review\CatalogPublished;
use App\Events\Review\PageApproved;
use App\Events\Review\PageReopenedAsDefect;
use App\Events\Review\PagesSubmitted;
use App\Events\Review\VersionUploaded;
use App\Models\AdjustmentIntake;
use Illuminate\Events\Dispatcher;

/** Keeps the catalog's card on its board in step with the review. */
class SyncCatalogCard
{
    public function __construct(private readonly CatalogCards $cards) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            CatalogPublished::class => 'onCatalogPublished',
            PagesSubmitted::class => 'onPagesSubmitted',
            AdjustmentCreated::class => 'onAdjustmentCreated',
            AdjustmentResolved::class => 'onAdjustmentResolved',
            PageApproved::class => 'onPageApproved',
            VersionUploaded::class => 'onVersionUploaded',
            PageReopenedAsDefect::class => 'onPageReopened',
        ];
    }

    /** A client comment: the page subtask gets an intake row and the moderators hear about it. */
    public function onAdjustmentCreated(AdjustmentCreated $e): void
    {
        $catalog = $e->adjustment->page->catalog;
        $this->cards->sync($catalog);
        $intake = AdjustmentIntake::where('adjustment_id', $e->adjustment->id)->with('subtask.card.project')->first();
        if ($intake && $intake->isPending()) {
            app(ProjectAlerts::class)->intakeArrived($intake, $e->by);
        }
    }

    public function onAdjustmentResolved(AdjustmentResolved $e): void
    {
        $this->cards->sync($e->adjustment->page->catalog);
    }

    /** Pages added later get their subtasks too. */
    public function onCatalogPublished(CatalogPublished $e): void
    {
        $this->cards->sync($e->catalog);
    }

    public function onPagesSubmitted(PagesSubmitted $e): void
    {
        $this->cards->sync($e->catalog);
    }

    public function onPageApproved(PageApproved $e): void
    {
        $this->cards->sync($e->approval->page->catalog);
    }

    public function onVersionUploaded(VersionUploaded $e): void
    {
        $this->cards->sync($e->version->page->catalog);
    }

    public function onPageReopened(PageReopenedAsDefect $e): void
    {
        $this->cards->sync($e->defect->page->catalog);
    }
}
