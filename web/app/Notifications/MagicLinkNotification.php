<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification
{
    public function __construct(public readonly string $url, public readonly int $minutes) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu enlace para entrar en Fuji Recipes')
            ->greeting('Hola')
            ->line('Pulsa el botón para entrar. El enlace sirve una sola vez.')
            ->action('Entrar en Fuji Recipes', $this->url)
            ->line("Caduca en {$this->minutes} minutos. Si no lo has pedido tú, ignora este correo.")
            ->salutation(' ');
    }
}
