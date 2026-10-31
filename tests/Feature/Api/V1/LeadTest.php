<?php

namespace Tests\Feature\Api\V1;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultLeadStatuses;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected ClientOrigin $instagram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->clinic = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($this->clinic);
        EnsureDefaultLeadStatuses::run($this->clinic);
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        $this->instagram = ClientOrigin::query()->withoutGlobalScopes()
            ->where('clinic_id', $this->clinic->id)->where('name', 'Instagram')->firstOrFail();
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function leadStatus(string $name, ?Clinic $clinic = null): LeadStatus
    {
        return LeadStatus::query()->withoutGlobalScopes()
            ->where('clinic_id', ($clinic ?? $this->clinic)->id)
            ->where('name', $name)->firstOrFail();
    }

    public function test_default_statuses_are_seeded_with_default_and_system_flags(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/lead-statuses')->assertOk();
        $this->assertSame(
            ['Novo', 'Em contato', 'Agendado', 'Virou paciente', 'Perdido'],
            collect($response->json('data'))->pluck('name')->all(),
        );
        $this->assertTrue($this->leadStatus('Novo')->is_default);
        $this->assertSame(LeadStatus::SYSTEM_CONVERTED, $this->leadStatus('Virou paciente')->system_key);

        // Idempotent: running again creates nothing.
        EnsureDefaultLeadStatuses::runAll();
        $this->assertSame(5, LeadStatus::query()->withoutGlobalScopes()->where('clinic_id', $this->clinic->id)->count());
    }

    public function test_create_lead_sets_creator_default_status_and_history(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Joana Prado',
            'phone' => '(11) 98888-7777',
            'client_origin_id' => $this->instagram->id,
            'notes' => 'Quer saber preço de botox',
        ])->assertCreated();

        $response->assertJsonPath('data.phone', '11988887777')
            ->assertJsonPath('data.status.name', 'Novo')
            ->assertJsonPath('data.client_origin.name', 'Instagram')
            ->assertJsonPath('data.created_by.name', 'Ana Admin')
            ->assertJsonPath('data.clinic_id', $this->clinic->id)
            ->assertJsonPath('data.activities.0.type', 'created');
    }

    public function test_create_requires_name_phone_and_origin_of_same_clinic(): void
    {
        Sanctum::actingAs($this->admin);
        $other = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($other);
        $foreignOrigin = ClientOrigin::query()->withoutGlobalScopes()->where('clinic_id', $other->id)->firstOrFail();

        $this->postJson('/api/v1/leads', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone', 'client_origin_id']);

        $this->postJson('/api/v1/leads', [
            'name' => 'X',
            'phone' => '11988887777',
            'client_origin_id' => $foreignOrigin->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['client_origin_id']);
    }

    public function test_list_filters_and_clinic_scoping(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);
        $seller = User::factory()->forClinic($this->clinic)->create();
        $seller->assignRole('seller');

        Lead::factory()->forClinic($this->clinic)->create([
            'name' => 'Lead Admin', 'client_origin_id' => $this->instagram->id,
            'lead_status_id' => $this->leadStatus('Novo')->id, 'created_by_user_id' => $this->admin->id,
            'created_at' => now()->subDays(10),
        ]);
        Lead::factory()->forClinic($this->clinic)->create([
            'name' => 'Lead Seller', 'phone' => '11977776666', 'client_origin_id' => $this->instagram->id,
            'lead_status_id' => $this->leadStatus('Perdido')->id, 'created_by_user_id' => $seller->id,
        ]);

        $other = Clinic::factory()->create();
        EnsureDefaultLeadStatuses::run($other);
        EnsureDefaultClientOrigins::run($other);
        $foreign = Lead::factory()->forClinic($other)->create([
            'client_origin_id' => ClientOrigin::query()->withoutGlobalScopes()->where('clinic_id', $other->id)->value('id'),
            'lead_status_id' => $this->leadStatus('Novo', $other)->id,
        ]);
        CurrentClinic::setId($this->clinic->id);

        $this->getJson('/api/v1/leads')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/leads?created_by_user_id='.$seller->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Lead Seller');
        $this->getJson('/api/v1/leads?lead_status_id='.$this->leadStatus('Novo')->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Lead Admin');
        $this->getJson('/api/v1/leads?from='.now()->subDay()->toDateString())->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Lead Seller');
        $this->getJson('/api/v1/leads?lead_status_id='.$this->leadStatus('Novo')->id.','.$this->leadStatus('Perdido')->id)
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/leads?converted=0')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/leads?converted=1')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/leads?client_origin_id='.$this->instagram->id)->assertOk()->assertJsonCount(2, 'data');

        CurrentClinic::forget();
        $this->getJson("/api/v1/leads/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/leads/{$foreign->id}", ['name' => 'Hack'])->assertNotFound();

        // Every clinic user sees every lead of the clinic.
        Sanctum::actingAs($seller);
        $this->getJson('/api/v1/leads')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_search_by_phone_digits(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);
        Lead::factory()->forClinic($this->clinic)->create([
            'name' => 'Carla', 'phone' => '11955554444', 'client_origin_id' => $this->instagram->id,
            'lead_status_id' => $this->leadStatus('Novo')->id,
        ]);
        Lead::factory()->forClinic($this->clinic)->create([
            'name' => 'Duda', 'phone' => '11933332222', 'client_origin_id' => $this->instagram->id,
            'lead_status_id' => $this->leadStatus('Novo')->id,
        ]);

        $this->getJson('/api/v1/leads?q='.urlencode('5555-4444'))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Carla');
    }

    public function test_quick_status_change_logs_history_and_blocks_manual_converted(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/v1/leads', [
            'name' => 'Bia', 'phone' => '11911112222', 'client_origin_id' => $this->instagram->id,
        ])->json('data.id');

        $this->patchJson("/api/v1/leads/{$id}/status", [
            'lead_status_id' => $this->leadStatus('Perdido')->id,
            'note' => 'Achou caro',
        ])->assertOk()
            ->assertJsonPath('data.status.name', 'Perdido')
            ->assertJsonPath('data.notes', 'Achou caro')
            ->assertJsonPath('data.activities.0.type', 'status_changed')
            ->assertJsonPath('data.activities.0.note', 'Achou caro')
            ->assertJsonPath('data.activities.0.from_status.name', 'Novo');

        $this->patchJson("/api/v1/leads/{$id}/status", [
            'lead_status_id' => $this->leadStatus('Virou paciente')->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['lead_status_id']);
    }

    public function test_permissions(): void
    {
        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');
        Sanctum::actingAs($stock);

        $this->getJson('/api/v1/leads')->assertOk();
        $this->postJson('/api/v1/leads', [
            'name' => 'X', 'phone' => '11911112222', 'client_origin_id' => $this->instagram->id,
        ])->assertForbidden();
        $this->postJson('/api/v1/lead-statuses', ['name' => 'Novo2', 'color' => 'muted'])->assertForbidden();

        $receptionist = User::factory()->forClinic($this->clinic)->create();
        $receptionist->assignRole('receptionist');
        Sanctum::actingAs($receptionist);
        $this->postJson('/api/v1/leads', [
            'name' => 'X', 'phone' => '11911112222', 'client_origin_id' => $this->instagram->id,
        ])->assertCreated();
        $this->postJson('/api/v1/lead-statuses', ['name' => 'Novo2', 'color' => 'muted'])->assertForbidden();
    }

    public function test_status_crud_default_switch_and_system_protection(): void
    {
        Sanctum::actingAs($this->admin);

        $created = $this->postJson('/api/v1/lead-statuses', [
            'name' => 'Fábrica de contatos', 'color' => 'purple', 'is_default' => true,
        ])->assertCreated()->assertJsonPath('data.is_default', true);
        $this->assertFalse($this->leadStatus('Novo')->fresh()->is_default);

        $this->postJson('/api/v1/lead-statuses', ['name' => 'Fábrica de contatos', 'color' => 'purple'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->postJson('/api/v1/lead-statuses', ['name' => 'Rosa', 'color' => '#ff00ff'])
            ->assertUnprocessable()->assertJsonValidationErrors(['color']);

        $converted = $this->leadStatus('Virou paciente');
        $this->deleteJson("/api/v1/lead-statuses/{$converted->id}")->assertUnprocessable();
        $this->putJson("/api/v1/lead-statuses/{$converted->id}", ['is_active' => false])->assertUnprocessable();
        $this->putJson("/api/v1/lead-statuses/{$converted->id}", ['name' => 'Paciente', 'color' => 'success'])
            ->assertOk()->assertJsonPath('data.name', 'Paciente');

        $this->deleteJson('/api/v1/lead-statuses/'.$created->json('data.id'))->assertUnprocessable();

        $emContato = $this->leadStatus('Em contato');
        $this->deleteJson("/api/v1/lead-statuses/{$emContato->id}")->assertOk()->assertJsonPath('deleted', true);
    }

    public function test_convert_creates_patient_appointment_and_keeps_lead(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/v1/leads', [
            'name' => 'Lia Mota', 'phone' => '11922223333', 'client_origin_id' => $this->instagram->id,
            'notes' => 'Indicada pela Maria',
        ])->json('data.id');

        $this->getJson("/api/v1/leads/{$id}/matching-clients")->assertOk()->assertJsonCount(0, 'data');

        $response = $this->postJson("/api/v1/leads/{$id}/convert", [
            'scheduled_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'professional_user_id' => $this->admin->id,
            'duration_minutes' => 45,
            'notes' => 'Avaliação',
        ])->assertCreated();

        $response->assertJsonPath('data.client_created', true)
            ->assertJsonPath('data.client.name', 'Lia Mota')
            ->assertJsonPath('data.client.whatsapp', '11922223333')
            ->assertJsonPath('data.client.client_origin_id', $this->instagram->id)
            ->assertJsonPath('data.client.lead_id', $id)
            ->assertJsonPath('data.lead.status.name', 'Virou paciente')
            ->assertJsonPath('data.lead.activities.0.type', 'converted')
            ->assertJsonPath('data.appointment.treatment_id', null)
            ->assertJsonPath('data.appointment.status', 'scheduled')
            ->assertJsonPath('data.appointment.duration_minutes', 45);

        $clientId = $response->json('data.client.id');
        $this->assertDatabaseHas('leads', ['id' => $id, 'client_id' => $clientId]);
        $this->assertDatabaseHas('appointments', ['client_id' => $clientId, 'treatment_id' => null]);

        // Lead stays listed and linked.
        $this->getJson('/api/v1/leads')->assertOk()->assertJsonPath('data.0.client.id', $clientId);

        // Patient page carries lead history.
        $this->getJson("/api/v1/clients/{$clientId}")->assertOk()
            ->assertJsonPath('data.lead.id', $id)
            ->assertJsonPath('data.lead.created_by.name', 'Ana Admin')
            ->assertJsonPath('data.lead.notes', 'Indicada pela Maria');

        // Cannot convert twice, cannot delete, cannot leave "Virou paciente".
        $this->postJson("/api/v1/leads/{$id}/convert", [
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'professional_user_id' => $this->admin->id,
        ])->assertUnprocessable();
        $this->deleteJson("/api/v1/leads/{$id}")->assertUnprocessable();
        $this->patchJson("/api/v1/leads/{$id}/status", ['lead_status_id' => $this->leadStatus('Perdido')->id])
            ->assertUnprocessable();
        $this->assertDatabaseHas('leads', ['id' => $id]);
    }

    public function test_convert_with_existing_phone_requires_choice_and_can_link(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);
        $existing = Client::factory()->forClinic($this->clinic)->create(['name' => 'Lia Antiga', 'whatsapp' => '11922223333']);

        $id = $this->postJson('/api/v1/leads', [
            'name' => 'Lia Mota', 'phone' => '11922223333', 'client_origin_id' => $this->instagram->id,
        ])->json('data.id');

        $this->getJson("/api/v1/leads/{$id}/matching-clients")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $existing->id);

        $payload = [
            'scheduled_at' => now()->addDay()->setTime(14, 0)->toIso8601String(),
            'professional_user_id' => $this->admin->id,
        ];

        $this->postJson("/api/v1/leads/{$id}/convert", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['client_id']);

        $this->postJson("/api/v1/leads/{$id}/convert", [...$payload, 'client_id' => $existing->id])
            ->assertCreated()
            ->assertJsonPath('data.client_created', false)
            ->assertJsonPath('data.client.id', $existing->id)
            ->assertJsonPath('data.client.lead_id', $id)
            ->assertJsonPath('data.client.client_origin_id', $this->instagram->id);

        $this->assertSame(1, Client::query()->where('whatsapp', '11922223333')->count());
    }

    public function test_evaluation_appointment_can_start_and_complete_without_treatment(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/v1/leads', [
            'name' => 'Rita', 'phone' => '11944445555', 'client_origin_id' => $this->instagram->id,
        ])->json('data.id');
        $appointmentId = $this->postJson("/api/v1/leads/{$id}/convert", [
            'scheduled_at' => now()->addHour()->toIso8601String(),
            'professional_user_id' => $this->admin->id,
        ])->assertCreated()->json('data.appointment.id');

        $this->getJson('/api/v1/appointments')->assertOk()->assertJsonPath('data.0.treatment', null);
        $this->postJson("/api/v1/appointments/{$appointmentId}/start")->assertOk();
        $this->postJson("/api/v1/appointments/{$appointmentId}/complete")->assertOk();
        $this->assertSame(Appointment::STATUS_COMPLETED, Appointment::query()->find($appointmentId)->status);
    }
}
