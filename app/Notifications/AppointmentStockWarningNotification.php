<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AppointmentStockWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly Appointment $appointment,
        public readonly array $warnings,
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
        $message = 'Alerta de estoque no agendamento #'.$this->appointment->id.': '.implode(' ', $this->warnings);

        return [
            'type' => 'appointment_stock_warning',
            'appointment_id' => $this->appointment->id,
            'treatment_id' => $this->appointment->treatment_id,
            'warnings' => $this->warnings,
            'title' => 'Estoque no agendamento',
            'message' => $message,
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
