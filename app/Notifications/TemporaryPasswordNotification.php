<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemporaryPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $temporaryPassword,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'HOF Pay');
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject("Sua senha temporária — {$appName}")
            ->view(
                ['html' => 'mail.temporary-password', 'text' => 'mail.temporary-password-text'],
                [
                    'appName' => $appName,
                    'name' => $notifiable->name ?? '',
                    'temporaryPassword' => $this->temporaryPassword,
                    'expiresInMinutes' => $this->expiresInMinutes,
                    'loginUrl' => $frontend.'/login?email='.rawurlencode((string) ($notifiable->email ?? '')),
                    'logoUrl' => $frontend.'/icon-192.png',
                ],
            );
    }
}
