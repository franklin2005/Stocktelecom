<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    use Queueable;

    /**
     * Construye el correo de restablecimiento de contraseña.
     */
    public function toMail($notifiable): MailMessage
    {
        // URL del enlace de reseteo (usa la ruta estándar de Laravel)
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Restablecimiento de contraseña')
            ->greeting('¡Hola!')
            ->line('Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace caducará en 60 minutos.')
            ->line('Si no realizaste esta solicitud, puedes ignorar este mensaje.')
            ->salutation('Saludos, ' . config('app.name'));
    }
}
