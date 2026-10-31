<?php

use App\Jobs\CheckLowStockProductsJob;
use App\Services\TemporaryPasswordService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CheckLowStockProductsJob)->dailyAt('01:00');

Artisan::command('auth:prune-temporary-passwords', function (TemporaryPasswordService $service) {
    $this->info('Senhas temporárias expiradas removidas: '.$service->pruneExpired());
})->purpose('Remove expired temporary passwords (Esqueceu a senha?)');

Schedule::command('auth:prune-temporary-passwords')->hourly();
