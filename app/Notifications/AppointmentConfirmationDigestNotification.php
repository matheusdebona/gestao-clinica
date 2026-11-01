<?php

namespace App\Notifications;

use App\Notifications\Channels\PushChannel;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AppointmentConfirmationDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'appointment_confirmations';

    public function __construct(
        public readonly string $date,
        public readonly int $total,
        public readonly int $toConfirm,
    ) {}

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
        $sessions = $this->total === 1 ? '1 sessão' : "{$this->total} sessões";
        $pending = $this->toConfirm === 1 ? '1 ainda precisa de confirmação' : "{$this->toConfirm} ainda precisam de confirmação";
        $day = CarbonImmutable::parse($this->date)->format('d/m');

        return [
            'type' => self::TYPE,
            'date' => $this->date,
            'count' => $this->toConfirm,
            'total' => $this->total,
            'title' => 'Confirmar amanhã',
            'message' => "Amanhã ({$day}) há {$sessions}; {$pending}. Envie a confirmação pelo WhatsApp na Agenda.",
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
