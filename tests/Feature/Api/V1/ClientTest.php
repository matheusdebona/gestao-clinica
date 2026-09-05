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

class ClientTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    public function test_can_create_and_show_client(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/clients', [
            'name' => 'Maria Silva',
            'whatsapp' => '11999887766',
            'notes' => 'Paciente recorrente',
            'main_pains' => 'Rugas na testa',
            'service_duration_minutes' => 45,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Maria Silva')
            ->assertJsonPath('data.whatsapp', '11999887766')
            ->assertJsonPath('data.notes', 'Paciente recorrente')
            ->assertJsonPath('data.main_pains', 'Rugas na testa')
            ->assertJsonPath('data.service_duration_minutes', 45)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.clinic_id', $this->clinic->id);

        $id = $response->json('data.id');

        $this->getJson("/api/v1/clients/{$id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria Silva');
    }

    public function test_can_search_clients_by_name_or_whatsapp(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);

        Client::factory()->forClinic($this->clinic)->create([
            'name' => 'Ana Souza',
            'whatsapp' => '11911112222',
        ]);
        Client::factory()->forClinic($this->clinic)->create([
            'name' => 'Bruno Lima',
            'whatsapp' => '11933334444',
        ]);

        $byName = $this->getJson('/api/v1/clients?q=Ana')->assertOk();
        $this->assertCount(1, $byName->json('data'));
        $this->assertSame('Ana Souza', $byName->json('data.0.name'));

        $byWhatsapp = $this->getJson('/api/v1/clients?q=933334444')->assertOk();
        $this->assertCount(1, $byWhatsapp->json('data'));
        $this->assertSame('Bruno Lima', $byWhatsapp->json('data.0.name'));
    }

    public function test_can_update_and_deactivate_client(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);

        $client = Client::factory()->forClinic($this->clinic)->create([
            'name' => 'Carla Dias',
            'whatsapp' => '11955556666',
        ]);

        $this->putJson("/api/v1/clients/{$client->id}", [
            'name' => 'Carla Dias Atualizada',
            'main_pains' => 'Flacidez',
            'service_duration_minutes' => 60,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Carla Dias Atualizada')
            ->assertJsonPath('data.main_pains', 'Flacidez')
            ->assertJsonPath('data.service_duration_minutes', 60);

        $this->deleteJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Client deactivated.');

        $this->assertFalse($client->fresh()->is_active);
    }

    public function test_user_without_permission_cannot_create_client(): void
    {
        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/clients', [
            'name' => 'Sem Permissão',
            'whatsapp' => '11900001111',
        ])->assertForbidden();
    }

    public function test_clients_are_isolated_by_clinic(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);

        Client::factory()->forClinic($this->clinic)->create([
            'name' => 'Cliente Local',
            'whatsapp' => '11977778888',
        ]);

        $otherClinic = Clinic::factory()->create();
        Client::factory()->forClinic($otherClinic)->create([
            'name' => 'Cliente Outra Clínica',
            'whatsapp' => '11999990000',
        ]);

        $response = $this->getJson('/api/v1/clients')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Cliente Local'));
        $this->assertFalse($names->contains('Cliente Outra Clínica'));
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/clients', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'whatsapp']);
    }
}
