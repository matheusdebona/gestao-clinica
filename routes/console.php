<?php

use App\Jobs\CheckLowStockProductsJob;
use App\Jobs\NotifyAppointmentConfirmationsJob;
use App\Jobs\NotifyDailyReviewJob;
use App\Jobs\NotifyForgottenBudgetsJob;
use App\Jobs\NotifyStaleLeadsJob;
use App\Services\ExpenseGenerator;
use App\Services\TemporaryPasswordService;
use App\Support\ClinicMonth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Horários em Brasília (America/Sao_Paulo), não em UTC.
// 22:00: estoque baixo e estoque previsto para os atendimentos de amanhã.
Schedule::job(new CheckLowStockProductsJob)->dailyAt('22:00')->timezone(ClinicMonth::TZ);

// 08:00: resumo diário de leads parados.
Schedule::job(new NotifyStaleLeadsJob)->dailyAt('08:00')->timezone(ClinicMonth::TZ);

// 08:00: sessões de amanhã ainda sem confirmação (só aviso interno; o WhatsApp é por link).
Schedule::job(new NotifyAppointmentConfirmationsJob)->dailyAt('08:00')->timezone(ClinicMonth::TZ);

// 08:00: orçamentos enviados sem resposta que passaram de um prazo.
Schedule::job(new NotifyForgottenBudgetsJob)->dailyAt('08:00')->timezone(ClinicMonth::TZ);

// 18:00: lembrete de fim de dia (acompanhamentos pendentes / check-in).
Schedule::job(new NotifyDailyReviewJob)->dailyAt('18:00')->timezone(ClinicMonth::TZ);

Artisan::command('auth:prune-temporary-passwords', function (TemporaryPasswordService $service) {
    $this->info('Senhas temporárias expiradas removidas: '.$service->pruneExpired());
})->purpose('Remove expired temporary passwords (Esqueceu a senha?)');

Schedule::command('auth:prune-temporary-passwords')->hourly();

Artisan::command('finance:generate-expenses', function (ExpenseGenerator $generator) {
    $this->info('Despesas geradas: '.$generator->generateCurrentMonthForAll());
})->purpose('Gera as despesas recorrentes e a depreciação do mês corrente');

// 00:05: despesas recorrentes e depreciação do mês de Brasília (idempotente).
Schedule::command('finance:generate-expenses')->dailyAt('00:05')->timezone(ClinicMonth::TZ);
