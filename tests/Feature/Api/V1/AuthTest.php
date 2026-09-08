<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campaign;
use App\Models\CardBrand;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultPaymentCatalog;
use App\Support\EnsureDefaultUnitsOfMeasure;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ThrottleRequests::class);
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

    public function test_login_validation_errors_are_in_portuguese(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        $emailError = $response->json('errors.email.0');
        $passwordError = $response->json('errors.password.0');

        $this->assertIsString($emailError);
        $this->assertIsString($passwordError);
        $this->assertStringContainsString('obrigatório', $emailError);
        $this->assertStringContainsString('obrigatório', $passwordError);
        $this->assertStringNotContainsString('validation.required', $emailError);
        $this->assertStringNotContainsString('validation.required', $passwordError);
    }

    public function test_register_validation_errors_are_in_portuguese(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['clinic_name', 'name', 'email', 'password']);

        $clinicNameError = $response->json('errors.clinic_name.0');

        $this->assertIsString($clinicNameError);
        $this->assertStringContainsString('obrigatório', $clinicNameError);
        $this->assertStringNotContainsString('validation.required', $clinicNameError);
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

    public function test_register_creates_clinic_admin_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'clinic_name' => 'Clínica Aurora',
            'name' => 'Ana Costa',
            'email' => 'ana@aurora.test',
            'password' => 'ChangeMe!123',
            'password_confirmation' => 'ChangeMe!123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'email', 'permissions', 'clinic'],
            ])
            ->assertJsonPath('user.email', 'ana@aurora.test')
            ->assertJsonPath('user.clinic.name', 'Clínica Aurora');

        $this->assertDatabaseHas('clinics', ['name' => 'Clínica Aurora']);
        $this->assertDatabaseHas('users', ['email' => 'ana@aurora.test']);

        $user = User::query()->where('email', 'ana@aurora.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_register_seeds_default_client_origins(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'clinic_name' => 'Clínica Origens',
            'name' => 'Carla Dias',
            'email' => 'carla@origens.test',
            'password' => 'ChangeMe!123',
            'password_confirmation' => 'ChangeMe!123',
        ])->assertCreated();

        $clinic = Clinic::query()->where('name', 'Clínica Origens')->first();
        $this->assertNotNull($clinic);

        $names = ClientOrigin::query()
            ->where('clinic_id', $clinic->id)
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(EnsureDefaultClientOrigins::NAMES, $names);
        $this->assertTrue(
            ClientOrigin::query()
                ->where('clinic_id', $clinic->id)
                ->where('is_active', false)
                ->doesntExist()
        );
        $this->assertSame(0, Campaign::query()->where('clinic_id', $clinic->id)->count());
    }

    public function test_register_seeds_default_payment_catalog(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'clinic_name' => 'Clínica Pagamentos',
            'name' => 'Eva Nunes',
            'email' => 'eva@pagamentos.test',
            'password' => 'ChangeMe!123',
            'password_confirmation' => 'ChangeMe!123',
        ])->assertCreated();

        $clinic = Clinic::query()->where('name', 'Clínica Pagamentos')->first();
        $this->assertNotNull($clinic);

        $methodCodes = PaymentMethod::query()
            ->where('clinic_id', $clinic->id)
            ->pluck('code')
            ->all();
        $brandCodes = CardBrand::query()
            ->where('clinic_id', $clinic->id)
            ->pluck('code')
            ->all();

        $this->assertEqualsCanonicalizing(
            array_column(EnsureDefaultPaymentCatalog::METHODS, 'code'),
            $methodCodes
        );
        $this->assertEqualsCanonicalizing(
            array_column(EnsureDefaultPaymentCatalog::BRANDS, 'code'),
            $brandCodes
        );
        $this->assertTrue(
            PaymentMethod::query()
                ->where('clinic_id', $clinic->id)
                ->where('is_active', false)
                ->doesntExist()
        );
        $this->assertTrue(
            CardBrand::query()
                ->where('clinic_id', $clinic->id)
                ->where('is_active', false)
                ->doesntExist()
        );
        $this->assertDatabaseHas('payment_methods', [
            'clinic_id' => $clinic->id,
            'code' => 'cartao_credito',
            'requires_card_meta' => true,
            'kind' => PaymentMethod::KIND_CREDIT_CARD,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'clinic_id' => $clinic->id,
            'code' => 'cartao_debito',
            'requires_card_meta' => true,
        ]);
    }

    public function test_register_seeds_default_units_of_measure(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'clinic_name' => 'Clínica Unidades',
            'name' => 'Lia Costa',
            'email' => 'lia@unidades.test',
            'password' => 'ChangeMe!123',
            'password_confirmation' => 'ChangeMe!123',
        ])->assertCreated();

        $clinic = Clinic::query()->where('name', 'Clínica Unidades')->first();
        $this->assertNotNull($clinic);

        $symbols = UnitOfMeasure::query()
            ->where('clinic_id', $clinic->id)
            ->pluck('symbol')
            ->all();

        $this->assertEqualsCanonicalizing(
            array_column(EnsureDefaultUnitsOfMeasure::UNITS, 'symbol'),
            $symbols
        );
        $this->assertTrue(
            UnitOfMeasure::query()
                ->where('clinic_id', $clinic->id)
                ->where('is_active', false)
                ->doesntExist()
        );
        $this->assertDatabaseHas('units_of_measure', [
            'clinic_id' => $clinic->id,
            'symbol' => 'un',
            'name' => 'Unidade',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('units_of_measure', [
            'clinic_id' => $clinic->id,
            'symbol' => 'seringa',
            'name' => 'Seringa',
        ]);
        $this->assertSame(
            count(EnsureDefaultUnitsOfMeasure::UNITS),
            UnitOfMeasure::query()->where('clinic_id', $clinic->id)->count()
        );
    }

    public function test_register_bootstraps_roles_when_catalog_is_missing(): void
    {
        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('role_has_permissions')->delete();
        Role::query()->delete();
        Permission::query()->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $response = $this->postJson('/api/v1/auth/register', [
            'clinic_name' => 'Clínica Sem Seed',
            'name' => 'Bruno Lima',
            'email' => 'bruno@semseed.test',
            'password' => 'ChangeMe!123',
            'password_confirmation' => 'ChangeMe!123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'bruno@semseed.test');

        $user = User::query()->where('email', 'bruno@semseed.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertDatabaseHas('roles', ['name' => 'receptionist', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'seller', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'stock', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'professional', 'guard_name' => 'web']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $clinic = Clinic::factory()->create();
        User::factory()->forClinic($clinic)->create(['email' => 'taken@clinica.test']);

        $this->postJson('/api/v1/auth/register', [
            'clinic_name' => 'Outra Clínica',
            'name' => 'Outra Pessoa',
            'email' => 'taken@clinica.test',
            'password' => 'ChangeMe!123',
            'password_confirmation' => 'ChangeMe!123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_weak_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'clinic_name' => 'Clínica Fraca',
            'name' => 'Ana',
            'email' => 'ana@fraca.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
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
