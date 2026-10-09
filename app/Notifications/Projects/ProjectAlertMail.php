<?php

namespace App\Notifications\Projects;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectAlertMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly array $payload) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payload;
        $mail = (new MailMessage)
            ->subject($p['text'])
            ->greeting('Hola, '.$notifiable->name)
            ->line($p['text'].'.')
            ->line($p['project_name'].' · '.$p['actor']);
        if (! empty($p['excerpt'])) {
            $mail->line('"'.$p['excerpt'].'"');
        }

        return $mail->action('Abrir tarjeta', $p['url']);
    }
}
