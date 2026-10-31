<?php

namespace App\Notifications;

use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StaleLeadsDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'stale_leads';

    public function __construct(public readonly int $count, public readonly int $days) {}

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        return ['database', PushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $leads = $this->count === 1 ? '1 lead está parado' : "{$this->count} leads estão parados";
        $days = $this->days === 1 ? '1 dia' : "{$this->days} dias";

        return [
            'type' => self::TYPE,
            'count' => $this->count,
            'days' => $this->days,
            'title' => 'Leads parados',
            'message' => "{$leads} há {$days} ou mais sem nenhum contato.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['title'],
            'body' => $data['message'],
            'data' => $data,
        ];
    }
}
