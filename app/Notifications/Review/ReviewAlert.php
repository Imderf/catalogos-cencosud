<?php

namespace App\Notifications\Review;

use Illuminate\Notifications\Notification;

/** In-app copy of an alert: written synchronously so the bandeja is always current. */
class ReviewAlert extends Notification
{
    public function __construct(public readonly AlertPayload $payload) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload->toArray();
    }
}
