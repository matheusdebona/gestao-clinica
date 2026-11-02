<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Support\CurrentClinic;
use App\Support\Demo\DemoClinicGuard;
use App\Support\Demo\DemoClinicSeeder;
use App\Support\Demo\DemoManifest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Popula UMA clínica com dados de demonstração (leads, pacientes, agenda, vendas, estoque, despesas)
 * usando os services reais. Nada sai: e-mail, notificações e filas ficam falsos durante o seed.
 */
class DemoSeedClinicCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'demo:seed-clinic
        {clinic : ID da clínica que vai receber os dados}
        {--confirm-name= : Nome exato da clínica (trava de segurança)}
        {--from=2026-09 : Primeiro mês (AAAA-MM, horário de São Paulo)}
        {--to=2026-11 : Último mês (AAAA-MM)}
        {--today= : "Agora" simulado (AAAA-MM-DD HH:MM, São Paulo); padrão = agora}
        {--seed=6 : Semente do sorteio (mesma semente = mesmos dados)}
        {--scale=1 : Multiplicador de volume}
        {--force : Rodar em produção sem pergunta}';

    protected $description = 'Popula uma clínica com dados de demonstração rastreados (desfazer com demo:purge-clinic).';

    public function handle(): int
    {
        $clinic = Clinic::query()->find((int) $this->argument('clinic'));
        if ($clinic === null) {
            $this->error('Clínica não encontrada.');

            return self::FAILURE;
        }
        if ((string) $this->option('confirm-name') !== (string) $clinic->name) {
            $this->error('O --confirm-name não bate com o nome da clínica '.$clinic->id.'. Nada foi feito.');

            return self::FAILURE;
        }
        if (DemoManifest::exists((int) $clinic->id)) {
            $this->error('Esta clínica já tem dados de demonstração (manifesto existe). Rode demo:purge-clinic antes.');

            return self::FAILURE;
        }
        if (! $this->confirmToProceed('Vai gravar dados de demonstração na clínica "'.$clinic->name.'".')) {
            return self::FAILURE;
        }

        $tz = 'America/Sao_Paulo';
        $from = CarbonImmutable::parse($this->option('from').'-01', $tz)->startOfMonth();
        $to = CarbonImmutable::parse($this->option('to').'-01', $tz)->endOfMonth();
        $now = $this->option('today')
            ? CarbonImmutable::parse((string) $this->option('today'), $tz)->utc()
            : CarbonImmutable::now()->utc();
        $scale = max(0.05, (float) $this->option('scale'));
        $seed = (int) $this->option('seed');
        if ($to->lt($from) || $now->lt($from)) {
            $this->error('Período inválido.');

            return self::FAILURE;
        }

        // Nada sai durante o seed: e-mail, notificações e jobs ficam presos nos fakes.
        $mail = Mail::fake();
        $notifications = Notification::fake();
        $queue = Queue::fake();
        Bus::fake();

        $before = DemoClinicGuard::fingerprintOthers((int) $clinic->id);
        $manifest = new DemoManifest;
        $seeder = new DemoClinicSeeder($clinic, $from, $to, $now, $scale, $seed);

        try {
            DB::transaction(function () use ($manifest, $seeder, $before, $clinic): void {
                $manifest->record();
                try {
                    $seeder->run();
                } finally {
                    $manifest->stop();
                    Carbon::setTestNow();
                }
                $this->assertOwnership($manifest, (int) $clinic->id);
                DemoClinicGuard::assertUnchanged($before, (int) $clinic->id);
            });
        } catch (Throwable $e) {
            CurrentClinic::forget();
            $this->error('Seed abortado e desfeito (rollback): '.$e->getMessage());

            return self::FAILURE;
        }
        CurrentClinic::forget();

        $months = [];
        for ($m = $from; $m->lte($to); $m = $m->addMonthNoOverflow()) {
            $months[] = $m->format('Y-m');
        }
        $path = $manifest->save((int) $clinic->id, [
            'clinic_name' => $clinic->name,
            'months' => $months,
            'now' => $now->toIso8601String(),
            'seed' => $seed,
            'scale' => $scale,
            'created_at' => CarbonImmutable::now()->toIso8601String(),
            'preexisting_products' => $seeder->preexistingProducts,
            'stats' => $seeder->stats,
        ]);

        DemoClinicGuard::assertUnchanged($before, (int) $clinic->id);
        $this->info('Dados de demonstração criados na clínica '.$clinic->id.'. Manifesto: '.$path);
        foreach ($manifest->created as $table => $ids) {
            $this->line(sprintf('  %-28s %d', $table, count(array_unique($ids))));
        }
        $this->line('Estatísticas: '.json_encode($seeder->stats, JSON_UNESCAPED_UNICODE));
        $held = 0;
        $sent = $notifications->sentNotifications();
        array_walk_recursive($sent, function () use (&$held): void {
            $held++;
        });
        $this->line(sprintf(
            'Saídas retidas pelos fakes (nada foi enviado): e-mails %d, notificações %d, jobs %d.',
            $mail->sent(Mailable::class)->count() + $mail->queued(Mailable::class)->count(),
            $held,
            count($queue->pushedJobs()),
        ));

        return self::SUCCESS;
    }

    /** Todo registro criado com clinic_id precisa ser da clínica alvo. */
    private function assertOwnership(DemoManifest $manifest, int $clinicId): void
    {
        foreach ($manifest->created as $table => $ids) {
            if (! Schema::hasColumn($table, 'clinic_id')) {
                continue;
            }
            foreach (array_chunk(array_unique($ids), 500) as $chunk) {
                $foreign = DB::table($table)->whereIn('id', $chunk)
                    ->where(fn ($q) => $q->where('clinic_id', '!=', $clinicId)->orWhereNull('clinic_id'))->count();
                if ($foreign > 0) {
                    throw new \RuntimeException("{$foreign} registro(s) em {$table} fora da clínica {$clinicId}.");
                }
            }
        }
    }
}
