<?php

use App\Jobs\CheckLowStockProductsJob;
use App\Jobs\NotifyStaleLeadsJob;
use App\Services\TemporaryPasswordService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CheckLowStockProductsJob)->dailyAt('01:00');

// 11:00 UTC = 08:00 em Brasília: resumo diário de leads parados.
Schedule::job(new NotifyStaleLeadsJob)->dailyAt('11:00');

Artisan::command('auth:prune-temporary-passwords', function (TemporaryPasswordService $service) {
    $this->info('Senhas temporárias expiradas removidas: '.$service->pruneExpired());
})->purpose('Remove expired temporary passwords (Esqueceu a senha?)');

Schedule::command('auth:prune-temporary-passwords')->hourly();
