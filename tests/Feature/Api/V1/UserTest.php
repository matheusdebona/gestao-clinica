<?php

namespace Tests\Feature\Api\V1;

use App\Models\Clinic;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create([
            'email' => 'admin@clinica.test',
        ]);
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    public function test_admin_can_list_users_of_own_clinic_only(): void
    {
        Sanctum::actingAs($this->admin);

        $otherClinic = Clinic::factory()->create();
        $outsider = User::factory()->forClinic($otherClinic)->create(['name' => 'Outsider']);
        $outsider->assignRole('receptionist');

        $staff = User::factory()->forClinic($this->clinic)->create(['name' => 'Staff Local']);
        $staff->assignRole('seller');

        $response = $this->getJson('/api/v1/users')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->admin->id));
        $this->assertTrue($ids->contains($staff->id));
        $this->assertFalse($ids->contains($outsider->id));
    }

    public function test_admin_can_create_user_with_assignable_role(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Recepcionista',
            'email' => 'recepcao@clinica.test',
            'password' => 'ChangeMe!123',
            'password_confirmation' => 'ChangeMe!123',
            'roles' => ['receptionist'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'recepcao@clinica.test')
            ->assertJsonPath('data.clinic_id', $this->clinic->id)
            ->assertJsonPath('data.roles', ['receptionist']);

        $user = User::query()->where('email', 'recepcao@clinica.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('receptionist'));
        $this->assertTrue($user->can('clients.view'));
    }

    public function test_cannot_assign_admin_role_when_creating_user(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/users', [
            'name' => 'Outro Admin',
            'email' => 'outro@clinica.test',
            'password' => 'ChangeMe!123',
            'password_confirmation' => 'ChangeMe!123',
            'roles' => ['admin'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
    }

    public function test_cannot_pass_direct_permissions_when_creating_user(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Staff',
            'email' => 'staff@clinica.test',
            'password' => 'ChangeMe!123',
            'password_confirmation' => 'ChangeMe!123',
            'roles' => ['stock'],
            'permissions' => ['users.delete'],
        ]);

        $response->assertCreated();
        $user = User::query()->where('email', 'staff@clinica.test')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->hasDirectPermission('users.delete'));
    }

    public function test_cannot_change_roles_of_clinic_admin(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/users/{$this->admin->id}", [
            'roles' => ['seller'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['roles']);

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_cannot_deactivate_clinic_admin(): void
    {
        Sanctum::actingAs($this->admin);

        $staff = User::factory()->forClinic($this->clinic)->create();
        $staff->assignRole('seller');
        Sanctum::actingAs($staff);
        // staff may lack users.delete — use admin again
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/v1/users/{$this->admin->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user']);

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_cannot_deactivate_self(): void
    {
        $staff = User::factory()->forClinic($this->clinic)->create();
        $staff->assignRole('admin');
        // second admin shouldn't exist in product, but for self-deactivate use seller with delete
        $staff->syncRoles(['seller']);
        $staff->givePermissionTo('users.delete');

        Sanctum::actingAs($staff);

        $this->deleteJson("/api/v1/users/{$staff->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
    }

    public function test_user_from_other_clinic_is_not_found(): void
    {
        Sanctum::actingAs($this->admin);

        $otherClinic = Clinic::factory()->create();
        $outsider = User::factory()->forClinic($otherClinic)->create();
        $outsider->assignRole('receptionist');

        $this->getJson("/api/v1/users/{$outsider->id}")->assertNotFound();
    }

    public function test_assignable_roles_excludes_admin(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/roles?assignable=1')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('receptionist'));
        $this->assertTrue($names->contains('seller'));
        $this->assertTrue($names->contains('stock'));
        $this->assertTrue($names->contains('professional'));
        $this->assertFalse($names->contains('admin'));
        $this->assertFalse($names->contains('super-admin'));
    }
}
