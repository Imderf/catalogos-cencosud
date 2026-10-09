<?php

namespace App\Listeners\Review;

use App\Domain\Review\AgencyQueue;
use App\Events\Review\AdjustmentCreated;
use App\Events\Review\AdjustmentResolved;
use App\Events\Review\AdjustmentValidated;
use App\Events\Review\PageReopenedAsDefect;
use App\Events\Review\VersionUploaded;
use Illuminate\Events\Dispatcher;

/** Every time work arrives at or leaves Ogilvy, the queue decides which clocks start. */
class AgencyQueueSubscriber
{
    public function __construct(private readonly AgencyQueue $queue) {}

    public function subscribe(Dispatcher $events): array
    {
        return array_fill_keys([AdjustmentCreated::class, AdjustmentValidated::class, AdjustmentResolved::class, VersionUploaded::class, PageReopenedAsDefect::class], 'refresh');
    }

    public function refresh(): void
    {
        $this->queue->refresh();
    }
}
