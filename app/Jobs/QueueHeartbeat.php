<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Proves the queue worker is alive: dispatched by the scheduler, stamped when a worker runs it. */
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::forever('heartbeat:queue', now()->toIso8601String());
    }
}
