<?php

namespace App\Jobs;

use App\Services\StockAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckLowStockProductsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $day = null) {}

    public function handle(StockAlertService $stockAlerts): void
    {
        $day = $this->day !== null
            ? CarbonImmutable::parse($this->day)
            : CarbonImmutable::now();

        $stockAlerts->runDailyChecks($day);
    }
}
