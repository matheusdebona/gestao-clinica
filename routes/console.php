<?php

use App\Jobs\CheckLowStockProductsJob;
use App\Jobs\NotifyAppointmentConfirmationsJob;
use App\Jobs\NotifyDailyReviewJob;
use App\Jobs\NotifyForgottenBudgetsJob;
use App\Jobs\NotifyStaleLeadsJob;
use App\Services\ExpenseGenerator;
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

// 11:00 UTC = 08:00 em Brasília: sessões de amanhã ainda sem confirmação (só aviso interno; o WhatsApp é por link).
Schedule::job(new NotifyAppointmentConfirmationsJob)->dailyAt('11:00');

// 11:00 UTC = 08:00 em Brasília: orçamentos enviados sem resposta que passaram de um prazo.
Schedule::job(new NotifyForgottenBudgetsJob)->dailyAt('11:00');

// 21:00 UTC = 18:00 em Brasília: lembrete de fim de dia (acompanhamentos pendentes / check-in).
Schedule::job(new NotifyDailyReviewJob)->dailyAt('21:00');

Artisan::command('auth:prune-temporary-passwords', function (TemporaryPasswordService $service) {
    $this->info('Senhas temporárias expiradas removidas: '.$service->pruneExpired());
})->purpose('Remove expired temporary passwords (Esqueceu a senha?)');

Schedule::command('auth:prune-temporary-passwords')->hourly();

Artisan::command('finance:generate-expenses', function (ExpenseGenerator $generator) {
    $this->info('Despesas geradas: '.$generator->generateCurrentMonthForAll());
})->purpose('Gera as despesas recorrentes e a depreciação do mês corrente');

// 03:00 UTC = 00:00 em Brasília: despesas recorrentes e depreciação do mês (idempotente).
Schedule::command('finance:generate-expenses')->dailyAt('03:00');
