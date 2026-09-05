<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\CardBrand;
use App\Models\CardOperator;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleTest extends TestCase
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

    protected function makeProduct(string $name, string $cost, string $sale, ?string $minSale = null, string $stock = '100.0000'): Product
    {
        CurrentClinic::setId($this->clinic->id);

        return Product::factory()->forClinic($this->clinic)->create([
            'name' => $name,
            'product_type_id' => ProductType::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'brand_id' => Brand::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'unit_of_measure_id' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'cost' => $cost,
            'sale_price' => $sale,
            'min_sale_price' => $minSale,
            'stock_quantity' => $stock,
            'sku' => fake()->unique()->bothify('S-####'),
        ]);
    }

    protected function createDraftSale(): int
    {
        return $this->postJson('/api/v1/sales', [
            'client_id' => $this->client->id,
        ])->assertCreated()->json('data.id');
    }

    public function test_can_create_draft_and_apply_protocol(): void
    {
        Sanctum::actingAs($this->admin);

        $a = $this->makeProduct('Toxina', '10.0000', '40.00', '30.00');
        $b = $this->makeProduct('Acido', '20.0000', '60.00', '50.00');

        $protocolId = $this->postJson('/api/v1/protocols', [
            'name' => 'Full face',
            'items' => [
                ['product_id' => $a->id, 'quantity' => 2],
                ['product_id' => $b->id, 'quantity' => 1],
            ],
        ])->assertCreated()->json('data.id');

        $saleId = $this->createDraftSale();

        $response = $this->postJson("/api/v1/sales/{$saleId}/apply-protocol", [
            'protocol_id' => $protocolId,
        ])->assertOk();

        $response->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.expected_amount', '140.00')
            ->assertJsonPath('data.effective_amount', '140.00')
            ->assertJsonPath('data.min_amount', '110.00')
            ->assertJsonPath('data.is_below_minimum', false)
            ->assertJsonCount(2, 'data.items');

        $this->assertNotEmpty($response->json('data.protocol_references'));
        $this->assertSame('Full face', $response->json('data.protocol_references.0.name'));
    }

    public function test_sync_items_recalculates_expected_and_marks_below_min(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '10.0000', '100.00', '80.00');
        $saleId = $this->createDraftSale();

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50],
            ],
        ])->assertOk()
            ->assertJsonPath('data.expected_amount', '50.00')
            ->assertJsonPath('data.effective_amount', '50.00')
            ->assertJsonPath('data.min_amount', '80.00')
            ->assertJsonPath('data.is_below_minimum', true)
            ->assertJsonPath('data.items.0.is_below_minimum', true);
    }

    public function test_confirm_requires_flag_when_below_minimum(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '10.0000', '100.00', '80.00');
        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_sale',
            'kind' => PaymentMethod::KIND_PIX,
        ]);
        $saleId = $this->createDraftSale();

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50],
            ],
        ])->assertOk();

        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 50],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/sales/{$saleId}/confirm")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirm_below_minimum']);

        $this->postJson("/api/v1/sales/{$saleId}/confirm", [
            'confirm_below_minimum' => true,
        ])->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_confirm_requires_payments_to_match_effective_amount(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '10.0000', '100.00', '80.00');
        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_match',
            'kind' => PaymentMethod::KIND_PIX,
        ]);
        $saleId = $this->createDraftSale();

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 50],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/sales/{$saleId}/confirm")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payments']);

        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 100],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/sales/{$saleId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_confirm_does_not_change_product_stock(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Stocked', '10.0000', '100.00', '80.00', '55.0000');
        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_stock',
            'kind' => PaymentMethod::KIND_PIX,
        ]);
        $saleId = $this->createDraftSale();

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertOk();

        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 200],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        $this->assertSame('55.0000', $product->fresh()->stock_quantity);
    }

    public function test_card_payment_requires_meta(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '10.0000', '100.00', '80.00');
        $card = PaymentMethod::factory()->forClinic($this->clinic)->creditCard()->create();
        $saleId = $this->createDraftSale();

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk();

        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [
                ['payment_method_id' => $card->id, 'amount' => 100],
            ],
        ])->assertStatus(422);

        $operator = CardOperator::factory()->forClinic($this->clinic)->create();
        $brand = CardBrand::factory()->forClinic($this->clinic)->create();

        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [
                [
                    'payment_method_id' => $card->id,
                    'amount' => 100,
                    'card_operator_id' => $operator->id,
                    'card_brand_id' => $brand->id,
                    'installments' => 3,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.payments.0.installments', 3);
    }

    public function test_user_without_permission_cannot_create_sale(): void
    {
        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sales', [
            'client_id' => $this->client->id,
        ])->assertForbidden();
    }

    public function test_sales_are_isolated_by_clinic(): void
    {
        Sanctum::actingAs($this->admin);
        $this->createDraftSale();

        $other = Clinic::factory()->create();
        CurrentClinic::setId($other->id);
        $otherClient = Client::factory()->forClinic($other)->create();
        $otherUser = User::factory()->forClinic($other)->create();
        $otherUser->assignRole('admin');

        \App\Models\Sale::query()->create([
            'clinic_id' => $other->id,
            'client_id' => $otherClient->id,
            'sold_by_user_id' => $otherUser->id,
            'sold_at' => now(),
            'status' => 'draft',
        ]);

        CurrentClinic::setId($this->clinic->id);
        $response = $this->getJson('/api/v1/sales')->assertOk();
        $clinicIds = collect($response->json('data'))->pluck('clinic_id')->unique();
        $this->assertTrue($clinicIds->every(fn ($id) => (int) $id === $this->clinic->id));
    }

    public function test_cancel_preserves_history_and_confirmed_only_allows_notes(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '10.0000', '100.00', '80.00');
        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_cancel',
            'kind' => PaymentMethod::KIND_PIX,
        ]);
        $saleId = $this->createDraftSale();

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk();
        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [['payment_method_id' => $pix->id, 'amount' => 100]],
        ])->assertOk();
        $this->postJson("/api/v1/sales/{$saleId}/confirm")->assertOk();

        $this->patchJson("/api/v1/sales/{$saleId}", [
            'effective_amount' => 90,
        ])->assertStatus(422);

        $this->patchJson("/api/v1/sales/{$saleId}", [
            'notes' => 'Ajuste administrativo',
        ])->assertOk()
            ->assertJsonPath('data.notes', 'Ajuste administrativo');

        $this->postJson("/api/v1/sales/{$saleId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->patchJson("/api/v1/sales/{$saleId}", [
            'notes' => 'depois do cancelamento',
        ])->assertStatus(422);

        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'cancelled']);
    }

    public function test_manual_effective_amount_is_preserved_on_item_sync(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '10.0000', '100.00', '50.00');
        $saleId = $this->createDraftSale();

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.effective_amount', '100.00');

        $this->patchJson("/api/v1/sales/{$saleId}", [
            'effective_amount' => 90,
        ])->assertOk()
            ->assertJsonPath('data.effective_amount', '90.00')
            ->assertJsonPath('data.effective_amount_is_manual', true);

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertOk()
            ->assertJsonPath('data.expected_amount', '200.00')
            ->assertJsonPath('data.effective_amount', '90.00');
    }
}
