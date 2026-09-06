<?php

namespace Tests\Feature\Api\V1;

use App\Models\Appointment;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TreatmentAppointmentTest extends TestCase
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

    protected function makeProduct(string $name, string $cost, string $sale, string $stock = '100.0000'): Product
    {
        CurrentClinic::setId($this->clinic->id);

        return Product::factory()->forClinic($this->clinic)->create([
            'name' => $name,
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => $cost,
            'sale_price' => $sale,
            'min_sale_price' => $sale,
            'stock_quantity' => $stock,
            'sku' => fake()->unique()->bothify('T-####'),
        ]);
    }

    protected function createConfirmedSale(Product $product, float $qty = 3, ?float $unitPrice = null): Sale
    {
        Sanctum::actingAs($this->admin);

        $saleId = $this->postJson('/api/v1/sales', [
            'client_id' => $this->client->id,
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => $unitPrice ?? (float) $product->sale_price,
            ]],
        ])->assertOk();

        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_'.fake()->unique()->numerify('###'),
            'kind' => PaymentMethod::KIND_PIX,
        ]);

        $effective = $this->getJson("/api/v1/sales/{$saleId}")->json('data.effective_amount');

        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => (float) $effective],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        return Sale::query()->findOrFail($saleId);
    }

    protected function createAppointment(int $treatmentId, array $payload = []): int
    {
        return $this->postJson("/api/v1/treatments/{$treatmentId}/appointments", array_merge([
            'professional_user_id' => $this->admin->id,
        ], $payload))->assertCreated()->json('data.id');
    }

    public function test_open_treatment_from_confirmed_sale_only_once(): void
    {
        $product = $this->makeProduct('Botox', '10.0000', '100.00');
        $sale = $this->createConfirmedSale($product, 3);

        $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertCreated()
            ->assertJsonPath('data.sale_id', $sale->id)
            ->assertJsonPath('data.status', 'open');

        $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertStatus(422);
    }

    public function test_cannot_open_treatment_from_draft_sale(): void
    {
        Sanctum::actingAs($this->admin);
        $saleId = $this->postJson('/api/v1/sales', [
            'client_id' => $this->client->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/sales/{$saleId}/treatments")
            ->assertStatus(422);
    }

    public function test_session_flow_consumes_stock_and_tracks_remaining(): void
    {
        $product = $this->makeProduct('Botox', '10.0000', '100.00', '2.0000');
        $sale = $this->createConfirmedSale($product, 3);
        $saleItemId = $sale->items()->first()->id;

        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertCreated()
            ->json('data.id');

        $appointmentId = $this->createAppointment($treatmentId, [
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'notes' => '1a aplicacao',
        ]);

        $start = $this->postJson("/api/v1/appointments/{$appointmentId}/start")
            ->assertOk();

        $start->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.suggested_consumptions.0.quantity', '3.0000')
            ->assertJsonPath('data.stock_warnings.0.product_id', $product->id);

        $this->putJson("/api/v1/appointments/{$appointmentId}/consumptions", [
            'consumptions' => [[
                'source' => 'suggested',
                'product_id' => $product->id,
                'sale_item_id' => $saleItemId,
                'quantity' => 1,
            ]],
        ])->assertOk();

        $this->postJson("/api/v1/appointments/{$appointmentId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_cost', '10.0000');

        $this->assertSame('1.0000', $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reference_type' => Appointment::class,
            'reference_id' => $appointmentId,
        ]);

        $this->getJson("/api/v1/treatments/{$treatmentId}/fulfillment")
            ->assertOk()
            ->assertJsonPath('data.items.0.sold_quantity', '3.0000')
            ->assertJsonPath('data.items.0.consumed_quantity', '1.0000')
            ->assertJsonPath('data.items.0.remaining_quantity', '2.0000');

        // Second session
        $appt2 = $this->createAppointment($treatmentId, [
            'scheduled_at' => now()->addDays(30)->toIso8601String(),
        ]);

        $this->postJson("/api/v1/appointments/{$appt2}/start")
            ->assertOk()
            ->assertJsonPath('data.suggested_consumptions.0.quantity', '2.0000');
    }

    public function test_evaluation_appointment_can_complete_without_consumptions(): void
    {
        $product = $this->makeProduct('Botox', '10.0000', '100.00');
        $sale = $this->createConfirmedSale($product, 1);

        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertCreated()->json('data.id');

        $appointmentId = $this->createAppointment($treatmentId, [
            'notes' => 'Avaliacao',
        ]);

        $this->postJson("/api/v1/appointments/{$appointmentId}/start")->assertOk();
        $this->putJson("/api/v1/appointments/{$appointmentId}/consumptions", [
            'consumptions' => [],
        ])->assertOk();

        $this->postJson("/api/v1/appointments/{$appointmentId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_cost', '0.0000');

        $this->assertSame('100.0000', $product->fresh()->stock_quantity);
    }

    public function test_charged_extra_creates_sale_payment_and_complimentary_counts_cost(): void
    {
        $product = $this->makeProduct('Botox', '10.0000', '100.00', '50.0000');
        $extra = $this->makeProduct('Creme', '5.0000', '40.00', '20.0000');
        $sale = $this->createConfirmedSale($product, 1);
        $saleItemId = $sale->items()->first()->id;

        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_extra',
            'kind' => PaymentMethod::KIND_PIX,
        ]);

        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertCreated()->json('data.id');
        $appointmentId = $this->createAppointment($treatmentId);

        $this->postJson("/api/v1/appointments/{$appointmentId}/start")->assertOk();

        $this->putJson("/api/v1/appointments/{$appointmentId}/consumptions", [
            'consumptions' => [
                [
                    'source' => 'suggested',
                    'product_id' => $product->id,
                    'sale_item_id' => $saleItemId,
                    'quantity' => 1,
                ],
                [
                    'source' => 'extra',
                    'product_id' => $extra->id,
                    'quantity' => 1,
                    'is_complimentary' => true,
                ],
                [
                    'source' => 'extra',
                    'product_id' => $extra->id,
                    'quantity' => 1,
                    'is_complimentary' => false,
                    'charged_amount' => 40,
                    'payment' => [
                        'payment_method_id' => $pix->id,
                    ],
                ],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/appointments/{$appointmentId}/complete")
            ->assertOk()
            ->assertJsonPath('data.total_cost', '20.0000')
            ->assertJsonPath('data.total_charged_on_appointment', '40.00');

        $this->assertSame(1, SalePayment::query()->where('sale_id', $sale->id)->where('amount', '40.00')->count());
        // original sale payment + extra
        $this->assertGreaterThanOrEqual(2, SalePayment::query()->where('sale_id', $sale->id)->count());
        $this->assertSame('18.0000', $extra->fresh()->stock_quantity);
    }

    public function test_complete_allows_negative_stock(): void
    {
        $product = $this->makeProduct('Botox', '10.0000', '100.00', '0.0000');
        $sale = $this->createConfirmedSale($product, 1);
        $saleItemId = $sale->items()->first()->id;

        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertCreated()->json('data.id');
        $appointmentId = $this->createAppointment($treatmentId);
        $this->postJson("/api/v1/appointments/{$appointmentId}/start")->assertOk();
        $this->putJson("/api/v1/appointments/{$appointmentId}/consumptions", [
            'consumptions' => [[
                'source' => 'suggested',
                'product_id' => $product->id,
                'sale_item_id' => $saleItemId,
                'quantity' => 1,
            ]],
        ])->assertOk();

        $this->postJson("/api/v1/appointments/{$appointmentId}/complete")->assertOk();
        $this->assertSame('-1.0000', $product->fresh()->stock_quantity);
    }

    public function test_cancel_appointment_does_not_change_stock(): void
    {
        $product = $this->makeProduct('Botox', '10.0000', '100.00', '10.0000');
        $sale = $this->createConfirmedSale($product, 1);

        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertCreated()->json('data.id');
        $appointmentId = $this->createAppointment($treatmentId);
        $this->postJson("/api/v1/appointments/{$appointmentId}/start")->assertOk();

        $this->postJson("/api/v1/appointments/{$appointmentId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('10.0000', $product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_treatments_are_clinic_isolated_and_permission_gated(): void
    {
        $product = $this->makeProduct('Botox', '10.0000', '100.00');
        $sale = $this->createConfirmedSale($product, 1);
        $treatmentId = $this->postJson("/api/v1/sales/{$sale->id}/treatments")
            ->assertCreated()->json('data.id');

        $other = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        Sanctum::actingAs($otherAdmin);
        CurrentClinic::setId($other->id);

        $this->getJson("/api/v1/treatments/{$treatmentId}")->assertNotFound();

        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);
        CurrentClinic::setId($this->clinic->id);
        $this->getJson('/api/v1/treatments')->assertForbidden();
    }
}
