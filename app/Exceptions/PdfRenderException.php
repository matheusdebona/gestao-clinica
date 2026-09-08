<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PdfRenderException extends RuntimeException
{
    public static function chromeMissing(array $triedPaths): self
    {
        $paths = implode(', ', $triedPaths);

        return new self(
            'Não foi possível gerar o PDF: Chromium/Chrome não encontrado'.
            ($paths !== '' ? " (tentou: {$paths})." : '.').
            ' Instale Chromium no servidor e defina BROWSERSHOT_CHROME_PATH. Veja README (PDF / Browsershot).'
        );
    }

    public static function nodeMissing(array $triedPaths): self
    {
        $paths = implode(', ', $triedPaths);

        return new self(
            'Não foi possível gerar o PDF: Node.js não encontrado'.
            ($paths !== '' ? " (tentou: {$paths})." : '.').
            ' Instale Node.js e, se o PHP-FPM não enxergar o binário, defina BROWSERSHOT_NODE_BINARY.'
        );
    }

    public static function fromRenderer(Throwable $previous): self
    {
        $detail = trim($previous->getMessage());
        $suffix = $detail !== '' ? ' Detalhe: '.$detail : '';

        return new self(
            'Não foi possível gerar o PDF do orçamento. Confira Chromium, Node.js e Puppeteer no servidor (php artisan pdf:diagnose).'.$suffix,
            0,
            $previous,
        );
    }
}
