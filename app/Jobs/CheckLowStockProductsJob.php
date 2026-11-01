<?php

namespace App\Jobs;

use App\Services\StockAlertService;
use App\Support\ClinicMonth;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckLowStockProductsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $day = null) {}

    public function handle(StockAlertService $stockAlerts): void
    {
        // Dia de calendário de São Paulo; sem dia: amanhã (roda às 22:00 de Brasília).
        $day = $this->day !== null
            ? CarbonImmutable::parse(substr($this->day, 0, 10), ClinicMonth::TZ)
            : ClinicMonth::today()->addDay();

        $stockAlerts->runDailyChecks($day);
    }
}
