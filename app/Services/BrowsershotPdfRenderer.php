<?php

namespace App\Services;

use App\Contracts\PdfRenderer;
use App\Exceptions\PdfRenderException;
use App\Support\BrowsershotBinaries;
use Spatie\Browsershot\Browsershot;
use Throwable;

class BrowsershotPdfRenderer implements PdfRenderer
{
    public function fromHtml(string $html): string
    {
        $chromePath = BrowsershotBinaries::resolveChromePath();
        if ($chromePath === null) {
            throw PdfRenderException::chromeMissing(BrowsershotBinaries::chromeCandidates());
        }

        $nodeBinary = BrowsershotBinaries::resolveNodeBinary();
        if ($nodeBinary === null) {
            throw PdfRenderException::nodeMissing(BrowsershotBinaries::nodeCandidates());
        }

        try {
            $shot = Browsershot::html($html)
                ->format('A4')
                ->margins(12, 12, 12, 12)
                ->showBackground()
                ->timeout(config('browsershot.timeout', 60))
                ->noSandbox()
                ->addChromiumArguments([
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--font-render-hinting=none',
                ])
                ->setChromePath($chromePath)
                ->setNodeBinary($nodeBinary);

            $npmBinary = BrowsershotBinaries::resolveNpmBinary();
            if ($npmBinary !== null) {
                $shot->setNpmBinary($npmBinary);
            }

            $nodeModules = BrowsershotBinaries::resolveNodeModulePath();
            if ($nodeModules !== null && method_exists($shot, 'setNodeModulePath')) {
                $shot->setNodeModulePath($nodeModules);
            }

            $includePath = dirname($nodeBinary).':/usr/local/bin:/usr/bin:/bin';
            if (method_exists($shot, 'setIncludePath')) {
                $shot->setIncludePath($includePath);
            }

            return $shot->pdf();
        } catch (PdfRenderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw PdfRenderException::fromRenderer($exception);
        }
    }
}
