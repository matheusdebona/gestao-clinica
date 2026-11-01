<?php

namespace App\Notifications;

use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ForgottenBudgetsDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'forgotten_budgets';

    public function __construct(
        public readonly int $count,
        public readonly int $oldestDays,
        public readonly float $amount,
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
        $budgets = $this->count === 1 ? '1 orçamento enviado está' : "{$this->count} orçamentos enviados estão";
        $value = 'R$ '.number_format($this->amount, 2, ',', '.');
        $oldest = $this->oldestDays === 1 ? '1 dia' : "{$this->oldestDays} dias";

        return [
            'type' => self::TYPE,
            'count' => $this->count,
            'oldest_days' => $this->oldestDays,
            'amount' => number_format($this->amount, 2, '.', ''),
            'title' => 'Orçamentos esquecidos',
            'message' => "{$budgets} sem resposta ({$value}; o mais antigo há {$oldest}). Cobre pelo WhatsApp em Orçamentos.",
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
