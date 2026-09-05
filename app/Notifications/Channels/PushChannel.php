<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Stub push channel — contract ready for Web Push / FCM later.
 * Currently persists intent via log only (no provider dispatch).
 */
class PushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPush')) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = $notification->toPush($notifiable);

        Log::debug('push.notification.stub', [
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->getKey(),
            'notification' => $notification::class,
            'payload' => $payload,
        ]);
    }
}
