<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TemporaryPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login do fluxo OAuth do Claude (api.hofpay.com.br/login). Sessão só para
 * aprovar o acesso; o app web continua com os tokens do Sanctum.
 */
class McpLoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return $this->continue($request);
        }

        return view('mcp.login');
    }

    public function login(Request $request): RedirectResponse|View
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'email.*' => 'Informe um e-mail válido.',
            'password.*' => 'Informe a senha.',
        ]);

        $key = 'mcp-login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Tente de novo em alguns minutos.']);
        }

        /** @var User|null $user */
        $user = User::query()->withoutGlobalScopes()->where('email', $data['email'])->first();
        // Sem usuário, compara com um hash qualquer: o tempo de resposta não revela se o e-mail existe.
        $valid = Hash::check($data['password'], $user?->password ?: '$2y$12$'.str_repeat('a', 53));
        if (! $user || ! $valid) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }
        RateLimiter::clear($key);

        $denied = self::denialReason($user);
        if ($denied !== null) {
            return view('mcp.error', ['message' => $denied]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->continue($request);
    }

    /** Por que esta conta não pode conectar o Claude (null = pode). */
    public static function denialReason(User $user): ?string
    {
        return match (true) {
            ! $user->is_active => 'Sua conta está inativa.',
            $user->clinic_id === null => 'O conector do Claude funciona só com contas de clínica.',
            ! ($user->clinic?->is_active ?? false) => 'Esta clínica está inativa.',
            (bool) $user->must_change_password || app(TemporaryPasswordService::class)->passwordIsTemporary($user) => 'Entre no app do HOF Pay e defina uma nova senha antes de conectar o Claude.',
            ! $user->can('integrations.manage') => 'Só administradores da clínica podem conectar o Claude.',
            default => null,
        };
    }

    private function continue(Request $request): RedirectResponse|View
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $path = parse_url($intended, PHP_URL_PATH);

        // Só volta para a autorização OAuth (nada de redirecionar para outro lugar).
        if ($intended !== '' && $path === '/oauth/authorize' && parse_url($intended, PHP_URL_HOST) === $request->getHost()) {
            return redirect()->to($intended);
        }

        return view('mcp.done');
    }
}
