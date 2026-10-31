<?php

namespace Tests\Feature\Api\V1;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Support\ClinicSettings;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultLeadStatuses;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsappMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create(['name' => 'Clínica Bella']);
        EnsureDefaultClientOrigins::run($this->clinic);
        EnsureDefaultLeadStatuses::run($this->clinic);
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        $this->reception = User::factory()->forClinic($this->clinic)->create(['name' => 'Rita Recepção']);
        $this->reception->assignRole('receptionist');
        CurrentClinic::setId($this->clinic->id);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    public function test_templates_default_update_reset_and_permissions(): void
    {
        Sanctum::actingAs($this->reception);
        $this->getJson('/api/v1/clinic/settings')->assertOk()
            ->assertJsonPath('data.clinic_name', 'Clínica Bella')
            ->assertJsonPath('data.whatsapp_templates.appointment_confirmation', ClinicSettings::DEFAULT_WHATSAPP_TEMPLATES['appointment_confirmation'])
            ->assertJsonPath('data.whatsapp_templates.lead_follow_up', ClinicSettings::DEFAULT_WHATSAPP_TEMPLATES['lead_follow_up']);
        $this->putJson('/api/v1/clinic/settings', ['whatsapp_templates' => ['lead_follow_up' => 'Oi']])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->putJson('/api/v1/clinic/settings', [
            'whatsapp_templates' => ['lead_follow_up' => '  Oi {paciente}, tudo bem?  '],
        ])->assertOk()
            ->assertJsonPath('data.whatsapp_templates.lead_follow_up', 'Oi {paciente}, tudo bem?')
            ->assertJsonPath('data.whatsapp_templates.appointment_confirmation', ClinicSettings::DEFAULT_WHATSAPP_TEMPLATES['appointment_confirmation']);

        // Blank = back to the default; other settings are kept.
        $this->clinic->refresh()->updateBranding(['primary_color' => 'brand']);
        $this->putJson('/api/v1/clinic/settings', ['whatsapp_templates' => ['lead_follow_up' => '']])->assertOk()
            ->assertJsonPath('data.whatsapp_templates.lead_follow_up', ClinicSettings::DEFAULT_WHATSAPP_TEMPLATES['lead_follow_up']);
        $this->assertSame('brand', $this->clinic->fresh()->settings['branding']['primary_color'] ?? null);

        $this->putJson('/api/v1/clinic/settings', [
            'whatsapp_templates' => ['lead_follow_up' => str_repeat('a', 1001)],
        ])->assertUnprocessable();

        // Users without any related permission cannot read it.
        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');
        Sanctum::actingAs($stock);
        $this->getJson('/api/v1/clinic/settings')->assertForbidden();
    }

    public function test_opening_whatsapp_logs_a_lead_touch(): void
    {
        $lead = Lead::factory()->forClinic($this->clinic)->create([
            'client_origin_id' => ClientOrigin::query()->where('clinic_id', $this->clinic->id)->value('id'),
            'lead_status_id' => LeadStatus::query()->where('clinic_id', $this->clinic->id)->where('name', 'Novo')->value('id'),
        ]);

        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/leads/{$lead->id}/whatsapp")->assertCreated()
            ->assertJsonPath('data.type', 'whatsapp')
            ->assertJsonPath('data.user.name', 'Rita Recepção');

        $this->getJson("/api/v1/leads/{$lead->id}")->assertOk()
            ->assertJsonPath('data.activities.0.type', 'whatsapp');

        $viewer = User::factory()->forClinic($this->clinic)->create();
        $viewer->givePermissionTo('leads.view');
        Sanctum::actingAs($viewer);
        $this->postJson("/api/v1/leads/{$lead->id}/whatsapp")->assertForbidden();
    }

    public function test_appointment_confirmation_is_recorded(): void
    {
        $client = Client::factory()->forClinic($this->clinic)->create();
        $appointment = Appointment::factory()->forClinic($this->clinic)->create([
            'client_id' => $client->id,
            'treatment_id' => null,
        ]);

        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/appointments/{$appointment->id}/whatsapp-confirmation")->assertOk()
            ->assertJsonPath('data.confirmation_sent_by.name', 'Rita Recepção');
        $this->assertNotNull($appointment->fresh()->confirmation_sent_at);

        $this->getJson("/api/v1/appointments/{$appointment->id}")->assertOk()
            ->assertJsonPath('data.confirmation_sent_by.name', 'Rita Recepção');

        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');
        Sanctum::actingAs($stock);
        $this->postJson("/api/v1/appointments/{$appointment->id}/whatsapp-confirmation")->assertForbidden();
    }
}
