<?php

namespace App\Events\Review;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells every open browser that something moved, so it refreshes its props at once
 * instead of waiting for the minute poll. Carries no content: the page reloads from the server.
 */
class ReviewChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly string $kind, public readonly string $catalog, public readonly ?int $page = null) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('review')];
    }

    public function broadcastAs(): string
    {
        return 'review.changed';
    }

    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'catalog' => $this->catalog, 'page' => $this->page];
    }
}
