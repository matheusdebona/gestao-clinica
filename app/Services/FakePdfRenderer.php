<?php

namespace App\Services;

use App\Contracts\PdfRenderer;

/**
 * Lightweight PDF bytes for Feature tests (no Chromium).
 */
class FakePdfRenderer implements PdfRenderer
{
    public function fromHtml(string $html): string
    {
        return "%PDF-1.4\n% Fake PDF for tests\n".md5($html)."\n%%EOF\n";
    }
}
