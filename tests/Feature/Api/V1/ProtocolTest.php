<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Clinic;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Protocol;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProtocolTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    protected function makeProduct(string $name, string $cost, string $sale, ?string $minSale = null): Product
    {
        CurrentClinic::setId($this->clinic->id);

        $type = ProductType::factory()->create(['clinic_id' => $this->clinic->id]);
        $brand = Brand::factory()->create(['clinic_id' => $this->clinic->id]);
        $unit = UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id]);

        return Product::factory()->forClinic($this->clinic)->create([
            'name' => $name,
            'product_type_id' => $type->id,
            'brand_id' => $brand->id,
            'unit_of_measure_id' => $unit->id,
            'cost' => $cost,
            'sale_price' => $sale,
            'min_sale_price' => $minSale,
            'stock_quantity' => '100.0000',
            'sku' => fake()->unique()->bothify('P-####'),
        ]);
    }

    public function test_protocol_calculates_cost_suggested_and_min_from_products(): void
    {
        Sanctum::actingAs($this->admin);

        $a = $this->makeProduct('Toxina', '10.0000', '40.00', '30.00');
        $b = $this->makeProduct('Acido', '20.0000', '60.00', '50.00');

        $response = $this->postJson('/api/v1/protocols', [
            'name' => 'Full face',
            'description' => 'Serviço completo',
            'special_price' => 85,
            'items' => [
                ['product_id' => $a->id, 'quantity' => 2],
                ['product_id' => $b->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Full face')
            // cost: 10*2 + 20*1 = 40
            ->assertJsonPath('data.total_cost', '40.0000')
            // sale: 40*2 + 60*1 = 140
            ->assertJsonPath('data.products_sale_total', '140.00')
            ->assertJsonPath('data.suggested_price', '140.00')
            // min: 30*2 + 50*1 = 110
            ->assertJsonPath('data.min_price', '110.00')
            ->assertJsonPath('data.special_price', '85.00')
            ->assertJsonPath('data.margin_at_suggested', '100.0000')
            ->assertJsonPath('data.margin_at_special', '45.0000');
    }

    public function test_manual_suggested_price_is_preserved_on_item_sync(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '5.0000', '25.00', '15.00');

        $create = $this->postJson('/api/v1/protocols', [
            'name' => 'Manual price protocol',
            'suggested_price' => 99,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->assertTrue($create->json('data.suggested_price_is_manual'));
        $this->assertSame('99.00', $create->json('data.suggested_price'));

        $this->putJson("/api/v1/protocols/{$id}/items", [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertOk()
            ->assertJsonPath('data.suggested_price', '99.00')
            ->assertJsonPath('data.total_cost', '10.0000')
            ->assertJsonPath('data.products_sale_total', '50.00');
    }

    public function test_recalculate_resets_suggested_from_products(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Item', '5.0000', '25.00', '15.00');

        $id = $this->postJson('/api/v1/protocols', [
            'name' => 'Reset protocol',
            'suggested_price' => 99,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->json('data.id');

        $this->postJson("/api/v1/protocols/{$id}/recalculate")
            ->assertOk()
            ->assertJsonPath('data.suggested_price', '25.00')
            ->assertJsonPath('data.suggested_price_is_manual', false)
            ->assertJsonPath('data.min_price', '15.00');
    }

    public function test_protocols_are_clinic_scoped(): void
    {
        Sanctum::actingAs($this->admin);
        $product = $this->makeProduct('Local', '1.0000', '2.00');

        $this->postJson('/api/v1/protocols', [
            'name' => 'Mine',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $other = Clinic::factory()->create();
        Protocol::factory()->create(['clinic_id' => $other->id, 'name' => 'Theirs']);

        $names = collect($this->getJson('/api/v1/protocols')->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Mine'));
        $this->assertFalse($names->contains('Theirs'));
    }
}
