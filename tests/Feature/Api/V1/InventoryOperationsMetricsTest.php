<?php

namespace Tests\Feature\Api\V1;

use App\Enums\StockMovementType;
use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryOperationsMetricsTest extends TestCase
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

    protected function makeProduct(array $overrides = []): Product
    {
        CurrentClinic::setId($this->clinic->id);

        return Product::factory()->forClinic($this->clinic)->create(array_merge([
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => '10.0000',
            'sale_price' => '100.00',
            'stock_quantity' => '20.0000',
            'min_stock' => '5.0000',
            'lead_time_days' => 0,
            'sku' => fake()->unique()->bothify('D-####'),
            'is_active' => true,
        ], $overrides));
    }

    public function test_inventory_snapshot_and_consumption_in_window(): void
    {
        Sanctum::actingAs($this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));

        $low = $this->makeProduct([
            'name' => 'Low Stock',
            'stock_quantity' => '3.0000',
            'min_stock' => '5.0000',
            'cost' => '10.0000',
            'lead_time_days' => 60,
        ]);
        $this->makeProduct([
            'name' => 'Ok Stock',
            'stock_quantity' => '50.0000',
            'min_stock' => '5.0000',
            'cost' => '2.0000',
        ]);
        $this->makeProduct([
            'name' => 'Negative',
            'stock_quantity' => '-1.0000',
            'min_stock' => '0.0000',
            'cost' => '1.0000',
        ]);

        StockMovement::query()->create([
            'clinic_id' => $this->clinic->id,
            'product_id' => $low->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Out->value,
            'quantity' => '6.0000',
            'unit_cost' => '10.0000',
            'cost_before' => '10.0000',
            'cost_after' => '10.0000',
            'stock_before' => '9.0000',
            'stock_after' => '3.0000',
            'reason' => 'appointment_complete',
            'created_at' => '2026-09-10 10:00:00',
            'updated_at' => '2026-09-10 10:00:00',
        ]);

        $response = $this->getJson('/api/v1/metrics/inventory?from=2026-09-01&to=2026-09-15')
            ->assertOk()
            ->assertJsonPath('data.from', '2026-09-01')
            ->assertJsonPath('data.to', '2026-09-15')
            ->assertJsonPath('data.kpis.low_stock_count', 2)
            ->assertJsonPath('data.kpis.negative_stock_count', 1)
            ->assertJsonPath('data.kpis.consumption_quantity', '6.0000');

        // 3*10 + 50*2 + (-1)*1 = 30 + 100 - 1 = 129
        $this->assertSame('129.00', $response->json('data.kpis.inventory_value'));

        $names = collect($response->json('data.low_stock_products'))->pluck('name');
        $this->assertTrue($names->contains('Low Stock'));
        $this->assertTrue($names->contains('Negative'));
        $this->assertFalse($names->contains('Ok Stock'));

        $lowRow = collect($response->json('data.low_stock_products'))->firstWhere('name', 'Low Stock');
        $this->assertSame(60, $lowRow['lead_time_days']);
        // 6 consumed over 15 days => 0.4/day; coverage = 3/0.4 = 7.5
        $this->assertSame('7.50', $lowRow['coverage_days']);
    }

    public function test_inventory_defaults_to_last_30_days(): void
    {
        Sanctum::actingAs($this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-09-30 08:00:00'));

        $this->getJson('/api/v1/metrics/inventory')
            ->assertOk()
            ->assertJsonPath('data.from', '2026-09-01')
            ->assertJsonPath('data.to', '2026-09-30');
    }

    public function test_inventory_is_clinic_isolated_and_permission_gated(): void
    {
        Sanctum::actingAs($this->admin);
        $this->makeProduct([
            'name' => 'Clinic A Low',
            'stock_quantity' => '1.0000',
            'min_stock' => '5.0000',
        ]);

        $other = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        CurrentClinic::setId($other->id);
        Product::factory()->forClinic($other)->create([
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $other->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $other->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $other->id])->id,
            'name' => 'Clinic B Low',
            'stock_quantity' => '1.0000',
            'min_stock' => '5.0000',
            'sku' => fake()->unique()->bothify('B-####'),
        ]);

        CurrentClinic::setId($this->clinic->id);
        Sanctum::actingAs($this->admin);
        $names = collect($this->getJson('/api/v1/metrics/inventory')->assertOk()->json('data.low_stock_products'))
            ->pluck('name');
        $this->assertTrue($names->contains('Clinic A Low'));
        $this->assertFalse($names->contains('Clinic B Low'));

        $plain = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($plain);
        $this->getJson('/api/v1/metrics/inventory')->assertForbidden();
    }

    public function test_operations_sessions_cancellation_and_pending_fulfillment(): void
    {
        Sanctum::actingAs($this->admin);

        $product = $this->makeProduct(['stock_quantity' => '100.0000']);
        $sale = Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id,
            'sold_by_user_id' => $this->admin->id,
            'sold_at' => '2026-09-01 10:00:00',
            'status' => Sale::STATUS_CONFIRMED,
            'expected_amount' => '300.00',
            'effective_amount' => '300.00',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 3,
            'list_unit_price' => '100.00',
            'list_line_total' => '300.00',
            'unit_price' => '100.00',
            'line_total' => '300.00',
            'unit_cost' => '10.0000',
            'min_unit_price' => '50.00',
        ]);
        $treatment = Treatment::factory()->forClinic($this->clinic)->create([
            'sale_id' => $sale->id,
            'client_id' => $this->client->id,
            'opened_by_user_id' => $this->admin->id,
            'status' => Treatment::STATUS_OPEN,
        ]);

        Appointment::factory()->forClinic($this->clinic)->create([
            'treatment_id' => $treatment->id,
            'client_id' => $this->client->id,
            'professional_user_id' => $this->admin->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => '2026-09-10 09:00:00',
        ]);
        Appointment::factory()->forClinic($this->clinic)->create([
            'treatment_id' => $treatment->id,
            'client_id' => $this->client->id,
            'professional_user_id' => $this->admin->id,
            'status' => Appointment::STATUS_CANCELLED,
            'scheduled_at' => '2026-09-11 09:00:00',
        ]);
        $completed = Appointment::factory()->forClinic($this->clinic)->create([
            'treatment_id' => $treatment->id,
            'client_id' => $this->client->id,
            'professional_user_id' => $this->admin->id,
            'status' => Appointment::STATUS_COMPLETED,
            'scheduled_at' => '2026-09-12 09:00:00',
            'finished_at' => '2026-09-12 10:00:00',
            'total_cost' => '10.0000',
        ]);
        AppointmentConsumption::factory()->create([
            'appointment_id' => $completed->id,
            'product_id' => $product->id,
            'sale_item_id' => $sale->items()->first()->id,
            'source' => AppointmentConsumption::SOURCE_SUGGESTED,
            'quantity' => '1.0000',
            'unit_cost' => '10.0000',
            'line_cost' => '10.0000',
        ]);

        $response = $this->getJson('/api/v1/metrics/operations?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.sessions_by_status.scheduled', 1)
            ->assertJsonPath('data.sessions_by_status.cancelled', 1)
            ->assertJsonPath('data.sessions_by_status.completed', 1)
            ->assertJsonPath('data.kpis.sessions_total', 3)
            ->assertJsonPath('data.kpis.cancellation_rate', '33.33')
            ->assertJsonPath('data.kpis.pending_fulfillment_units', '2.0000')
            ->assertJsonPath('data.kpis.pending_fulfillment_treatments_count', 1)
            ->assertJsonPath('data.pending_fulfillments.0.treatment_id', $treatment->id)
            ->assertJsonPath('data.pending_fulfillments.0.remaining_units', '2.0000')
            ->assertJsonPath('data.by_professional.0.professional_user_id', $this->admin->id)
            ->assertJsonPath('data.by_professional.0.sessions_count', 1)
            ->assertJsonPath('data.by_professional.0.total_cost', '10.0000');
    }

    public function test_operations_requires_dates_and_permission(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/metrics/operations')->assertStatus(422);

        $plain = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($plain);
        $this->getJson('/api/v1/metrics/operations?from=2026-09-01&to=2026-09-30')->assertForbidden();
    }
}
