<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Igual que ResetPasswordNotification (staff) pero apunta a la ruta de
 * reset de la tienda online, no a la del panel de staff — mismo guard que
 * disparó el pedido, ver App\Models\Customer::sendPasswordResetNotification().
 */
class TiendaResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('tienda.account.reset-password', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Recuperar contraseña - '.config('app.name'))
            ->greeting('Hola'.($notifiable->name ? ', '.$notifiable->name : '').'.')
            ->line('Pediste restablecer tu contraseña en la tienda online.')
            ->action('Elegir nueva contraseña', $url)
            ->line('Este link vence en 60 minutos.')
            ->line('Si no pediste esto, podés ignorar este mensaje: tu contraseña sigue siendo la misma.');
    }
}
