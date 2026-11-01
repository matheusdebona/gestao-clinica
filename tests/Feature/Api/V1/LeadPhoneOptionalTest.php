<?php

namespace Tests\Feature\Api\V1;

use App\Models\Client;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultLeadStatuses;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Telefone é opcional para cadastrar e editar o lead, e obrigatório para agendar
 * (converter o lead em paciente com consulta) e para cadastrar paciente.
 */
class LeadPhoneOptionalTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    private User $admin;

    private ClientOrigin $instagram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($this->clinic);
        EnsureDefaultLeadStatuses::run($this->clinic);
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        $this->instagram = ClientOrigin::query()->withoutGlobalScopes()
            ->where('clinic_id', $this->clinic->id)->where('name', 'Instagram')->firstOrFail();
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function newLead(array $extra = []): int
    {
        return (int) $this->postJson('/api/v1/leads', [
            'name' => 'Sem Telefone', 'client_origin_id' => $this->instagram->id, ...$extra,
        ])->assertCreated()->json('data.id');
    }

    private function schedule(): array
    {
        return [
            'scheduled_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'professional_user_id' => $this->admin->id,
        ];
    }

    public function test_lead_can_be_created_and_edited_without_phone(): void
    {
        $a = $this->newLead();
        $this->getJson("/api/v1/leads/{$a}")->assertOk()->assertJsonPath('data.phone', null);

        // Vários leads sem telefone convivem (o índice único ignora NULL); "" e máscara vazia viram null.
        $b = $this->newLead(['phone' => '']);
        $c = $this->newLead(['phone' => '(  )     -    ']);
        $this->assertSame(3, Lead::query()->whereNull('phone')->whereNull('phone_digits')->count());

        // Telefone curto continua inválido.
        $this->postJson('/api/v1/leads', ['name' => 'X', 'phone' => '1199', 'client_origin_id' => $this->instagram->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone']);

        // Adiciona depois e pode tirar de novo.
        $this->putJson("/api/v1/leads/{$b}", ['phone' => '(11) 98888-1234'])->assertOk()->assertJsonPath('data.phone', '11988881234');
        $this->putJson("/api/v1/leads/{$b}", ['phone' => null])->assertOk()->assertJsonPath('data.phone', null);
        $this->putJson("/api/v1/leads/{$c}", ['name' => 'Só o nome'])->assertOk()->assertJsonPath('data.phone', null);

        // Listas, busca, resumo de parados e resumo do dia não quebram com lead sem telefone.
        $this->getJson('/api/v1/leads')->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/leads/stale-summary')->assertOk();
        $this->getJson('/api/v1/leads/daily-analysis')->assertOk();
        $this->getJson("/api/v1/leads/{$a}/matching-clients")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_works_with_leads_without_phone(): void
    {
        $this->newLead(['name' => 'Bruna Sem Número']);
        $this->newLead(['name' => 'Carla', 'phone' => '11988885555']);

        $this->getJson('/api/v1/leads?q=Bruna')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/leads?q=8888-5555')->assertOk()->assertJsonPath('data.0.name', 'Carla');
    }

    public function test_whatsapp_action_is_refused_without_phone(): void
    {
        $id = $this->newLead();
        $this->postJson("/api/v1/leads/{$id}/whatsapp")
            ->assertUnprocessable()->assertJsonPath('message', 'Este lead ainda não tem telefone.');
        $this->assertDatabaseMissing('lead_activities', ['lead_id' => $id, 'type' => 'whatsapp']);
        $this->assertDatabaseHas('leads', ['id' => $id, 'approached_at' => null]);
    }

    public function test_scheduling_requires_phone_and_saves_it_to_lead_and_patient(): void
    {
        $id = $this->newLead();

        $this->postJson("/api/v1/leads/{$id}/convert", $this->schedule())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'Informe o telefone do paciente para agendar.']);
        $this->postJson("/api/v1/leads/{$id}/convert", [...$this->schedule(), 'phone' => '119'])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone' => 'Informe DDD e número.']);
        $this->assertSame(0, Client::query()->count());

        $this->postJson("/api/v1/leads/{$id}/convert", [...$this->schedule(), 'phone' => '(11) 97777-4321'])
            ->assertCreated()
            ->assertJsonPath('data.client_created', true)
            ->assertJsonPath('data.client.whatsapp', '11977774321')
            ->assertJsonPath('data.lead.phone', '11977774321');
        $this->assertDatabaseHas('leads', ['id' => $id, 'phone' => '11977774321', 'phone_digits' => '11977774321']);
    }

    public function test_lead_with_phone_schedules_without_sending_it_again(): void
    {
        $id = $this->newLead(['phone' => '11955556666']);
        $this->postJson("/api/v1/leads/{$id}/convert", $this->schedule())
            ->assertCreated()->assertJsonPath('data.client.whatsapp', '11955556666');
    }

    public function test_scheduling_phone_of_another_active_lead_is_blocked(): void
    {
        $this->newLead(['name' => 'Outra', 'phone' => '11944443333']);
        $id = $this->newLead();

        $this->postJson("/api/v1/leads/{$id}/convert", [...$this->schedule(), 'phone' => '11944443333'])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone']);
        $this->assertDatabaseHas('leads', ['id' => $id, 'phone' => null]);
    }

    public function test_scheduling_phone_of_existing_patient_keeps_the_link_choice(): void
    {
        $patient = Client::factory()->forClinic($this->clinic)->create(['name' => 'Paciente Antiga', 'whatsapp' => '11922221111']);
        $id = $this->newLead();

        // O número fica salvo no lead e a conversão pede para escolher o paciente existente.
        $this->postJson("/api/v1/leads/{$id}/convert", [...$this->schedule(), 'phone' => '11922221111'])
            ->assertUnprocessable()->assertJsonValidationErrors(['client_id']);
        $this->assertDatabaseHas('leads', ['id' => $id, 'phone' => '11922221111']);
        $this->getJson("/api/v1/leads/{$id}/matching-clients")->assertOk()->assertJsonPath('data.0.id', $patient->id);

        $this->postJson("/api/v1/leads/{$id}/convert", [...$this->schedule(), 'client_id' => $patient->id])
            ->assertCreated()->assertJsonPath('data.client.id', $patient->id);
    }

    public function test_patient_still_requires_whatsapp(): void
    {
        $this->postJson('/api/v1/clients', ['name' => 'Paciente sem telefone'])
            ->assertUnprocessable()->assertJsonValidationErrors(['whatsapp']);
    }
}
