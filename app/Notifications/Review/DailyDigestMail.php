<?php

namespace App\Notifications\Review;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One morning email: what waits for the user and what happened since yesterday. */
class DailyDigestMail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{title:string, detail:string, url:string}>  $pending
     * @param  list<array{text:string, actor:string, url:string}>  $recent
     */
    public function __construct(public readonly array $pending, public readonly array $recent) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $n = count($this->pending);
        $mail = (new MailMessage)
            ->subject($n ? "Tienes {$n} pendiente".($n === 1 ? '' : 's').' en catálogos' : 'Resumen de catálogos')
            ->greeting('Hola, '.$notifiable->name);

        if ($this->pending) {
            $mail->line('**Esto espera por ti:**');
            foreach (array_slice($this->pending, 0, 15) as $p) {
                $mail->line('• '.$p['title'].' · '.$p['detail']);
            }
        } else {
            $mail->line('No hay nada esperando por tu equipo ahora mismo.');
        }
        if ($this->recent) {
            $mail->line('**Desde ayer:**');
            foreach (array_slice($this->recent, 0, 20) as $r) {
                $mail->line('• '.$r['text'].' · '.$r['actor']);
            }
        }

        return $mail->action('Abrir notificaciones', route('notifications.index'))
            ->line('Recibes este resumen porque elegiste "Un resumen cada mañana laboral" en Mi cuenta.');
    }
}
