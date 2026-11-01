<?php

namespace App\Jobs;

use App\Services\BudgetFollowUpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyForgottenBudgetsJob implements ShouldQueue
{
    use Queueable;

    public function handle(BudgetFollowUpService $budgets): void
    {
        $budgets->runDailyDigest();
    }
}
