<?php

namespace App\Jobs;

use App\Services\AppointmentConfirmationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyAppointmentConfirmationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(AppointmentConfirmationService $confirmations): void
    {
        $confirmations->runDailyDigest();
    }
}
