<?php

namespace Tests\Feature\Api\V1;

use App\Models\Clinic;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_returns_token_and_user(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->forClinic($clinic)->create([
            'email' => 'admin@clinica.test',
            'password' => 'password',
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@clinica.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'email', 'permissions', 'clinic'],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@clinica.test',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->forClinic($clinic)->create();
        $user->assignRole('admin');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_revokes_current_token(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->forClinic($clinic)->create([
            'email' => 'logout@clinica.test',
            'password' => 'password',
        ]);
        $user->assignRole('admin');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'logout@clinica.test',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Fresh auth state (guards persist in the same test process).
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
