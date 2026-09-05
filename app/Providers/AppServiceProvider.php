<?php

namespace App\Providers;

use App\Contracts\PdfRenderer;
use App\Services\BrowsershotPdfRenderer;
use App\Services\FakePdfRenderer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PdfRenderer::class, function ($app) {
            if ($app->environment('testing')) {
                return $app->make(FakePdfRenderer::class);
            }

            return $app->make(BrowsershotPdfRenderer::class);
        });
    }

    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(10)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });
    }
}
