<?php

namespace App\Notifications\Review;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email copy of an alert, sent from the queue. */
class ReviewAlertMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AlertPayload $payload) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payload;
        $where = $p->catalogName.($p->pageNumber ? ' · página '.str_pad((string) $p->pageNumber, 2, '0', STR_PAD_LEFT) : '');

        return (new MailMessage)
            ->subject($p->text)
            ->greeting('Hola, '.$notifiable->name)
            ->line($p->text.'.')
            ->line($where.' · '.$p->actor)
            ->action($p->pageNumber ? 'Abrir página' : 'Abrir catálogo', $p->url())
            ->line('Los tiempos corren en minutos laborales; fuera de horario los cronómetros quedan en pausa.');
    }
}
