<?php

namespace App\Contracts;

interface PdfRenderer
{
    public function fromHtml(string $html): string;
}
