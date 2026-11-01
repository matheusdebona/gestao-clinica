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
use Illuminate\Database\UniqueConstraintViolationException;
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
            ['Novo', 'Em contato', 'Agendado', 'Virou paciente', 'Perdido', 'Cancelado'],
            collect($response->json('data'))->pluck('name')->all(),
        );
        $this->assertTrue($this->leadStatus('Novo')->is_default);
        $this->assertSame(LeadStatus::SYSTEM_CONVERTED, $this->leadStatus('Virou paciente')->system_key);
        $this->assertSame(LeadStatus::SYSTEM_CANCELLED, $this->leadStatus('Cancelado')->system_key);

        // Idempotent: running again creates nothing.
        EnsureDefaultLeadStatuses::runAll();
        $this->assertSame(6, LeadStatus::query()->withoutGlobalScopes()->where('clinic_id', $this->clinic->id)->count());

        // A clinic seeded before "Cancelado" existed gets it added.
        $this->leadStatus('Cancelado')->delete();
        EnsureDefaultLeadStatuses::run($this->clinic);
        $this->assertSame(LeadStatus::SYSTEM_CANCELLED, $this->leadStatus('Cancelado')->system_key);
        $this->deleteJson('/api/v1/lead-statuses/'.$this->leadStatus('Cancelado')->id)->assertUnprocessable();
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

    public function test_create_requires_name_and_origin_of_same_clinic_phone_is_optional(): void
    {
        Sanctum::actingAs($this->admin);
        $other = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($other);
        $foreignOrigin = ClientOrigin::query()->withoutGlobalScopes()->where('clinic_id', $other->id)->firstOrFail();

        $this->postJson('/api/v1/leads', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'client_origin_id'])
            ->assertJsonMissingValidationErrors(['phone']);

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
        foreach (['stock', 'professional'] as $role) {
            $user = User::factory()->forClinic($this->clinic)->create();
            $user->assignRole($role);
            Sanctum::actingAs($user);

            $this->assertFalse($user->can('leads.view'), $role);
            $this->getJson('/api/v1/leads')->assertForbidden();
            $this->getJson('/api/v1/lead-statuses')->assertForbidden();
            $this->postJson('/api/v1/leads', [
                'name' => 'X', 'phone' => '11911112222', 'client_origin_id' => $this->instagram->id,
            ])->assertForbidden();
            $this->postJson('/api/v1/lead-statuses', ['name' => 'Novo2', 'color' => 'muted'])->assertForbidden();
        }

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
            'notes' => 'Consulta',
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

        // Cannot convert twice, cannot cancel or delete, cannot leave "Virou paciente".
        $this->postJson("/api/v1/leads/{$id}/convert", [
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'professional_user_id' => $this->admin->id,
        ])->assertUnprocessable();
        $this->postJson("/api/v1/leads/{$id}/cancel")->assertUnprocessable();
        $this->deleteJson("/api/v1/leads/{$id}")->assertStatus(405);
        $this->patchJson("/api/v1/leads/{$id}/status", ['lead_status_id' => $this->leadStatus('Perdido')->id])
            ->assertUnprocessable();
        $this->assertDatabaseHas('leads', ['id' => $id]);
    }

    public function test_convert_with_existing_phone_requires_choice_and_can_link(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);
        $id = $this->postJson('/api/v1/leads', [
            'name' => 'Lia Mota', 'phone' => '11922223333', 'client_origin_id' => $this->instagram->id,
        ])->assertCreated()->json('data.id');

        // Patient registered directly after the lead existed (lead creation would block it).
        CurrentClinic::setId($this->clinic->id);
        $existing = Client::factory()->forClinic($this->clinic)->create(['name' => 'Lia Antiga', 'whatsapp' => '11922223333']);

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

    public function test_duplicate_phone_of_active_lead_is_blocked_with_details(): void
    {
        Sanctum::actingAs($this->admin);

        $first = $this->postJson('/api/v1/leads', [
            'name' => 'Joana Dias', 'phone' => '(11) 97777-1111', 'client_origin_id' => $this->instagram->id,
        ])->assertCreated()->assertJsonPath('data.phone', '11977771111');
        $firstId = $first->json('data.id');

        // Same number typed with the country code.
        $this->postJson('/api/v1/leads', [
            'name' => 'Joana D.', 'phone' => '+55 11 97777-1111', 'client_origin_id' => $this->instagram->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('duplicate.type', 'lead')
            ->assertJsonPath('duplicate.lead.id', $firstId)
            ->assertJsonPath('duplicate.lead.name', 'Joana Dias')
            ->assertJsonPath('duplicate.lead.status.name', 'Novo')
            ->assertJsonPath('duplicate.lead.created_by.name', 'Ana Admin');

        $this->getJson('/api/v1/leads/phone-check?phone=11977771111')->assertOk()
            ->assertJsonPath('data.type', 'lead')->assertJsonPath('data.lead.id', $firstId);
        // Editing the same lead does not conflict with itself.
        $this->getJson("/api/v1/leads/phone-check?phone=11977771111&lead_id={$firstId}")->assertOk()
            ->assertJsonPath('data', null);
        $this->putJson("/api/v1/leads/{$firstId}", ['phone' => '11977771111', 'name' => 'Joana Dias Silva'])->assertOk();

        // Editing another lead to the taken phone is blocked too.
        $secondId = $this->postJson('/api/v1/leads', [
            'name' => 'Outra', 'phone' => '11977772222', 'client_origin_id' => $this->instagram->id,
        ])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/leads/{$secondId}", ['phone' => '11977771111'])
            ->assertUnprocessable()->assertJsonPath('duplicate.lead.id', $firstId);

        // Another clinic can have the same phone.
        $other = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($other);
        EnsureDefaultLeadStatuses::run($other);
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        Sanctum::actingAs($otherAdmin);
        $this->postJson('/api/v1/leads', [
            'name' => 'Joana', 'phone' => '11977771111',
            'client_origin_id' => ClientOrigin::query()->withoutGlobalScopes()->where('clinic_id', $other->id)->value('id'),
        ])->assertCreated();
    }

    public function test_duplicate_phone_of_patient_is_blocked(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);
        $patient = Client::factory()->forClinic($this->clinic)->create(['name' => 'Paula Paciente', 'whatsapp' => '(11) 96666-5555']);

        $this->postJson('/api/v1/leads', [
            'name' => 'Paula', 'phone' => '11966665555', 'client_origin_id' => $this->instagram->id,
        ])->assertUnprocessable()
            ->assertJsonPath('duplicate.type', 'patient')
            ->assertJsonPath('duplicate.client.id', $patient->id)
            ->assertJsonPath('duplicate.client.name', 'Paula Paciente');

        $this->getJson('/api/v1/leads/phone-check?phone=5511966665555')->assertOk()
            ->assertJsonPath('data.type', 'patient');
        $this->getJson('/api/v1/leads/phone-check?phone=11900000000')->assertOk()->assertJsonPath('data', null);
    }

    public function test_cancel_is_final_kept_in_list_and_frees_the_phone(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/leads', [
            'name' => 'Clara', 'phone' => '11955550000', 'client_origin_id' => $this->instagram->id,
        ])->assertCreated()->json('data.id');

        // Cancelado cannot be chosen manually.
        $this->patchJson("/api/v1/leads/{$id}/status", ['lead_status_id' => $this->leadStatus('Cancelado')->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['lead_status_id']);

        $this->postJson("/api/v1/leads/{$id}/cancel", ['reason' => 'Número errado'])->assertOk()
            ->assertJsonPath('data.status.name', 'Cancelado')
            ->assertJsonPath('data.activities.0.type', 'cancelled')
            ->assertJsonPath('data.activities.0.note', 'Número errado')
            ->assertJsonPath('data.activities.0.from_status.name', 'Novo');
        $this->assertNotNull(Lead::query()->find($id)->cancelled_at);

        // Final: no status change, edit, conversion or second cancel.
        $this->patchJson("/api/v1/leads/{$id}/status", ['lead_status_id' => $this->leadStatus('Novo')->id])
            ->assertUnprocessable();
        $this->putJson("/api/v1/leads/{$id}", ['name' => 'Clara 2'])->assertUnprocessable();
        $this->postJson("/api/v1/leads/{$id}/convert", [
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'professional_user_id' => $this->admin->id,
            'create_new_client' => true,
        ])->assertUnprocessable();
        $this->postJson("/api/v1/leads/{$id}/cancel")->assertUnprocessable();
        $this->deleteJson("/api/v1/leads/{$id}")->assertStatus(405);

        // Still in the default list and reachable by status filter.
        $this->getJson('/api/v1/leads')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/leads?lead_status_id='.$this->leadStatus('Cancelado')->id)
            ->assertOk()->assertJsonCount(1, 'data');

        // The phone of a cancelled lead can be used by a new lead; the old one stays.
        $this->getJson('/api/v1/leads/phone-check?phone=11955550000')->assertOk()->assertJsonPath('data', null);
        $this->postJson('/api/v1/leads', [
            'name' => 'Clara Nova', 'phone' => '11955550000', 'client_origin_id' => $this->instagram->id,
        ])->assertCreated();
        $this->assertSame(2, Lead::query()->where('phone_digits', '11955550000')->count());
    }

    public function test_db_enforces_one_active_lead_per_phone(): void
    {
        CurrentClinic::setId($this->clinic->id);
        $make = fn (array $extra = []) => Lead::factory()->forClinic($this->clinic)->create([
            'phone' => '11944440000', 'client_origin_id' => $this->instagram->id,
            'lead_status_id' => $this->leadStatus('Novo')->id, ...$extra,
        ]);

        $make(['cancelled_at' => now()]);
        $make();
        $this->expectException(UniqueConstraintViolationException::class);
        $make(['phone' => '5511944440000']);
    }

    public function test_comments_join_the_history_and_are_allowed_on_final_leads(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/leads', [
            'name' => 'Marta', 'phone' => '11933330000', 'client_origin_id' => $this->instagram->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/leads/{$id}/comments", ['body' => '  '])
            ->assertUnprocessable()->assertJsonValidationErrors(['body']);

        $this->postJson("/api/v1/leads/{$id}/comments", ['body' => 'Ligou, pediu retorno amanhã'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'comment')
            ->assertJsonPath('data.note', 'Ligou, pediu retorno amanhã')
            ->assertJsonPath('data.user.name', 'Ana Admin');

        $this->patchJson("/api/v1/leads/{$id}/status", ['lead_status_id' => $this->leadStatus('Em contato')->id])->assertOk();
        $this->postJson("/api/v1/leads/{$id}/cancel", ['reason' => 'Desistiu'])->assertOk();
        // Cancelled leads still take comments (history).
        $this->postJson("/api/v1/leads/{$id}/comments", ['body' => 'Confirmou que desistiu'])->assertCreated();

        $types = collect($this->getJson("/api/v1/leads/{$id}")->assertOk()->json('data.activities'))->pluck('type')->all();
        $this->assertSame(['comment', 'cancelled', 'status_changed', 'comment', 'created'], $types);

        $this->getJson('/api/v1/leads')->assertOk()->assertJsonPath('data.0.comments_count', 2);

        // View-only users read but cannot comment; comments are never deleted.
        $viewer = User::factory()->forClinic($this->clinic)->create();
        $viewer->givePermissionTo('leads.view');
        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/leads/{$id}")->assertOk();
        $this->postJson("/api/v1/leads/{$id}/comments", ['body' => 'x'])->assertForbidden();
    }
}
