<?php

namespace App\Events\Projects;

use App\Models\Card;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** One line of history, pushed live so teammates on the board see "Daniela movió X" as a toast. */
class ActivityLogged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly int $projectId, public readonly ?User $by, public readonly Card $card, public readonly string $text) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->projectId)];
    }

    public function broadcastAs(): string
    {
        return 'activity.new';
    }

    public function broadcastWith(): array
    {
        return [
            'actor' => $this->by ? ['id' => $this->by->id, 'name' => $this->by->name] : null,
            'card' => ['id' => $this->card->id, 'title' => $this->card->title],
            'text' => $this->text,
        ];
    }
}
