<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset for app customers (D3): the link opens the mobile app, which
 * posts the token to /api/v1/auth/reset-password.
 */
class CustomerResetPassword extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('services.mobile.reset_password_url').'?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('Reset your password'))
            ->line(__('You asked to reset your password. Open the link on your phone to choose a new one.'))
            ->action(__('Reset password'), $url)
            ->line(__('Or enter this code in the app: :code', ['code' => $this->token]))
            ->line(__('The link expires in :minutes minutes. If you did not ask for this, ignore this e-mail.', ['minutes' => config('auth.passwords.customers.expire')]));
    }
}
