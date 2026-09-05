<?php

namespace Tests\Feature\Api\V1;

use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\Treatment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarginMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
        $this->client = Client::factory()->forClinic($this->clinic)->create();
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function confirmedSale(string $soldAt, string $effective): Sale
    {
        CurrentClinic::setId($this->clinic->id);

        return Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id,
            'sold_by_user_id' => $this->admin->id,
            'sold_at' => $soldAt,
            'status' => Sale::STATUS_CONFIRMED,
            'expected_amount' => $effective,
            'effective_amount' => $effective,
        ]);
    }

    protected function openTreatment(Sale $sale): Treatment
    {
        CurrentClinic::setId($this->clinic->id);

        return Treatment::factory()->forClinic($this->clinic)->create([
            'sale_id' => $sale->id,
            'client_id' => $sale->client_id,
            'opened_by_user_id' => $this->admin->id,
            'status' => Treatment::STATUS_OPEN,
            'total_cost' => '0.0000',
        ]);
    }

    protected function completedAppointment(
        Treatment $treatment,
        string $finishedAt,
        string $totalCost,
        string $chargedExtras = '0.00',
    ): Appointment {
        CurrentClinic::setId($this->clinic->id);

        return Appointment::factory()->forClinic($this->clinic)->create([
            'treatment_id' => $treatment->id,
            'client_id' => $treatment->client_id,
            'professional_user_id' => $this->admin->id,
            'status' => Appointment::STATUS_COMPLETED,
            'scheduled_at' => $finishedAt,
            'started_at' => $finishedAt,
            'finished_at' => $finishedAt,
            'total_cost' => $totalCost,
            'total_charged_on_appointment' => $chargedExtras,
        ]);
    }

    public function test_period_mode_separates_sale_month_from_appointment_month(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->confirmedSale('2026-09-10 10:00:00', '1000.00');
        $treatment = $this->openTreatment($sale);
        $this->completedAppointment($treatment, '2026-10-05 14:00:00', '300.0000');

        // Draft sale in September must be ignored.
        Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id,
            'sold_by_user_id' => $this->admin->id,
            'sold_at' => '2026-09-15 10:00:00',
            'status' => Sale::STATUS_DRAFT,
            'effective_amount' => '500.00',
        ]);

        $this->getJson('/api/v1/metrics/margin?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.mode', 'period')
            ->assertJsonPath('data.kpis.sale_revenue', '1000.00')
            ->assertJsonPath('data.kpis.extras_revenue', '0.00')
            ->assertJsonPath('data.kpis.revenue', '1000.00')
            ->assertJsonPath('data.kpis.clinical_cost', '0.00')
            ->assertJsonPath('data.kpis.gross_margin', '1000.00')
            ->assertJsonPath('data.kpis.margin_percent', '100.00')
            ->assertJsonPath('data.kpis.pending_fulfillment_count', null);

        $this->getJson('/api/v1/metrics/margin?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.kpis.sale_revenue', '0.00')
            ->assertJsonPath('data.kpis.clinical_cost', '300.00')
            ->assertJsonPath('data.kpis.gross_margin', '-300.00')
            ->assertJsonPath('data.kpis.margin_percent', null);
    }

    public function test_cohort_sale_includes_later_appointment_costs(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->confirmedSale('2026-09-10 10:00:00', '1000.00');
        $treatment = $this->openTreatment($sale);
        $this->completedAppointment($treatment, '2026-10-05 14:00:00', '250.0000', '40.00');

        $this->getJson('/api/v1/metrics/margin?from=2026-09-01&to=2026-09-30&mode=cohort_sale')
            ->assertOk()
            ->assertJsonPath('data.mode', 'cohort_sale')
            ->assertJsonPath('data.kpis.sale_revenue', '1000.00')
            ->assertJsonPath('data.kpis.extras_revenue', '40.00')
            ->assertJsonPath('data.kpis.revenue', '1040.00')
            ->assertJsonPath('data.kpis.clinical_cost', '250.00')
            ->assertJsonPath('data.kpis.gross_margin', '790.00')
            ->assertJsonPath('data.kpis.margin_percent', '75.96')
            ->assertJsonPath('data.kpis.pending_fulfillment_count', 1);
    }

    public function test_courtesy_and_charged_extras_affect_margin(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = $this->confirmedSale('2026-09-05 10:00:00', '500.00');
        $treatment = $this->openTreatment($sale);
        $appointment = $this->completedAppointment($treatment, '2026-09-20 11:00:00', '120.0000', '50.00');

        $product = Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => '20.0000',
            'sale_price' => '50.00',
            'sku' => fake()->unique()->bothify('M-####'),
        ]);

        AppointmentConsumption::factory()->create([
            'appointment_id' => $appointment->id,
            'product_id' => $product->id,
            'source' => AppointmentConsumption::SOURCE_EXTRA,
            'quantity' => '1.0000',
            'is_complimentary' => true,
            'charged_amount' => '0.00',
            'unit_cost' => '20.0000',
            'line_cost' => '20.0000',
        ]);

        AppointmentConsumption::factory()->create([
            'appointment_id' => $appointment->id,
            'product_id' => $product->id,
            'source' => AppointmentConsumption::SOURCE_SUGGESTED,
            'quantity' => '1.0000',
            'is_complimentary' => false,
            'charged_amount' => '0.00',
            'unit_cost' => '100.0000',
            'line_cost' => '100.0000',
        ]);

        $this->getJson('/api/v1/metrics/margin?from=2026-09-01&to=2026-09-30&mode=period')
            ->assertOk()
            ->assertJsonPath('data.kpis.sale_revenue', '500.00')
            ->assertJsonPath('data.kpis.extras_revenue', '50.00')
            ->assertJsonPath('data.kpis.revenue', '550.00')
            ->assertJsonPath('data.kpis.clinical_cost', '120.00')
            ->assertJsonPath('data.kpis.courtesy_cost', '20.00')
            ->assertJsonPath('data.kpis.gross_margin', '430.00');
    }

    public function test_margin_metrics_isolates_clinics_and_requires_permission(): void
    {
        Sanctum::actingAs($this->admin);
        $this->confirmedSale('2026-09-05 10:00:00', '200.00');

        $other = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        $otherClient = Client::factory()->forClinic($other)->create();

        CurrentClinic::setId($other->id);
        Sale::factory()->forClinic($other)->create([
            'client_id' => $otherClient->id,
            'sold_by_user_id' => $otherAdmin->id,
            'sold_at' => '2026-09-05 10:00:00',
            'status' => Sale::STATUS_CONFIRMED,
            'effective_amount' => '900.00',
        ]);

        CurrentClinic::setId($this->clinic->id);
        $this->getJson('/api/v1/metrics/margin?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.kpis.sale_revenue', '200.00');

        Sanctum::actingAs($otherAdmin);
        CurrentClinic::setId($other->id);
        $this->getJson('/api/v1/metrics/margin?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.kpis.sale_revenue', '900.00');

        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);
        CurrentClinic::setId($this->clinic->id);
        $this->getJson('/api/v1/metrics/margin?from=2026-09-01&to=2026-09-30')
            ->assertForbidden();
    }

    public function test_margin_metrics_validates_input(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/metrics/margin')
            ->assertUnprocessable();

        $this->getJson('/api/v1/metrics/margin?from=2026-09-30&to=2026-09-01')
            ->assertUnprocessable();

        $this->getJson('/api/v1/metrics/margin?from=2026-09-01&to=2026-09-30&mode=cash')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mode']);
    }
}
