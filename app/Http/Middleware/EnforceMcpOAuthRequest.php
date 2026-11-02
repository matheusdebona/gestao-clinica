<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Mcp\McpOAuthController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas OAuth do Passport (/oauth/*): exige PKCE S256 e o escopo mcp:read na
 * autorização, e manda não guardar em cache nenhuma resposta (tokens, códigos).
 */
class EnforceMcpOAuthRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->routeIs('passport.authorizations.authorize')) {
            $error = match (true) {
                $request->query('response_type') !== 'code' => 'Só o fluxo authorization_code é aceito.',
                ! is_string($request->query('code_challenge')) || $request->query('code_challenge') === '' => 'PKCE obrigatório (code_challenge).',
                $request->query('code_challenge_method') !== 'S256' => 'PKCE precisa usar S256.',
                ! self::scopeOk($request->query('scope')) => 'Escopo inválido: use mcp:read.',
                // Mesmo cliente já registrado: o retorno precisa continuar na lista permitida hoje.
                ! McpOAuthController::allowedRedirect((string) $request->query('redirect_uri', '')) => 'Endereço de retorno não permitido.',
                default => null,
            };

            if ($error !== null) {
                return response()->view('mcp.error', ['message' => $error], 400)
                    ->header('Cache-Control', 'no-store');
            }
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private static function scopeOk(mixed $scope): bool
    {
        if ($scope === null || $scope === '') {
            return true; // Passport aplica o escopo padrão (mcp:read).
        }

        $scopes = array_values(array_filter(explode(' ', (string) $scope)));

        return $scopes === [McpOAuthController::SCOPE];
    }
}
