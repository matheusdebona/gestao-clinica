<?php

namespace App\Services;

use App\Contracts\PdfRenderer;
use Spatie\Browsershot\Browsershot;

class BrowsershotPdfRenderer implements PdfRenderer
{
    public function fromHtml(string $html): string
    {
        $shot = Browsershot::html($html)
            ->format('A4')
            ->margins(12, 12, 12, 12)
            ->showBackground()
            ->timeout(config('browsershot.timeout', 60))
            ->noSandbox()
            ->addChromiumArguments([
                '--disable-dev-shm-usage',
                '--disable-gpu',
            ]);

        $chromePath = config('browsershot.chrome_path');
        if (is_string($chromePath) && $chromePath !== '') {
            $shot->setChromePath($chromePath);
        }

        $nodeBinary = config('browsershot.node_binary');
        if (is_string($nodeBinary) && $nodeBinary !== '') {
            $shot->setNodeBinary($nodeBinary);
        }

        $npmBinary = config('browsershot.npm_binary');
        if (is_string($npmBinary) && $npmBinary !== '') {
            $shot->setNpmBinary($npmBinary);
        }

        return $shot->pdf();
    }
}
