<?php

namespace Tests\Feature\Mcp;

use App\Models\McpOAuthUser;
use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Conector MCP: descoberta OAuth, registro dinâmico (só redirects do Claude),
 * PKCE S256, login + consentimento, token, revalidação a cada chamada e
 * a tela de Integrações (listar e revogar).
 */
class McpOAuthTest extends TestCase
{
    use McpFixtures, RefreshDatabase;

    private const CLAUDE = 'https://claude.ai/api/mcp/auth_callback';

    private const MCP_HEADERS = ['Accept' => 'application/json, text/event-stream'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMcpFixtures();
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    private int $ip = 1;

    private function register(array $uris = [self::CLAUDE]): TestResponse
    {
        // IP diferente por chamada: o registro tem limite por IP (testado à parte).
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$this->ip++])->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => $uris]);
    }

    /** @return array{0: string, 1: string} [verifier, challenge] */
    private function pkce(): array
    {
        $verifier = Str::random(64);

        return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
    }

    private function authorizeUrl(string $clientId, string $challenge, array $extra = []): string
    {
        return '/oauth/authorize?'.http_build_query(array_merge([
            'response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => self::CLAUDE,
            'scope' => 'mcp:read', 'state' => 'xyz', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ], $extra));
    }

    private function mcp(string $token, string $method, array $params = [], int $id = 1): TestResponse
    {
        // Cada requisição real chega sem usuário em memória.
        Auth::forgetGuards();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token] + self::MCP_HEADERS)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) $params]);
        $this->flushHeaders();

        return $response;
    }

    /** Fluxo completo do Claude; devolve [access, refresh, clientId]. */
    private function connect(User $user, string $password = 'Senha-Forte-123!'): array
    {
        $clientId = $this->register()->assertCreated()->json('client_id');
        [$verifier, $challenge] = $this->pkce();
        $url = $this->authorizeUrl($clientId, $challenge);

        // Sem sessão do HOF Pay → tela de login; depois volta para o consentimento.
        // (No teste a sessão do app pode sobrar de um connect() anterior.)
        $first = $this->get($url);
        if ($first->isRedirect()) {
            $first->assertRedirect(route('login'));
            $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirectContains('/oauth/authorize?');
        }

        $consent = $this->get($url)->assertOk()->assertSee($user->clinic->name)->assertSee('Permitir acesso')->assertSee('claude.ai');
        preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $m);

        $approve = $this->post('/oauth/authorize', ['state' => '', 'client_id' => $clientId, 'auth_token' => $m[1]]);
        $approve->assertStatus(302); // O Claude recusa 307.
        $location = $approve->headers->get('Location');
        $this->assertStringStartsWith(self::CLAUDE.'?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);
        $this->assertSame('xyz', $q['state']);

        // O Claude troca o código sem a sessão do navegador.
        Auth::forgetGuards();
        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'client_id' => $clientId, 'redirect_uri' => self::CLAUDE,
            'code_verifier' => $verifier, 'code' => $q['code'],
        ])->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame('Bearer', $token->json('token_type'));
        $this->assertLessThanOrEqual(3600, $token->json('expires_in'));

        return [$token->json('access_token'), $token->json('refresh_token'), $clientId];
    }

    public function test_discovery_metadata_advertises_only_read_scope_and_pkce(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('scopes_supported', ['mcp:read']);

        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('token_endpoint_auth_methods_supported', ['none'])
            ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token'])
            ->assertJsonPath('registration_endpoint', url('/oauth/register'));
    }

    public function test_unauthenticated_mcp_call_gets_401_with_resource_metadata(): void
    {
        $r = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], self::MCP_HEADERS);
        $r->assertUnauthorized();
        $this->assertStringContainsString('resource_metadata=', (string) $r->headers->get('WWW-Authenticate'));
        $this->assertStringNotContainsString('Paula', $r->getContent());

        $this->mcp('not-a-token', 'tools/list')->assertUnauthorized();
    }

    public function test_registration_only_accepts_claude_or_loopback_redirects_and_public_clients(): void
    {
        config(['mcp.allow_loopback_redirects' => true]);
        $this->register()->assertCreated()->assertJsonPath('token_endpoint_auth_method', 'none')->assertJsonMissingPath('client_secret');
        $this->register(['https://claude.com/api/mcp/auth_callback'])->assertCreated();
        $this->register(['http://localhost:6274/oauth/callback'])->assertCreated();
        $this->register(['http://127.0.0.1:33418/callback'])->assertCreated();

        foreach ([
            'https://evil.example/api/mcp/auth_callback',
            'https://claude.ai.evil.example/api/mcp/auth_callback',
            'https://claude.ai/api/mcp/auth_callback/../../x',
            'https://claude.ai/other',
            'http://claude.ai/api/mcp/auth_callback',
            'http://localhost/callback',
            'http://evil.example:8080/callback',
            'https://user@claude.ai/api/mcp/auth_callback',
            'javascript:alert(1)',
            'myapp://callback',
        ] as $uri) {
            $this->register([self::CLAUDE, $uri])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
        }

        $this->postJson('/oauth/register', ['redirect_uris' => [self::CLAUDE], 'token_endpoint_auth_method' => 'client_secret_basic'])
            ->assertStatus(400);

        config(['mcp.allow_loopback_redirects' => false]);
        $this->register(['http://localhost:6274/oauth/callback'])->assertStatus(400);
    }

    public function test_loopback_is_off_by_default_and_warned_on_consent(): void
    {
        $this->register(['http://localhost:6274/oauth/callback'])->assertStatus(400);

        config(['mcp.allow_loopback_redirects' => true]);
        $loopback = 'http://localhost:6274/oauth/callback';
        $clientId = $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->postJson('/oauth/register', ['client_name' => 'Claude Oficial', 'redirect_uris' => [$loopback]])->json('client_id');
        [, $challenge] = $this->pkce();
        $url = $this->authorizeUrl($clientId, $challenge, ['redirect_uri' => $loopback]);

        $this->actingAs($this->adminA, 'web');
        $this->get($url)->assertOk()
            ->assertSee('um aplicativo neste computador')
            ->assertSee('não para o site do Claude')
            ->assertDontSee('Permitir que Claude Oficial');

        // Desligou o loopback depois: o cliente antigo não autoriza mais.
        config(['mcp.allow_loopback_redirects' => false]);
        $this->get($url)->assertStatus(400)->assertSee('retorno não permitido');
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/oauth/register', ['redirect_uris' => [self::CLAUDE]])->assertCreated();
        }
        $this->postJson('/oauth/register', ['redirect_uris' => [self::CLAUDE]])->assertStatus(429);
    }

    public function test_authorize_requires_pkce_s256_and_read_scope(): void
    {
        $clientId = $this->register()->json('client_id');
        [, $challenge] = $this->pkce();

        $this->get($this->authorizeUrl($clientId, $challenge))->assertRedirect(route('login'));
        $this->get($this->authorizeUrl($clientId, ''))->assertStatus(400)->assertSee('PKCE');
        $this->get($this->authorizeUrl($clientId, $challenge, ['code_challenge_method' => 'plain']))->assertStatus(400)->assertSee('S256');
        $this->get($this->authorizeUrl($clientId, $challenge, ['scope' => 'mcp:read mcp:write']))->assertStatus(400);
        $this->get($this->authorizeUrl($clientId, $challenge, ['scope' => '*']))->assertStatus(400);
        $this->get($this->authorizeUrl($clientId, $challenge, ['response_type' => 'token']))->assertStatus(400);

        // Redirect fora da lista: recusado antes do Passport.
        $this->actingAs($this->adminA, 'web');
        $this->get($this->authorizeUrl($clientId, $challenge, ['redirect_uri' => 'https://evil.example/cb']))->assertStatus(400);
        // Permitido na lista mas diferente do registrado pelo cliente: Passport recusa.
        $this->get($this->authorizeUrl($clientId, $challenge, ['redirect_uri' => 'https://claude.com/api/mcp/auth_callback']))->assertStatus(401);
    }

    public function test_full_claude_flow_then_tools_work(): void
    {
        [$access, $refresh, $clientId] = $this->connect($this->adminA);

        $this->mcp($access, 'initialize', [
            'protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'teste', 'version' => '1'],
        ])->assertOk()->assertJsonPath('result.serverInfo.name', 'HOF Pay');

        $this->assertCount(11, $this->mcp($access, 'tools/list')->assertOk()->json('result.tools'));
        $text = $this->mcp($access, 'tools/call', ['name' => 'buscar_pacientes', 'arguments' => (object) []])->json('result.content.0.text');
        $this->assertStringContainsString('Paula Alfa', $text);
        $this->assertStringNotContainsString('Paula Beta', $text);

        // Token não fica em texto puro no banco (JWT assinado; banco guarda só o id).
        $this->assertDatabaseMissing('oauth_access_tokens', ['id' => $access]);
        $this->assertSame(0, \DB::table('oauth_refresh_tokens')->where('id', $refresh)->count());

        // Refresh de outro client é recusado; do mesmo client gera token novo, ainda só mcp:read.
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $this->register()->json('client_id')])
            ->assertStatus(400);
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $clientId, 'scope' => 'mcp:read mcp:write'])
            ->assertStatus(400);
        $renewed = $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $clientId])->assertOk();
        $this->mcp($renewed->json('access_token'), 'tools/list')->assertOk();
        $this->assertSame(['mcp:read'], \DB::table('oauth_access_tokens')->latest('created_at')->get()->map(fn ($t) => json_decode($t->scopes))->unique()->flatten()->all());
    }

    public function test_wrong_pkce_verifier_or_reused_code_is_refused(): void
    {
        $clientId = $this->register()->json('client_id');
        [$verifier, $challenge] = $this->pkce();
        $url = $this->authorizeUrl($clientId, $challenge);
        $this->get($url);
        $this->post('/login', ['email' => $this->adminA->email, 'password' => 'Senha-Forte-123!']);
        preg_match('/name="auth_token" value="([^"]+)"/', $this->get($url)->getContent(), $m);
        parse_str((string) parse_url($this->post('/oauth/authorize', ['state' => '', 'client_id' => $clientId, 'auth_token' => $m[1]])->headers->get('Location'), PHP_URL_QUERY), $q);

        $body = ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'redirect_uri' => self::CLAUDE, 'code' => $q['code']];
        $this->postJson('/oauth/token', $body + ['code_verifier' => Str::random(64)])->assertStatus(400);
        $this->postJson('/oauth/token', $body)->assertStatus(400);
        $this->postJson('/oauth/token', $body + ['code_verifier' => $verifier])->assertOk();
        $this->postJson('/oauth/token', $body + ['code_verifier' => $verifier])->assertStatus(400);
    }

    public function test_only_admins_can_connect(): void
    {
        $this->receptionist->forceFill(['password' => bcrypt('Senha-Forte-123!')])->save();
        $clientId = $this->register()->json('client_id');
        [, $challenge] = $this->pkce();
        $this->get($this->authorizeUrl($clientId, $challenge));

        $this->post('/login', ['email' => $this->receptionist->email, 'password' => 'Senha-Forte-123!'])
            ->assertSee('Só administradores');
        $this->assertGuest('web');

        // Mesmo com sessão, a tela de consentimento recusa.
        $this->actingAs($this->receptionist, 'web');
        $this->get($this->authorizeUrl($clientId, $challenge))->assertSee('Só administradores')->assertDontSee('Permitir acesso');
    }

    public function test_wrong_password_is_rate_limited_and_does_not_leak(): void
    {
        $this->post('/login', ['email' => 'ninguem@x.test', 'password' => 'errada'])->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $this->adminA->email, 'password' => 'errada'])->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        }
        $this->post('/login', ['email' => $this->adminA->email, 'password' => 'Senha-Forte-123!'])->assertSessionHasErrors(['email' => 'Muitas tentativas. Tente de novo em alguns minutos.']);
        $this->assertGuest('web');
    }

    public function test_access_is_rechecked_on_every_call(): void
    {
        [$access] = $this->connect($this->adminA);
        $this->mcp($access, 'tools/list')->assertOk();

        // Perdeu a permissão → 401 na hora e tokens revogados.
        $this->adminA->syncRoles(['receptionist']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->mcp($access, 'tools/list')->assertUnauthorized()->assertJsonPath('error', 'invalid_token');
        $this->assertSame(0, \DB::table('oauth_access_tokens')->where('user_id', $this->adminA->id)->where('revoked', false)->count());

        // Voltou a ser admin: o token antigo continua morto.
        $this->adminA->syncRoles(['admin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->mcp($access, 'tools/list')->assertUnauthorized();
    }

    public function test_deactivated_user_or_inactive_clinic_get_401(): void
    {
        [$access] = $this->connect($this->adminA);
        $this->adminA->forceFill(['is_active' => false])->save();
        $this->mcp($access, 'tools/list')->assertUnauthorized();

        $this->adminA->forceFill(['is_active' => true])->save();
        [$access] = $this->connect($this->adminA);
        $this->clinicA->forceFill(['is_active' => false])->save();
        $this->mcp($access, 'tools/list')->assertUnauthorized();
    }

    public function test_token_without_read_scope_is_refused(): void
    {
        Passport::actingAs(McpOAuthUser::query()->findOrFail($this->adminA->id), []);
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], self::MCP_HEADERS)
            ->assertUnauthorized()->assertJsonPath('error', 'insufficient_scope');
    }

    public function test_sanctum_app_token_cannot_reach_mcp(): void
    {
        $token = $this->adminA->createToken('app')->plainTextToken;
        $this->mcp($token, 'tools/list')->assertUnauthorized();
    }

    public function test_mcp_calls_are_rate_limited_per_token(): void
    {
        config(['mcp.rate_limit_per_token' => 3]);
        [$access] = $this->connect($this->adminA);
        for ($i = 0; $i < 3; $i++) {
            $this->mcp($access, 'tools/list')->assertOk();
        }
        $this->mcp($access, 'tools/list')->assertStatus(429);
    }

    public function test_integrations_page_lists_and_revokes_connections(): void
    {
        [$access, , $clientId] = $this->connect($this->adminA);
        $this->mcp($access, 'tools/call', ['name' => 'estoque_baixo', 'arguments' => (object) []])->assertOk();

        Sanctum::actingAs($this->adminA);
        $card = $this->getJson('/api/v1/integrations')->assertOk()->json('data.0');
        $this->assertSame('claude_mcp', $card['key']);
        $this->assertSame('connected', $card['status']);
        $this->assertTrue($card['read_only']);
        $this->assertSame(url('/mcp'), $card['server_url']);
        $this->assertCount(1, $card['connections']);
        $this->assertSame('Ana Admin', $card['connections'][0]['user_name']);
        $this->assertSame(1, $card['connections'][0]['calls']);
        $this->assertSame('estoque_baixo', $card['recent_calls'][0]['tool']);
        $this->assertStringNotContainsString($access, json_encode($card));

        // Outra clínica não vê nem revoga.
        Sanctum::actingAs($this->adminB);
        $this->assertSame('not_connected', $this->getJson('/api/v1/integrations')->json('data.0.status'));
        $this->deleteJson("/api/v1/integrations/claude/connections/{$this->adminA->id}/{$clientId}")->assertNotFound();

        // Sem permissão: 403.
        Sanctum::actingAs($this->receptionist);
        $this->getJson('/api/v1/integrations')->assertForbidden();
        $this->deleteJson("/api/v1/integrations/claude/connections/{$this->adminA->id}/{$clientId}")->assertForbidden();

        // Admin revoga → próxima chamada do Claude = 401.
        Sanctum::actingAs($this->adminA);
        $this->deleteJson("/api/v1/integrations/claude/connections/{$this->adminA->id}/{$clientId}")->assertOk();
        $this->assertSame('not_connected', $this->getJson('/api/v1/integrations')->json('data.0.status'));
        $this->mcp($access, 'tools/list')->assertUnauthorized();
    }
}
