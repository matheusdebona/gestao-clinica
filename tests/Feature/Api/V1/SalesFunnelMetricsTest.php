<?php

namespace Tests\Feature\Api\V1;

use App\Models\Appointment;
use App\Models\Budget;
use App\Models\Client;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultLeadStatuses;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesFunnelMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $seller;

    private const URL = '/api/v1/metrics/sales-funnel?from=2026-10-01&to=2026-10-31';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->clinic = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($this->clinic);
        EnsureDefaultLeadStatuses::run($this->clinic);
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        $this->seller = User::factory()->forClinic($this->clinic)->create(['name' => 'Bia Vendas']);
        $this->seller->assignRole('seller');
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

    /**
     * @param  list<string>  $steps  approached, replied, booked, attended, budget, accepted, sale:<amount>
     */
    private function lead(string $origin, User $by, array $steps = [], string $createdAt = '2026-10-10 10:00:00', ?Clinic $clinic = null): Lead
    {
        $clinic ??= $this->clinic;
        $client = null;
        $needsClient = array_intersect(['attended', 'budget', 'accepted'], $steps) !== []
            || collect($steps)->contains(fn ($s) => str_starts_with($s, 'sale:'));
        if ($needsClient) {
            $client = Client::factory()->forClinic($clinic)->create(['client_origin_id' => $this->origin($origin, $clinic)->id]);
            $client->forceFill(['created_at' => '2026-10-12 10:00:00'])->save();
        }

        $lead = Lead::factory()->forClinic($clinic)->create([
            'client_origin_id' => $this->origin($origin, $clinic)->id,
            'created_by_user_id' => $by->id,
            'client_id' => $client?->id,
            'converted_at' => $client ? '2026-10-12 10:00:00' : null,
            'approached_at' => in_array('approached', $steps, true) ? '2026-10-11 10:00:00' : null,
            'replied_at' => in_array('replied', $steps, true) ? '2026-10-11 11:00:00' : null,
            'booked_at' => in_array('booked', $steps, true) ? '2026-10-11 12:00:00' : null,
        ]);
        $lead->forceFill(['created_at' => $createdAt])->save();
        if ($client) {
            $client->forceFill(['lead_id' => $lead->id])->save();
        }

        $this->patientSteps($client, $clinic, $steps);

        return $lead;
    }

    /** @param list<string> $steps */
    private function patientSteps(?Client $client, Clinic $clinic, array $steps): void
    {
        if ($client === null) {
            return;
        }
        if (in_array('attended', $steps, true)) {
            Appointment::factory()->forClinic($clinic)->create([
                'client_id' => $client->id, 'status' => Appointment::STATUS_COMPLETED, 'scheduled_at' => '2026-10-15 10:00:00',
            ]);
        }
        if (in_array('budget', $steps, true) || in_array('accepted', $steps, true)) {
            $sale = Sale::factory()->forClinic($clinic)->create(['client_id' => $client->id]);
            Budget::factory()->forClinic($clinic)->create([
                'client_id' => $client->id, 'sale_id' => $sale->id,
                'status' => in_array('accepted', $steps, true) ? Budget::STATUS_ACCEPTED : Budget::STATUS_SENT,
            ]);
        }
        foreach ($steps as $step) {
            if (str_starts_with($step, 'sale:')) {
                Sale::factory()->forClinic($clinic)->create([
                    'client_id' => $client->id, 'status' => Sale::STATUS_CONFIRMED,
                    'sold_at' => '2026-10-20 10:00:00', 'effective_amount' => substr($step, 5),
                ]);
            }
        }
    }

    public function test_funnel_counts_each_stage_with_rates_revenue_and_breakdowns(): void
    {
        // Instagram / Bia: full funnel with two sales; one stuck after replying.
        $this->lead('Instagram', $this->seller, ['approached', 'replied', 'booked', 'attended', 'budget', 'sale:1000.00', 'sale:500.00']);
        $this->lead('Instagram', $this->seller, ['approached', 'replied']);
        // Google / Ana: accepted budget counts as venda (no confirmed sale yet → no revenue).
        $this->lead('Google', $this->admin, ['approached', 'replied', 'booked', 'attended', 'accepted']);
        $this->lead('Google', $this->admin);
        // Outside the period: ignored.
        $this->lead('Google', $this->admin, ['approached'], '2026-09-01 10:00:00');
        // Direct patient (no lead) with a sale → "Sem lead".
        $direct = Client::factory()->forClinic($this->clinic)->create(['client_origin_id' => $this->origin('Indicação')->id]);
        $direct->forceFill(['created_at' => '2026-10-05 10:00:00'])->save();
        $this->patientSteps($direct, $this->clinic, ['attended', 'sale:300.00']);

        Sanctum::actingAs($this->admin);
        $data = $this->getJson(self::URL)->assertOk()->json('data');

        $stages = collect($data['stages'])->keyBy('key');
        $this->assertSame(4, $stages['leads']['count']);
        $this->assertSame(3, $stages['approached']['count']);
        $this->assertSame(3, $stages['replied']['count']);
        $this->assertSame(2, $stages['booked']['count']);
        $this->assertSame(2, $stages['attended']['count']);
        $this->assertSame(2, $stages['budgeted']['count']);
        $this->assertSame(2, $stages['sold']['count']);
        $this->assertSame('75.00', $stages['approached']['rate_from_previous']);
        $this->assertSame('66.67', $stages['booked']['rate_from_previous']);
        $this->assertSame('50.00', $stages['sold']['rate_from_start']);

        $this->assertSame('50.00', $data['kpis']['conversion_rate']);
        $this->assertSame('1500.00', $data['kpis']['revenue']);
        $this->assertSame(2, $data['kpis']['sales_count']);
        $this->assertSame('750.00', $data['kpis']['ticket_avg']);

        $this->assertSame(1, $data['no_lead']['patients']);
        $this->assertSame('300.00', $data['no_lead']['revenue']);
        $this->assertSame('1800.00', $data['totals']['revenue']);
        $this->assertSame(3, $data['totals']['sold']);

        $byOrigin = collect($data['by_origin'])->keyBy('label');
        $this->assertSame(['Instagram', 'Google', 'Sem lead'], $byOrigin->keys()->all());
        $this->assertSame('1500.00', $byOrigin['Instagram']['revenue']);
        $this->assertSame('750.00', $byOrigin['Instagram']['ticket_avg']);
        $this->assertSame('50.00', $byOrigin['Instagram']['conversion_rate']);
        $this->assertSame(1, $byOrigin['Google']['sold']);
        $this->assertSame('0.00', $byOrigin['Google']['revenue']);
        $this->assertSame('300.00', $byOrigin['Sem lead']['revenue']);
        // Rows add up to the totals.
        $this->assertEqualsWithDelta(1800.0, collect($data['by_origin'])->sum(fn ($r) => (float) $r['revenue']), 0.001);

        $byUser = collect($data['by_user'])->keyBy('label');
        $this->assertSame(2, $byUser['Bia Vendas']['leads']);
        $this->assertSame('1500.00', $byUser['Bia Vendas']['revenue']);
        $this->assertSame(1, $byUser['Ana Admin']['sold']);
        $this->assertArrayHasKey('Sem lead', $byUser->all());
    }

    public function test_filters_by_origin_and_collaborator(): void
    {
        $this->lead('Instagram', $this->seller, ['approached', 'replied', 'booked', 'attended', 'budget', 'sale:1000.00']);
        $this->lead('Google', $this->admin, ['approached']);
        $direct = Client::factory()->forClinic($this->clinic)->create(['client_origin_id' => $this->origin('Instagram')->id]);
        $direct->forceFill(['created_at' => '2026-10-05 10:00:00'])->save();

        Sanctum::actingAs($this->admin);
        $byOrigin = $this->getJson(self::URL.'&client_origin_id='.$this->origin('Instagram')->id)->assertOk()->json('data');
        $this->assertSame(1, $byOrigin['kpis']['leads']);
        $this->assertSame('1000.00', $byOrigin['kpis']['revenue']);
        $this->assertSame(1, $byOrigin['no_lead']['patients']);

        $byUser = $this->getJson(self::URL.'&user_id='.$this->admin->id)->assertOk()->json('data');
        $this->assertSame(1, $byUser['kpis']['leads']);
        $this->assertSame(0, $byUser['kpis']['sold']);
        // Direct patients have no collaborator: left out when filtering by one.
        $this->assertSame(0, $byUser['no_lead']['patients']);
        $this->assertSame(['Ana Admin'], collect($byUser['by_user'])->pluck('label')->all());
    }

    public function test_clinic_isolation_and_permissions(): void
    {
        $this->lead('Instagram', $this->seller, ['approached', 'replied', 'booked', 'attended', 'budget', 'sale:1000.00']);

        $other = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($other);
        EnsureDefaultLeadStatuses::run($other);
        $foreignUser = User::factory()->forClinic($other)->create();
        $foreignUser->assignRole('admin');
        $this->lead('Instagram', $foreignUser, ['approached', 'replied', 'booked', 'attended', 'budget', 'sale:9999.00'], clinic: $other);

        Sanctum::actingAs($this->admin);
        $data = $this->getJson(self::URL)->assertOk()->json('data');
        $this->assertSame(1, $data['kpis']['leads']);
        $this->assertSame('1000.00', $data['kpis']['revenue']);

        // Filters must belong to the clinic.
        $this->getJson(self::URL.'&client_origin_id='.$this->origin('Instagram', $other)->id)
            ->assertUnprocessable()->assertJsonValidationErrors(['client_origin_id']);
        $this->getJson(self::URL.'&user_id='.$foreignUser->id)
            ->assertUnprocessable()->assertJsonValidationErrors(['user_id']);

        foreach (['stock', 'professional'] as $role) {
            $user = User::factory()->forClinic($this->clinic)->create();
            $user->assignRole($role);
            Sanctum::actingAs($user);
            $this->getJson(self::URL)->assertForbidden();
        }
    }
}
