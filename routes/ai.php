<?php

use App\Http\Controllers\Mcp\McpLoginController;
use App\Http\Controllers\Mcp\McpOAuthController;
use App\Http\Middleware\EnforceMcpOAuthRequest;
use App\Http\Middleware\EnsureMcpUser;
use App\Mcp\Servers\HofPayServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
| Conector MCP do HOF Pay para o Claude — SÓ LEITURA.
|
| POST /mcp  (streamable HTTP)  → HofPayServer, com token OAuth (Passport, escopo mcp:read).
| /.well-known/*                → descoberta OAuth (RFC 9728 / RFC 8414).
| POST /oauth/register          → registro dinâmico de cliente (RFC 7591), só redirects do Claude.
| /login                        → login do HOF Pay para aprovar o acesso (sessão web).
| /oauth/authorize, /oauth/token → Passport (PKCE S256 obrigatório, ver EnforceMcpOAuthRequest).
*/

Route::get('/.well-known/oauth-protected-resource', [McpOAuthController::class, 'protectedResource'])
    ->name('mcp.oauth.protected-resource');
Route::get('/.well-known/oauth-protected-resource/{path}', [McpOAuthController::class, 'protectedResource'])
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource.nested');
Route::get('/.well-known/oauth-authorization-server', [McpOAuthController::class, 'authorizationServer'])
    ->name('mcp.oauth.authorization-server');
Route::get('/.well-known/oauth-authorization-server/{path}', [McpOAuthController::class, 'authorizationServer'])
    ->where('path', '.*')
    ->name('mcp.oauth.authorization-server.nested');

Route::post('/oauth/register', [McpOAuthController::class, 'register'])
    ->middleware('throttle:mcp-register')
    ->name('mcp.oauth.register');

// Passport, só o necessário para authorization_code + refresh_token (PKCE S256).
Route::prefix('oauth')->as('passport.')->middleware(EnforceMcpOAuthRequest::class)->group(function (): void {
    Route::post('token', [AccessTokenController::class, 'issueToken'])
        ->middleware('throttle:mcp-token')
        ->name('token');
    Route::get('authorize', [AuthorizationController::class, 'authorize'])
        ->middleware('web')
        ->name('authorizations.authorize');
    Route::middleware(['web', 'auth:web'])->group(function (): void {
        Route::post('authorize', [ApproveAuthorizationController::class, 'approve'])->name('authorizations.approve');
        Route::delete('authorize', [DenyAuthorizationController::class, 'deny'])->name('authorizations.deny');
    });
});

Route::middleware('web')->group(function (): void {
    Route::get('/login', [McpLoginController::class, 'show'])->name('login');
    Route::post('/login', [McpLoginController::class, 'login'])
        ->middleware('throttle:mcp-login')
        ->name('mcp.login.submit');
});

Mcp::web('/mcp', HofPayServer::class)
    ->middleware(['auth:api', EnsureMcpUser::class, 'throttle:mcp'])
    ->name('mcp.server');
