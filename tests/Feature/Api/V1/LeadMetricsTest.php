<?php

namespace Tests\Feature\Api\V1;

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

class LeadMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->clinic = Clinic::factory()->create();
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

    private function origin(string $name, ?Clinic $clinic = null): ClientOrigin
    {
        return ClientOrigin::query()->withoutGlobalScopes()
            ->where('clinic_id', ($clinic ?? $this->clinic)->id)->where('name', $name)->firstOrFail();
    }

    private function leadStatus(string $name, ?Clinic $clinic = null): LeadStatus
    {
        return LeadStatus::query()->withoutGlobalScopes()
            ->where('clinic_id', ($clinic ?? $this->clinic)->id)->where('name', $name)->firstOrFail();
    }

    private function lead(string $origin, string $status, ?User $by, string $createdAt, ?string $convertedAt = null): Lead
    {
        $client = $convertedAt !== null
            ? Client::factory()->forClinic($this->clinic)->create()
            : null;

        $lead = Lead::factory()->forClinic($this->clinic)->create([
            'client_origin_id' => $this->origin($origin)->id,
            'lead_status_id' => $this->leadStatus($status)->id,
            'created_by_user_id' => $by?->id,
            'client_id' => $client?->id,
            'converted_at' => $convertedAt,
        ]);
        $lead->forceFill(['created_at' => $createdAt])->save();

        return $lead;
    }

    public function test_lead_metrics_aggregate_the_period_cohort(): void
    {
        // In period (October): 5 leads (1 cancelled, still counted), 2 converted.
        $this->lead('Instagram', 'Virou paciente', $this->reception, '2026-10-02 10:00:00', '2026-10-04 10:00:00');
        $this->lead('Instagram', 'Virou paciente', $this->admin, '2026-10-10 09:00:00', '2026-11-03 09:00:00');
        $this->lead('Instagram', 'Novo', $this->reception, '2026-10-12 09:00:00');
        $this->lead('Google', 'Perdido', $this->reception, '2026-11-01 02:30:00'); // 31/10 23:30 em São Paulo → conta
        $cancelled = $this->lead('Google', 'Cancelado', $this->reception, '2026-10-20 10:00:00');
        $cancelled->forceFill(['cancelled_at' => '2026-10-21 10:00:00'])->save();
        // Outside the period.
        $this->lead('Google', 'Novo', $this->admin, '2026-10-01 02:59:00'); // 30/09 23:59 em São Paulo → fora
        $this->lead('Google', 'Novo', $this->admin, '2026-11-01 03:00:00'); // 01/11 00:00 em São Paulo → fora

        // Another clinic's leads never leak in.
        $other = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($other);
        EnsureDefaultLeadStatuses::run($other);
        Lead::factory()->forClinic($other)->create([
            'client_origin_id' => $this->origin('Instagram', $other)->id,
            'lead_status_id' => $this->leadStatus('Novo', $other)->id,
            'created_at' => '2026-10-15 10:00:00',
        ]);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/v1/metrics/leads?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.kpis.total', 5)
            ->assertJsonPath('data.kpis.converted', 2)
            ->assertJsonPath('data.kpis.conversion_rate', '40.00')
            ->assertJsonPath('data.kpis.cancelled', 1)
            ->assertJsonPath('data.kpis.cancelled_rate', '20.00')
            ->assertJsonPath('data.kpis.avg_days_to_conversion', '13.0');

        $origins = collect($response->json('data.by_origin'))->keyBy('label');
        $this->assertSame(['Instagram', 'Google'], $origins->keys()->all());
        $this->assertSame(3, $origins['Instagram']['total']);
        $this->assertSame(2, $origins['Instagram']['converted']);
        $this->assertSame('66.67', $origins['Instagram']['conversion_rate']);
        $this->assertSame('0.00', $origins['Google']['conversion_rate']);
        $this->assertSame(2, $origins['Google']['total']);
        $this->assertSame(1, $origins['Google']['cancelled']);

        $statuses = collect($response->json('data.by_status'));
        $this->assertSame(['Novo', 'Virou paciente', 'Perdido', 'Cancelado'], $statuses->pluck('label')->all());
        $this->assertSame([1, 2, 1, 1], $statuses->pluck('total')->all());
        $this->assertSame('converted', $statuses[1]['system_key']);
        $this->assertSame('success', $statuses[1]['color']);

        $creators = collect($response->json('data.by_creator'));
        $this->assertSame(['Rita Recepção', 'Ana Admin'], $creators->pluck('label')->all());
        $this->assertSame([4, 1], $creators->pluck('total')->all());
        $this->assertSame([1, 1], $creators->pluck('converted')->all());
        $this->assertSame([1, 0], $creators->pluck('cancelled')->all());
        $this->assertSame(['25.00', '100.00'], $creators->pluck('conversion_rate')->all());
    }

    public function test_empty_period_returns_zeroes(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/metrics/leads?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.kpis.total', 0)
            ->assertJsonPath('data.kpis.converted', 0)
            ->assertJsonPath('data.kpis.conversion_rate', null)
            ->assertJsonPath('data.kpis.avg_days_to_conversion', null)
            ->assertJsonPath('data.by_origin', [])
            ->assertJsonPath('data.by_creator', []);
    }

    public function test_validates_period(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/metrics/leads')->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);
        $this->getJson('/api/v1/metrics/leads?from=2026-10-31&to=2026-10-01')->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    public function test_requires_metrics_and_leads_permissions(): void
    {
        // Receptionist has leads.view but not metrics.view.
        Sanctum::actingAs($this->reception);
        $this->getJson('/api/v1/metrics/leads?from=2026-10-01&to=2026-10-31')->assertForbidden();

        foreach (['stock', 'professional'] as $role) {
            $user = User::factory()->forClinic($this->clinic)->create();
            $user->assignRole($role);
            $user->givePermissionTo('metrics.view');
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/metrics/leads?from=2026-10-01&to=2026-10-31')->assertForbidden();
            $this->getJson('/api/v1/metrics/acquisition?from=2026-10-01&to=2026-10-31')->assertOk();
        }
    }
}
