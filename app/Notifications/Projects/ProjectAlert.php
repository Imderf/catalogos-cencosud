<?php

namespace App\Notifications\Projects;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/** In-app line for a board event; stored with a ready URL so the bandeja links straight to the card. */
class ProjectAlert extends Notification
{
    public function __construct(public readonly array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->payload))->onConnection('sync');
    }
}
