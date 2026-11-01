<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * O termo do agendamento é "consulta" ("Agendar consulta"), nunca "avaliação".
 * Varre o código do backend (mensagens, notificações, PDFs, modelos de WhatsApp).
 */
class CopyTermsTest extends TestCase
{
    public function test_backend_copy_says_consulta_not_avaliacao(): void
    {
        $root = dirname(__DIR__, 2);
        $hits = [];
        foreach (['app', 'resources', 'routes', 'config', 'lang'] as $dir) {
            if (! is_dir("$root/$dir")) {
                continue;
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! preg_match('/\.(php|blade\.php|json)$/', $file->getFilename())) {
                    continue;
                }
                if (preg_match('/avalia[çc][ãaõo]/iu', (string) file_get_contents($file->getPathname()))) {
                    $hits[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        $this->assertSame([], $hits, 'Use "consulta" em vez de "avaliação".');
    }
}
