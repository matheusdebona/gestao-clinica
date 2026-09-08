<?php

namespace App\Console\Commands;

use App\Support\BrowsershotBinaries;
use Illuminate\Console\Command;

class DiagnosePdfCommand extends Command
{
    protected $signature = 'pdf:diagnose';

    protected $description = 'Check Chromium/Node binaries used by Browsershot budget PDFs.';

    public function handle(): int
    {
        $this->info('HOF Pay — diagnóstico de PDF (Browsershot)');
        $this->newLine();

        $chrome = BrowsershotBinaries::resolveChromePath();
        $node = BrowsershotBinaries::resolveNodeBinary();
        $npm = BrowsershotBinaries::resolveNpmBinary();
        $modules = BrowsershotBinaries::resolveNodeModulePath();

        $this->line('Chrome/Chromium: '.($chrome ?? 'NÃO ENCONTRADO'));
        $this->line('  candidatos: '.implode(', ', BrowsershotBinaries::chromeCandidates()));
        $this->line('Node: '.($node ?? 'NÃO ENCONTRADO'));
        $this->line('  candidatos: '.implode(', ', BrowsershotBinaries::nodeCandidates()));
        $this->line('npm: '.($npm ?? 'não encontrado (opcional se o Node já resolve puppeteer)'));
        $this->line('node_modules: '.($modules ?? 'NÃO ENCONTRADO'));
        $this->line('  candidatos: '.implode(', ', BrowsershotBinaries::nodeModuleCandidates()));
        $this->newLine();

        $ok = $chrome !== null && $node !== null;
        if (! $ok) {
            $this->error('PDF de orçamento vai falhar neste host até instalar Chromium + Node (e Puppeteer).');
            $this->line('Passos: README.md seção "PDF de orçamento (Browsershot)" e `docs/hofpay-architecture.md`.');

            return self::FAILURE;
        }

        $this->info('Binários mínimos encontrados. Gere um orçamento PDF pela API para validar o Chromium de ponta a ponta.');

        return self::SUCCESS;
    }
}
