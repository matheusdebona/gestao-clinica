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

    /**
     * @param  list<array{days: int, label: string, count: int}>  $groups  leads that just crossed each tier
     */
    public function __construct(public readonly int $count, public readonly array $groups) {}

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
        $lead = $this->count === 1 ? '1 lead passou' : "{$this->count} leads passaram";
        $parts = array_map(
            fn (array $group): string => "{$group['count']} em {$group['days']}+ dias",
            $this->groups,
        );
        $minDays = $this->groups[0]['days'] ?? null;

        return [
            'type' => self::TYPE,
            'count' => $this->count,
            'days' => $minDays,
            'groups' => $this->groups,
            'title' => 'Leads parados',
            'message' => "{$lead} de um prazo de acompanhamento sem contato: ".implode(', ', $parts).'.',
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
