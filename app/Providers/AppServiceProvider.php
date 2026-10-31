<?php

namespace App\Providers;

use App\Contracts\PdfRenderer;
use App\Services\BrowsershotPdfRenderer;
use App\Services\FakePdfRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Per-IP limit for "Esqueceu a senha?". Per-e-mail limit lives in
        // TemporaryPasswordService (silent, so it never reveals accounts).
        RateLimiter::for('forgot-password', function (Request $request) {
            return [
                Limit::perMinute(5)->by('fp-ip-min:'.$request->ip()),
                Limit::perHour(20)->by('fp-ip-hour:'.$request->ip()),
            ];
        });

        Password::defaults(function () {
            return Password::min(10)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });
    }
}
