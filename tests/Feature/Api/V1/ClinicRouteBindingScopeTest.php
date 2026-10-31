<?php

namespace Tests\Feature\Api\V1;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicRouteBindingScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_binding_is_scoped_to_the_user_clinic(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $a = Clinic::factory()->create();
        $b = Clinic::factory()->create();
        $u = User::factory()->forClinic($a)->create();
        $u->assignRole('admin');
        $c = Client::factory()->forClinic($b)->create();
        CurrentClinic::forget();
        Sanctum::actingAs($u);
        $this->getJson("/api/v1/clients/{$c->id}")->assertNotFound();
    }
}
