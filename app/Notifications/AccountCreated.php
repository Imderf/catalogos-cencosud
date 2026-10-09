<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an administrator creates an account. The initial password is handed over
 * by the administrator in person; it is never sent by email.
 */
class AccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $createdBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu cuenta en '.config('app.name'))
            ->greeting('Hola, '.$notifiable->name)
            ->line($this->createdBy.' te creó una cuenta en la plataforma de revisión de catálogos.')
            ->line('Ingresa con tu correo **'.$notifiable->email.'** y la contraseña que te entregó '.$this->createdBy.'. El sistema te pedirá cambiarla en el primer ingreso.')
            ->action('Ingresar', route('login'))
            ->line('Si no esperabas este correo, ignóralo.');
    }
}
