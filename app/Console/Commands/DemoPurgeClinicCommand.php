<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Support\CurrentClinic;
use App\Support\Demo\DemoClinicGuard;
use App\Support\Demo\DemoClinicPurger;
use App\Support\Demo\DemoManifest;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Throwable;

class DemoPurgeClinicCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'demo:purge-clinic
        {clinic : ID da clínica}
        {--confirm-name= : Nome exato da clínica (trava de segurança)}
        {--force : Rodar em produção sem pergunta}';

    protected $description = 'Remove somente os dados de demonstração criados por demo:seed-clinic (pelo manifesto).';

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
        if (! DemoManifest::exists((int) $clinic->id)) {
            $this->error('Não há manifesto de demonstração para esta clínica.');

            return self::FAILURE;
        }
        if (! $this->confirmToProceed('Vai apagar os dados de demonstração da clínica "'.$clinic->name.'".')) {
            return self::FAILURE;
        }

        Mail::fake();
        Notification::fake();
        Queue::fake();
        Bus::fake();

        $manifest = DemoManifest::load((int) $clinic->id);
        $before = DemoClinicGuard::fingerprintOthers((int) $clinic->id);
        $purger = new DemoClinicPurger((int) $clinic->id);
        try {
            CurrentClinic::setId((int) $clinic->id);
            DB::transaction(function () use ($purger, $manifest, $before, $clinic): void {
                $purger->purge($manifest);
                DemoClinicGuard::assertUnchanged($before, (int) $clinic->id);
            });
        } catch (Throwable $e) {
            $this->error('Purge abortado e desfeito (rollback): '.$e->getMessage());

            return self::FAILURE;
        } finally {
            CurrentClinic::forget();
        }

        $path = DemoManifest::path((int) $clinic->id);
        rename($path, $path.'.purged-'.now()->format('YmdHis'));
        $this->info('Dados de demonstração removidos da clínica '.$clinic->id.'.');
        foreach ($purger->deleted as $table => $n) {
            $this->line(sprintf('  %-28s %d', $table, $n));
        }

        return self::SUCCESS;
    }
}
