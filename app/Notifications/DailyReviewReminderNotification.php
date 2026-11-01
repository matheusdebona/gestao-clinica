<?php

namespace App\Notifications;

use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DailyReviewReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'daily_review';

    public function __construct(public readonly int $pending, public readonly bool $checkinDone) {}

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
        $parts = [];
        if ($this->pending > 0) {
            $parts[] = $this->pending === 1 ? '1 lead esperando acompanhamento' : "{$this->pending} leads esperando acompanhamento";
        }
        if (! $this->checkinDone) {
            $parts[] = 'check-in do dia não feito';
        }

        return [
            'type' => self::TYPE,
            'pending' => $this->pending,
            'checkin_done' => $this->checkinDone,
            'title' => 'Resumo do dia',
            'message' => ucfirst(implode(' e ', $parts)).'.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return ['title' => $data['title'], 'body' => $data['message'], 'data' => $data];
    }
}
