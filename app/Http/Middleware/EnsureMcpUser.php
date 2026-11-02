<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Mcp\McpLoginController;
use App\Http\Controllers\Mcp\McpOAuthController;
use App\Models\McpOAuthUser;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\McpConnections;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conector MCP: depois do auth:api (token OAuth do Passport), carrega o usuário
 * real (App\Models\User, com papéis e permissões) e prende a requisição à
 * clínica dele. A cada chamada confere de novo: escopo mcp:read, conta ativa,
 * clínica ativa, senha definida e permissão integrations.manage.
 */
class EnsureMcpUser
{
    public const CLIENT_ATTRIBUTE = 'mcp_oauth_client_id';

    public const TOKEN_ATTRIBUTE = 'mcp_oauth_token_id';

    private static function unauthorized(Request $request, string $error, string $message): Response
    {
        return response()->json(['error' => $error, 'message' => $message], 401)->header(
            'WWW-Authenticate',
            'Bearer realm="mcp", error="'.$error.'", resource_metadata="'.route('mcp.oauth.protected-resource.nested', ['path' => $request->path()]).'"',
        );
    }

    public function handle(Request $request, Closure $next): Response
    {
        $oauthUser = Auth::guard('api')->user();

        $user = $oauthUser instanceof McpOAuthUser
            ? User::query()->withoutGlobalScopes()->find($oauthUser->getAuthIdentifier())
            : null;

        $token = $oauthUser?->token();
        if (! $token instanceof AccessToken || ! $token->can(McpOAuthController::SCOPE)) {
            return self::unauthorized($request, 'insufficient_scope', 'Token sem o escopo mcp:read.');
        }

        // Revalida a cada chamada: conta desativada, sem permissão ou clínica inativa
        // derruba o acesso na hora (401) e revoga os tokens dessa conta.
        $denied = $user === null ? 'Conta não encontrada.' : McpLoginController::denialReason($user);
        if ($denied !== null) {
            if ($user !== null) {
                McpConnections::revokeAllFor($user->id);
            }

            return self::unauthorized($request, 'invalid_token', $denied);
        }

        $request->attributes->set(self::CLIENT_ATTRIBUTE, $token->oauth_client_id);
        $request->attributes->set(self::TOKEN_ATTRIBUTE, $token->oauth_access_token_id);

        CurrentClinic::setId($user->clinic_id);
        Auth::guard('api')->setUser($user);
        $request->setUserResolver(fn () => $user);

        try {
            return $next($request);
        } finally {
            CurrentClinic::forget();
        }
    }
}
