<?php

namespace App\Providers;

use App\Contracts\PdfRenderer;
use App\Http\Controllers\Mcp\McpLoginController;
use App\Http\Controllers\Mcp\McpOAuthController;
use App\Http\Middleware\EnsureMcpUser;
use App\Models\User;
use App\Services\BrowsershotPdfRenderer;
use App\Services\FakePdfRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Rotas OAuth próprias (routes/ai.php): só authorize/approve/deny/token, sem device code.
        Passport::ignoreRoutes();
        Passport::$deviceCodeGrantEnabled = false;

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

        $this->configureMcp();

        Password::defaults(function () {
            return Password::min(10)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });
    }

    /**
     * Conector MCP do Claude (só leitura): OAuth do Passport com escopo único
     * mcp:read, tokens curtos e limites de uso. Ver routes/ai.php.
     */
    private function configureMcp(): void
    {
        Passport::tokensCan([McpOAuthController::SCOPE => 'Ler dados da clínica (só leitura)']);
        Passport::defaultScopes([McpOAuthController::SCOPE]);
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));

        Passport::authorizationView(function (array $parameters) {
            $user = $parameters['user'] ?? null;
            $denied = $user instanceof User ? McpLoginController::denialReason($user) : 'Faça login de novo.';
            if ($denied !== null) {
                return view('mcp.error', ['message' => $denied]);
            }

            return view('mcp.authorize', [...$parameters, 'clinicName' => $user->clinic?->name ?? '—']);
        });

        RateLimiter::for('mcp', function (Request $request) {
            $user = $request->user();

            return [
                Limit::perMinute((int) config('mcp.rate_limit_per_token', 60))
                    ->by('mcp-token:'.($request->attributes->get(EnsureMcpUser::TOKEN_ATTRIBUTE) ?? $request->ip())),
                Limit::perMinute((int) config('mcp.rate_limit_per_user', 120))
                    ->by('mcp-user:'.($user?->getAuthIdentifier() ?? $request->ip())),
            ];
        });

        RateLimiter::for('mcp-register', fn (Request $request) => [
            Limit::perMinute(5)->by('mcp-reg-min:'.$request->ip()),
            Limit::perDay(30)->by('mcp-reg-day:'.$request->ip()),
        ]);

        RateLimiter::for('mcp-token', fn (Request $request) => [
            Limit::perMinute(30)->by('mcp-token-ip:'.$request->ip()),
        ]);

        RateLimiter::for('mcp-login', fn (Request $request) => [
            Limit::perMinute(10)->by('mcp-login-ip:'.$request->ip()),
        ]);
    }
}
