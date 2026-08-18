<?php

namespace Tests\Feature\Api\V1;

use App\Models\Clinic;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionAndClinicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    public function test_user_without_permission_gets_forbidden(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->forClinic($clinic)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_user_with_permission_can_list_users(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->forClinic($clinic)->create();
        $user->givePermissionTo('users.view');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users')->assertOk();
    }

    public function test_current_clinic_resolves_from_authenticated_user(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Clínica Alpha']);
        $user = User::factory()->forClinic($clinic)->create();
        $user->givePermissionTo('clinics.view');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/clinics/current')
            ->assertOk()
            ->assertJsonPath('data.name', 'Clínica Alpha')
            ->assertJsonPath('resolved_clinic_id', $clinic->id);
    }

    public function test_clinic_user_does_not_see_users_from_another_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();

        $adminA = User::factory()->forClinic($clinicA)->create();
        $adminA->givePermissionTo('users.view');

        User::factory()->forClinic($clinicB)->create([
            'email' => 'other@clinic-b.test',
        ]);

        Sanctum::actingAs($adminA);

        $response = $this->getJson('/api/v1/users')->assertOk();

        $emails = collect($response->json('data'))->pluck('email');

        $this->assertTrue($emails->contains($adminA->email));
        $this->assertFalse($emails->contains('other@clinic-b.test'));
    }
}
