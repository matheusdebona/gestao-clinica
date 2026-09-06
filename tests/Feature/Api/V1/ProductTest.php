<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Clinic;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
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

    protected function catalog(): array
    {
        CurrentClinic::setId($this->clinic->id);

        $brand = Brand::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Allergan']);

        return [
            'type' => ProductType::factory()->create([
                'clinic_id' => $this->clinic->id,
                'brand_id' => $brand->id,
                'name' => 'Botox',
                'slug' => 'botox',
            ]),
            'brand' => $brand,
            'unit' => UnitOfMeasure::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Unidade', 'symbol' => 'un']),
        ];
    }

    public function test_can_create_product_with_opening_stock_and_margin_fields(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Botox 100U',
            'sku' => 'BTX-100',
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'purpose' => 'Aplicação facial',
            'cost' => 50,
            'sale_price' => 120,
            'min_sale_price' => 100,
            'stock_quantity' => 10,
            'min_stock' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Botox 100U')
            ->assertJsonPath('data.cost', '50.0000')
            ->assertJsonPath('data.stock_quantity', '10.0000')
            ->assertJsonPath('data.unit_margin', '70.0000')
            ->assertJsonPath('data.inventory_value', '500.0000')
            ->assertJsonPath('data.is_low_stock', false);

        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_inbound_recalculates_weighted_average_cost(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        $product = Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'cost' => '10.0000',
            'stock_quantity' => '10.0000',
            'sale_price' => '50.00',
        ]);

        $this->postJson("/api/v1/products/{$product->id}/stock-movements", [
            'type' => 'in',
            'quantity' => 10,
            'unit_cost' => 20,
            'reason' => 'purchase',
        ])->assertOk()
            ->assertJsonPath('data.product.cost', '15.0000')
            ->assertJsonPath('data.product.stock_quantity', '20.0000');
    }

    public function test_low_stock_filter(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'name' => 'Low stock item',
            'stock_quantity' => '1.0000',
            'min_stock' => '5.0000',
            'sku' => 'LOW-1',
        ]);

        Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'name' => 'Ok stock item',
            'stock_quantity' => '20.0000',
            'min_stock' => '5.0000',
            'sku' => 'OK-1',
        ]);

        $response = $this->getJson('/api/v1/products?low_stock=1')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Low stock item'));
        $this->assertFalse($names->contains('Ok stock item'));
    }

    public function test_products_are_isolated_by_clinic(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'name' => 'Mine',
            'sku' => 'MINE-1',
        ]);

        $other = Clinic::factory()->create();
        $otherType = ProductType::factory()->create(['clinic_id' => $other->id]);
        $otherBrand = Brand::factory()->create(['clinic_id' => $other->id]);
        $otherUnit = UnitOfMeasure::factory()->create(['clinic_id' => $other->id]);

        Product::query()->create([
            'clinic_id' => $other->id,
            'product_type_id' => $otherType->id,
            'brand_id' => $otherBrand->id,
            'unit_of_measure_id' => $otherUnit->id,
            'name' => 'Theirs',
            'sku' => 'THEIRS-1',
            'cost' => 1,
            'sale_price' => 2,
            'stock_quantity' => 1,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/products')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Mine'));
        $this->assertFalse($names->contains('Theirs'));
    }

    public function test_user_without_permission_cannot_create_product(): void
    {
        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/products', [
            'name' => 'X',
            'product_type_id' => 1,
            'brand_id' => 1,
            'unit_of_measure_id' => 1,
            'sale_price' => 10,
        ])->assertForbidden();
    }

    public function test_can_create_and_update_lead_time_days(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        $created = $this->postJson('/api/v1/products', [
            'name' => 'Filler 1ml',
            'sku' => 'FIL-1',
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'sale_price' => 200,
            'stock_quantity' => 5,
            'min_stock' => 2,
            'lead_time_days' => 60,
        ])->assertCreated()
            ->assertJsonPath('data.lead_time_days', 60);

        $productId = $created->json('data.id');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'lead_time_days' => 60,
        ]);

        $this->putJson("/api/v1/products/{$productId}", [
            'lead_time_days' => 45,
        ])->assertOk()
            ->assertJsonPath('data.lead_time_days', 45);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'lead_time_days' => 45,
        ]);
    }

    public function test_lead_time_days_defaults_to_zero_when_omitted(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        $this->postJson('/api/v1/products', [
            'name' => 'Toxin',
            'sku' => 'TOX-1',
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'sale_price' => 150,
            'stock_quantity' => 3,
            'min_stock' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.lead_time_days', 0);
    }

    public function test_lead_time_days_rejects_negative_values(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        $this->postJson('/api/v1/products', [
            'name' => 'Acid',
            'sku' => 'ACD-1',
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'sale_price' => 80,
            'lead_time_days' => -1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lead_time_days']);
    }

    public function test_rejects_product_when_type_does_not_belong_to_brand(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();
        $otherBrand = Brand::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Galderma']);

        $this->postJson('/api/v1/products', [
            'name' => 'Mismatch',
            'sku' => 'MIS-1',
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $otherBrand->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'sale_price' => 80,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_type_id']);
    }

    public function test_update_rejects_type_from_another_brand(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        $created = $this->postJson('/api/v1/products', [
            'name' => 'Botox 50U',
            'sku' => 'BTX-50',
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'sale_price' => 90,
        ])->assertCreated();

        $otherBrand = Brand::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Galderma']);
        $otherType = ProductType::factory()->create([
            'clinic_id' => $this->clinic->id,
            'brand_id' => $otherBrand->id,
            'name' => 'Preenchimento',
            'slug' => 'preenchimento',
        ]);

        $this->putJson('/api/v1/products/'.$created->json('data.id'), [
            'product_type_id' => $otherType->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_type_id']);
    }

    public function test_can_search_products_by_name_or_sku(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'name' => 'Botox 100U',
            'sku' => 'BTX-100',
        ]);

        Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'name' => 'Ácido hialurônico',
            'sku' => 'ACD-SK',
        ]);

        $byName = $this->getJson('/api/v1/products?q=Botox')->assertOk();
        $this->assertSame(['Botox 100U'], collect($byName->json('data'))->pluck('name')->all());

        $bySku = $this->getJson('/api/v1/products?q=ACD-SK')->assertOk();
        $this->assertSame(['Ácido hialurônico'], collect($bySku->json('data'))->pluck('name')->all());
    }

    public function test_can_filter_products_by_active_flag(): void
    {
        Sanctum::actingAs($this->admin);
        $catalog = $this->catalog();

        Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'name' => 'Ativo',
            'sku' => 'ACT-1',
            'is_active' => true,
        ]);

        Product::factory()->forClinic($this->clinic)->create([
            'product_type_id' => $catalog['type']->id,
            'brand_id' => $catalog['brand']->id,
            'unit_of_measure_id' => $catalog['unit']->id,
            'name' => 'Inativo',
            'sku' => 'INA-1',
            'is_active' => false,
        ]);

        $active = $this->getJson('/api/v1/products?is_active=1')->assertOk();
        $this->assertSame(['Ativo'], collect($active->json('data'))->pluck('name')->all());

        $inactive = $this->getJson('/api/v1/products?is_active=0')->assertOk();
        $this->assertSame(['Inativo'], collect($inactive->json('data'))->pluck('name')->all());
    }
}
