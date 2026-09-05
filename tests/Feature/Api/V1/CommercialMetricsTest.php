<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommercialMetricsTest extends TestCase
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

    protected function makeProduct(string $name = 'Produto'): Product
    {
        CurrentClinic::setId($this->clinic->id);

        return Product::factory()->forClinic($this->clinic)->create([
            'name' => $name,
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => '10.0000',
            'sale_price' => '100.00',
            'min_sale_price' => '50.00',
            'stock_quantity' => '100.0000',
            'sku' => fake()->unique()->bothify('M-####'),
        ]);
    }

    protected function confirmedSale(string $soldAt, string $effective, string $list = '100.00', string $offered = '80.00'): Sale
    {
        CurrentClinic::setId($this->clinic->id);

        $sale = Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id,
            'sold_by_user_id' => $this->admin->id,
            'sold_at' => $soldAt,
            'status' => Sale::STATUS_CONFIRMED,
            'expected_amount' => $offered,
            'effective_amount' => $effective,
        ]);

        $product = $this->makeProduct();

        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'list_unit_price' => $list,
            'list_line_total' => $list,
            'unit_price' => $offered,
            'line_total' => $offered,
            'unit_cost' => '10.0000',
            'min_unit_price' => '50.00',
            'source_protocol_id' => null,
        ]);

        return $sale;
    }

    public function test_commercial_metrics_aggregates_confirmed_sales_in_period(): void
    {
        Sanctum::actingAs($this->admin);

        $this->confirmedSale('2026-09-05 10:00:00', '200.00', '250.00', '200.00');
        $this->confirmedSale('2026-09-10 12:00:00', '100.00', '100.00', '100.00');
        $this->confirmedSale('2026-08-01 09:00:00', '999.00');

        Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id,
            'sold_by_user_id' => $this->admin->id,
            'sold_at' => '2026-09-08 11:00:00',
            'status' => Sale::STATUS_DRAFT,
            'effective_amount' => '500.00',
        ]);

        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'name' => 'PIX',
            'code' => 'pix',
            'kind' => PaymentMethod::KIND_PIX,
        ]);
        $cash = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'name' => 'Dinheiro',
            'code' => 'cash',
            'kind' => PaymentMethod::KIND_CASH,
        ]);

        $saleA = Sale::query()->where('effective_amount', '200.00')->where('status', Sale::STATUS_CONFIRMED)->firstOrFail();
        $saleB = Sale::query()->where('effective_amount', '100.00')->where('status', Sale::STATUS_CONFIRMED)->firstOrFail();

        SalePayment::factory()->create([
            'sale_id' => $saleA->id,
            'payment_method_id' => $pix->id,
            'amount' => '200.00',
        ]);
        SalePayment::factory()->create([
            'sale_id' => $saleB->id,
            'payment_method_id' => $cash->id,
            'amount' => '100.00',
        ]);

        Budget::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id,
            'created_by_user_id' => $this->admin->id,
            'status' => Budget::STATUS_SENT,
            'created_at' => '2026-09-03 10:00:00',
            'sent_at' => '2026-09-03 10:00:00',
        ]);
        Budget::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id,
            'created_by_user_id' => $this->admin->id,
            'status' => Budget::STATUS_ACCEPTED,
            'created_at' => '2026-09-04 10:00:00',
            'sent_at' => '2026-09-04 10:00:00',
            'accepted_at' => '2026-09-05 10:00:00',
        ]);

        $response = $this->getJson('/api/v1/metrics/commercial?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.date_field', 'sold_at')
            ->assertJsonPath('data.granularity', 'day')
            ->assertJsonPath('data.kpis.revenue', '300.00')
            ->assertJsonPath('data.kpis.sales_count', 2)
            ->assertJsonPath('data.kpis.ticket_avg', '150.00')
            ->assertJsonPath('data.kpis.avg_discount_percent', '14.29')
            ->assertJsonPath('data.budget_funnel.sent_in_period', 2)
            ->assertJsonPath('data.budget_funnel.accepted_in_period', 1)
            ->assertJsonPath('data.budget_funnel.acceptance_rate', '50.00');

        $mix = collect($response->json('data.payment_mix'));
        $this->assertSame('200.00', $mix->firstWhere('kind', 'pix')['amount']);
        $this->assertSame('100.00', $mix->firstWhere('kind', 'cash')['amount']);

        $series = collect($response->json('data.series'));
        $this->assertCount(30, $series);
        $this->assertSame('200.00', $series->firstWhere('period', '2026-09-05')['revenue']);
        $this->assertSame('100.00', $series->firstWhere('period', '2026-09-10')['revenue']);
        $this->assertSame('0.00', $series->firstWhere('period', '2026-09-01')['revenue']);
    }

    public function test_commercial_metrics_isolates_clinics_and_requires_permission(): void
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
        $this->getJson('/api/v1/metrics/commercial?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.kpis.revenue', '200.00')
            ->assertJsonPath('data.kpis.sales_count', 1);

        Sanctum::actingAs($otherAdmin);
        CurrentClinic::setId($other->id);
        $this->getJson('/api/v1/metrics/commercial?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.kpis.revenue', '900.00');

        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);
        CurrentClinic::setId($this->clinic->id);
        $this->getJson('/api/v1/metrics/commercial?from=2026-09-01&to=2026-09-30')
            ->assertForbidden();
    }

    public function test_commercial_metrics_granularity_defaults_and_override(): void
    {
        Sanctum::actingAs($this->admin);
        $this->confirmedSale('2026-01-15 10:00:00', '50.00');
        $this->confirmedSale('2026-03-20 10:00:00', '70.00');

        $quarter = $this->getJson('/api/v1/metrics/commercial?from=2026-01-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.granularity', 'week')
            ->assertJsonPath('data.kpis.revenue', '120.00');

        $this->assertGreaterThan(8, count($quarter->json('data.series')));

        $year = $this->getJson('/api/v1/metrics/commercial?from=2025-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.granularity', 'month')
            ->assertJsonPath('data.kpis.revenue', '120.00');

        $this->assertCount(24, $year->json('data.series'));

        $forced = $this->getJson('/api/v1/metrics/commercial?from=2026-01-01&to=2026-03-31&granularity=month')
            ->assertOk()
            ->assertJsonPath('data.granularity', 'month');

        $this->assertCount(3, $forced->json('data.series'));
        $this->assertSame('50.00', collect($forced->json('data.series'))->firstWhere('period', '2026-01-01')['revenue']);
        $this->assertSame('70.00', collect($forced->json('data.series'))->firstWhere('period', '2026-03-01')['revenue']);
    }

    public function test_commercial_metrics_validates_period(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/metrics/commercial')
            ->assertUnprocessable();

        $this->getJson('/api/v1/metrics/commercial?from=2026-09-30&to=2026-09-01')
            ->assertUnprocessable();

        $this->getJson('/api/v1/metrics/commercial?from=2020-01-01&to=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }
}
