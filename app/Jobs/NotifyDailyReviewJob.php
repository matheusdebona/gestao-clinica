<?php

namespace App\Jobs;

use App\Services\LeadDailyReviewReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyDailyReviewJob implements ShouldQueue
{
    use Queueable;

    public function handle(LeadDailyReviewReminder $reminder): void
    {
        $reminder->run();
    }
}
