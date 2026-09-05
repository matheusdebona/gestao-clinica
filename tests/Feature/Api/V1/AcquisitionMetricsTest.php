<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Sale;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcquisitionMetricsTest extends TestCase
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
        CurrentClinic::setId($this->clinic->id);
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function makeClient(array $attributes = []): Client
    {
        CurrentClinic::setId($this->clinic->id);

        return Client::factory()->forClinic($this->clinic)->create($attributes);
    }

    protected function confirmedSaleFor(Client $client, string $soldAt, string $effective): Sale
    {
        CurrentClinic::setId($this->clinic->id);

        return Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $client->id,
            'sold_by_user_id' => $this->admin->id,
            'sold_at' => $soldAt,
            'status' => Sale::STATUS_CONFIRMED,
            'expected_amount' => $effective,
            'effective_amount' => $effective,
        ]);
    }

    public function test_acquisition_metrics_ranks_origins_with_lifetime_conversion(): void
    {
        Sanctum::actingAs($this->admin);

        $instagram = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Instagram']);
        $facebook = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Facebook']);
        $igCampaign = Campaign::factory()->forOrigin($instagram)->create(['name' => 'Reels Set']);

        // Converted Instagram client — sale in the following month (lifetime).
        $igClient = $this->makeClient([
            'client_origin_id' => $instagram->id,
            'campaign_id' => $igCampaign->id,
            'initial_consultation_amount' => '150.00',
            'created_at' => '2026-09-10 10:00:00',
        ]);
        $this->confirmedSaleFor($igClient, '2026-10-05 14:00:00', '800.00');

        // Facebook client without sale.
        $this->makeClient([
            'client_origin_id' => $facebook->id,
            'initial_consultation_amount' => '100.00',
            'created_at' => '2026-09-12 11:00:00',
        ]);

        // Unattributed client with draft sale only — not converted.
        $plain = $this->makeClient([
            'initial_consultation_amount' => '50.00',
            'created_at' => '2026-09-15 09:00:00',
        ]);
        Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $plain->id,
            'sold_by_user_id' => $this->admin->id,
            'sold_at' => '2026-09-20 10:00:00',
            'status' => Sale::STATUS_DRAFT,
            'effective_amount' => '999.00',
        ]);

        // Outside period — ignored.
        $this->makeClient([
            'client_origin_id' => $instagram->id,
            'initial_consultation_amount' => '200.00',
            'created_at' => '2026-08-01 10:00:00',
        ]);

        $response = $this->getJson('/api/v1/metrics/acquisition?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.group_by', 'origin')
            ->assertJsonPath('data.conversion', 'lifetime')
            ->assertJsonPath('data.kpis.new_clients', 3)
            ->assertJsonPath('data.kpis.consultation_revenue', '300.00')
            ->assertJsonPath('data.kpis.converted_clients', 1)
            ->assertJsonPath('data.kpis.conversion_rate', '33.33')
            ->assertJsonPath('data.kpis.sales_revenue', '800.00')
            ->assertJsonPath('data.kpis.sales_count', 1);

        $breakdown = collect($response->json('data.breakdown'));
        $ig = $breakdown->firstWhere('label', 'Instagram');
        $fb = $breakdown->firstWhere('label', 'Facebook');
        $none = $breakdown->firstWhere('key', 'unattributed');

        $this->assertSame(1, $ig['new_clients']);
        $this->assertSame('150.00', $ig['consultation_revenue']);
        $this->assertSame(1, $ig['converted_clients']);
        $this->assertSame('100.00', $ig['conversion_rate']);
        $this->assertSame('800.00', $ig['sales_revenue']);
        $this->assertSame('5.33', $ig['sales_to_consultation_ratio']);

        $this->assertSame(1, $fb['new_clients']);
        $this->assertSame(0, $fb['converted_clients']);
        $this->assertSame('0.00', $fb['conversion_rate']);

        $this->assertSame(1, $none['new_clients']);
        $this->assertSame(0, $none['converted_clients']);
    }

    public function test_acquisition_metrics_group_by_campaign(): void
    {
        Sanctum::actingAs($this->admin);

        $origin = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Instagram']);
        $campaign = Campaign::factory()->forOrigin($origin)->create(['name' => 'Black Friday']);

        $client = $this->makeClient([
            'client_origin_id' => $origin->id,
            'campaign_id' => $campaign->id,
            'initial_consultation_amount' => '120.00',
            'created_at' => '2026-09-05 10:00:00',
        ]);
        $this->confirmedSaleFor($client, '2026-09-20 10:00:00', '500.00');

        $this->makeClient([
            'client_origin_id' => $origin->id,
            'campaign_id' => null,
            'initial_consultation_amount' => '80.00',
            'created_at' => '2026-09-06 10:00:00',
        ]);

        $response = $this->getJson('/api/v1/metrics/acquisition?from=2026-09-01&to=2026-09-30&group_by=campaign')
            ->assertOk()
            ->assertJsonPath('data.group_by', 'campaign')
            ->assertJsonPath('data.kpis.new_clients', 2)
            ->assertJsonPath('data.kpis.converted_clients', 1);

        $breakdown = collect($response->json('data.breakdown'));
        $bf = $breakdown->firstWhere('label', 'Black Friday');
        $none = $breakdown->firstWhere('key', 'unattributed');

        $this->assertSame('Instagram', $bf['origin_label']);
        $this->assertSame(1, $bf['new_clients']);
        $this->assertSame('500.00', $bf['sales_revenue']);
        $this->assertSame(1, $none['new_clients']);
        $this->assertNull($none['origin_label']);
    }

    public function test_acquisition_metrics_isolates_clinics_and_requires_permission(): void
    {
        Sanctum::actingAs($this->admin);
        $origin = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Local']);
        $this->makeClient([
            'client_origin_id' => $origin->id,
            'initial_consultation_amount' => '100.00',
            'created_at' => '2026-09-10 10:00:00',
        ]);

        $other = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        CurrentClinic::setId($other->id);
        $otherOrigin = ClientOrigin::factory()->forClinic($other)->create(['name' => 'Other']);
        Client::factory()->forClinic($other)->create([
            'client_origin_id' => $otherOrigin->id,
            'initial_consultation_amount' => '900.00',
            'created_at' => '2026-09-10 10:00:00',
        ]);

        CurrentClinic::setId($this->clinic->id);
        $this->getJson('/api/v1/metrics/acquisition?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.kpis.new_clients', 1)
            ->assertJsonPath('data.kpis.consultation_revenue', '100.00');

        Sanctum::actingAs($otherAdmin);
        CurrentClinic::setId($other->id);
        $this->getJson('/api/v1/metrics/acquisition?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.kpis.new_clients', 1)
            ->assertJsonPath('data.kpis.consultation_revenue', '900.00');

        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);
        CurrentClinic::setId($this->clinic->id);
        $this->getJson('/api/v1/metrics/acquisition?from=2026-09-01&to=2026-09-30')
            ->assertForbidden();
    }

    public function test_acquisition_metrics_validates_input(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/metrics/acquisition')
            ->assertUnprocessable();

        $this->getJson('/api/v1/metrics/acquisition?from=2026-09-30&to=2026-09-01')
            ->assertUnprocessable();

        $this->getJson('/api/v1/metrics/acquisition?from=2026-09-01&to=2026-09-30&group_by=channel')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['group_by']);
    }
}
