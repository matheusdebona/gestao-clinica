<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Budget;
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

class BudgetTest extends TestCase
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

    protected function makeProduct(string $name, string $cost, string $sale, ?string $minSale = null): Product
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
            'stock_quantity' => '100.0000',
            'sku' => fake()->unique()->bothify('B-####'),
        ]);
    }

    protected function createSaleWithItem(Product $product, float $qty = 1, ?float $unitPrice = null): int
    {
        $saleId = $this->postJson('/api/v1/sales', [
            'client_id' => $this->client->id,
        ])->assertCreated()->json('data.id');

        $item = [
            'product_id' => $product->id,
            'quantity' => $qty,
        ];
        if ($unitPrice !== null) {
            $item['unit_price'] = $unitPrice;
        }

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [$item],
        ])->assertOk();

        return $saleId;
    }

    public function test_sale_items_snapshot_list_price_and_product_name(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Botox 100U', '10.0000', '100.00', '80.00');
        $saleId = $this->createSaleWithItem($product, 2, 90);

        $this->getJson("/api/v1/sales/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'Botox 100U')
            ->assertJsonPath('data.items.0.list_unit_price', '100.00')
            ->assertJsonPath('data.items.0.list_line_total', '200.00')
            ->assertJsonPath('data.items.0.unit_price', '90.00')
            ->assertJsonPath('data.items.0.line_total', '180.00');

        $product->update(['sale_price' => '150.00', 'name' => 'Botox Novo']);

        $this->getJson("/api/v1/sales/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'Botox 100U')
            ->assertJsonPath('data.items.0.list_unit_price', '100.00');
    }

    public function test_can_create_budget_snapshot_from_draft_sale(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Botox', '10.0000', '100.00', '80.00');
        $saleId = $this->createSaleWithItem($product, 1, 90);

        $response = $this->postJson("/api/v1/sales/{$saleId}/budgets", [
            'notes' => 'Proposta inicial',
        ])->assertCreated();

        $response->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.sale_id', $saleId)
            ->assertJsonPath('data.expected_amount', '90.00')
            ->assertJsonPath('data.effective_amount', '90.00')
            ->assertJsonPath('data.items.0.product_name', 'Botox')
            ->assertJsonPath('data.items.0.list_unit_price', '100.00')
            ->assertJsonPath('data.items.0.unit_price', '90.00');
    }

    public function test_second_budget_version_supersedes_previous_draft_or_sent(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '10.0000', '100.00', '80.00');
        $saleId = $this->createSaleWithItem($product);

        $firstId = $this->postJson("/api/v1/sales/{$saleId}/budgets")
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/budgets/{$firstId}/send")->assertOk();

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 95],
            ],
        ])->assertOk();

        $second = $this->postJson("/api/v1/sales/{$saleId}/budgets")
            ->assertCreated();

        $second->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.expected_amount', '190.00');

        $this->assertDatabaseHas('budgets', [
            'id' => $firstId,
            'status' => 'superseded',
        ]);

        $this->getJson("/api/v1/budgets/{$firstId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', '100.00')
            ->assertJsonPath('data.items.0.quantity', '1.0000');
    }

    public function test_accept_requires_sent_and_allows_only_one_accepted(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '10.0000', '100.00', '80.00');
        $saleId = $this->createSaleWithItem($product);
        $budgetId = $this->postJson("/api/v1/sales/{$saleId}/budgets")
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/budgets/{$budgetId}/accept")
            ->assertStatus(422);

        $this->postJson("/api/v1/budgets/{$budgetId}/send")->assertOk();
        $this->postJson("/api/v1/budgets/{$budgetId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $otherId = $this->postJson("/api/v1/sales/{$saleId}/budgets")
            ->assertCreated()
            ->json('data.id');
        $this->postJson("/api/v1/budgets/{$otherId}/send")->assertOk();
        $this->postJson("/api/v1/budgets/{$otherId}/accept")
            ->assertStatus(422);
    }

    public function test_after_accept_sale_remains_draft_and_can_be_confirmed(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item2', '10.0000', '100.00', '80.00');
        $saleId = $this->createSaleWithItem($product);
        $stockBefore = $product->fresh()->stock_quantity;

        $budgetId = $this->postJson("/api/v1/sales/{$saleId}/budgets")->json('data.id');
        $this->postJson("/api/v1/budgets/{$budgetId}/send")->assertOk();
        $this->postJson("/api/v1/budgets/{$budgetId}/accept")->assertOk();

        $this->getJson("/api/v1/sales/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $pix = PaymentMethod::factory()->forClinic($this->clinic)->create([
            'code' => 'pix_budget',
            'kind' => PaymentMethod::KIND_PIX,
        ]);

        $this->putJson("/api/v1/sales/{$saleId}/payments", [
            'payments' => [
                ['payment_method_id' => $pix->id, 'amount' => 100],
            ],
        ])->assertOk();

        $this->postJson("/api/v1/sales/{$saleId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame($stockBefore, $product->fresh()->stock_quantity);
    }

    public function test_edit_sale_after_budget_does_not_change_budget_items(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '10.0000', '100.00', '80.00');
        $saleId = $this->createSaleWithItem($product, 1, 100);
        $budgetId = $this->postJson("/api/v1/sales/{$saleId}/budgets")->json('data.id');

        $this->putJson("/api/v1/sales/{$saleId}/items", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 70],
            ],
        ])->assertOk();

        $this->getJson("/api/v1/budgets/{$budgetId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', '1.0000')
            ->assertJsonPath('data.items.0.unit_price', '100.00');
    }

    public function test_user_without_permission_cannot_create_budget(): void
    {
        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);

        $sale = \App\Models\Sale::factory()->forClinic($this->clinic)->create([
            'client_id' => $this->client->id,
            'sold_by_user_id' => $user->id,
        ]);

        $this->postJson("/api/v1/sales/{$sale->id}/budgets")->assertForbidden();
    }

    public function test_budgets_are_isolated_by_clinic(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Local', '10.0000', '100.00', '80.00');
        $saleId = $this->createSaleWithItem($product);
        $this->postJson("/api/v1/sales/{$saleId}/budgets")->assertCreated();

        $other = Clinic::factory()->create();
        CurrentClinic::setId($other->id);
        $otherClient = Client::factory()->forClinic($other)->create();
        $otherUser = User::factory()->forClinic($other)->create();
        $otherUser->assignRole('admin');
        $otherSale = \App\Models\Sale::factory()->forClinic($other)->create([
            'client_id' => $otherClient->id,
            'sold_by_user_id' => $otherUser->id,
        ]);
        Budget::factory()->create([
            'clinic_id' => $other->id,
            'sale_id' => $otherSale->id,
            'client_id' => $otherClient->id,
            'created_by_user_id' => $otherUser->id,
            'version' => 1,
        ]);

        CurrentClinic::setId($this->clinic->id);
        Sanctum::actingAs($this->admin);
        $ids = collect($this->getJson('/api/v1/budgets')->assertOk()->json('data'))
            ->pluck('clinic_id')
            ->unique();

        $this->assertTrue($ids->every(fn ($id) => (int) $id === $this->clinic->id));
    }
}
