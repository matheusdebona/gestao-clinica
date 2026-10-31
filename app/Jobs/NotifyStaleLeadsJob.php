<?php

namespace App\Jobs;

use App\Services\LeadStaleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyStaleLeadsJob implements ShouldQueue
{
    use Queueable;

    public function handle(LeadStaleService $stale): void
    {
        $stale->runDailyDigest();
    }
}
