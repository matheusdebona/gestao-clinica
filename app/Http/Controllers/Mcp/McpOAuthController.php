<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\ClientRepository;
use Throwable;

/**
 * Descoberta OAuth (RFC 9728 / RFC 8414) e registro dinâmico de clientes (RFC 7591)
 * para o conector do Claude. Substitui o Mcp::oauthRoutes() do laravel/mcp para:
 *  - anunciar só o escopo mcp:read;
 *  - aceitar só clientes públicos com PKCE S256;
 *  - aceitar só redirects do Claude (claude.ai / claude.com) e, se ligado, de
 *    loopback (http://localhost / 127.0.0.1, usados pelo Claude Desktop/Code).
 */
class McpOAuthController extends Controller
{
    public const SCOPE = 'mcp:read';

    public function protectedResource(Request $request, string $path = ''): JsonResponse
    {
        return $this->json([
            'resource' => url('/'.ltrim($path, '/')),
            'authorization_servers' => [url('/')],
            'scopes_supported' => [self::SCOPE],
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'HOF Pay (só leitura)',
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        return $this->json([
            'issuer' => url('/'),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => route('mcp.oauth.register'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => [self::SCOPE],
        ]);
    }

    public function register(Request $request, ClientRepository $clients): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_name' => ['nullable', 'string', 'max:120'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:5'],
            'redirect_uris.*' => ['required', 'string', 'max:512'],
            'token_endpoint_auth_method' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->json(['error' => 'invalid_client_metadata', 'error_description' => 'Dados de registro inválidos.'], 400);
        }

        $uris = array_values(array_unique($request->input('redirect_uris')));
        foreach ($uris as $uri) {
            if (! self::allowedRedirect((string) $uri)) {
                return $this->json(['error' => 'invalid_redirect_uri', 'error_description' => 'Endereço de retorno não permitido.'], 400);
            }
        }

        $method = $request->input('token_endpoint_auth_method');
        if ($method !== null && $method !== 'none') {
            return $this->json(['error' => 'invalid_client_metadata', 'error_description' => 'Só clientes públicos com PKCE.'], 400);
        }

        $name = trim(strip_tags((string) $request->input('client_name', ''))) ?: (parse_url($uris[0], PHP_URL_HOST) ?: 'Cliente MCP');

        try {
            $client = $clients->createAuthorizationCodeGrantClient(
                name: mb_substr($name, 0, 120),
                redirectUris: $uris,
                confidential: false,
            );
        } catch (Throwable $e) {
            report($e);

            return $this->json(['error' => 'server_error', 'error_description' => 'Não foi possível registrar.'], 500);
        }

        return $this->json([
            'client_id' => (string) $client->id,
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => self::SCOPE,
        ], 201);
    }

    /** Redirects aceitos: os do Claude (config mcp.allowed_redirect_uris) e loopback http, se ligado. */
    public static function allowedRedirect(string $uri): bool
    {
        $parts = parse_url($uri);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        foreach ((array) config('mcp.allowed_redirect_uris', []) as $allowed) {
            if (hash_equals((string) $allowed, $uri)) {
                return true;
            }
        }

        if (! config('mcp.allow_loopback_redirects', false)) {
            return false;
        }

        return ($parts['scheme'] ?? null) === 'http'
            && in_array($parts['host'] ?? null, ['localhost', '127.0.0.1', '[::1]'], true)
            && isset($parts['port']);
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'no-store');
    }
}
