<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Clinic;
use App\Models\ProductType;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTypeTest extends TestCase
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

    public function test_store_requires_brand_id(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/product-types', [
            'name' => 'Botox',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['brand_id']);
    }

    public function test_can_create_type_for_brand_and_filter_by_brand_id(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);

        $allergan = Brand::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Allergan']);
        $galderma = Brand::factory()->create(['clinic_id' => $this->clinic->id, 'name' => 'Galderma']);

        $this->postJson('/api/v1/product-types', [
            'name' => 'Botox',
            'brand_id' => $allergan->id,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Botox')
            ->assertJsonPath('data.brand_id', $allergan->id);

        ProductType::factory()->create([
            'clinic_id' => $this->clinic->id,
            'brand_id' => $galderma->id,
            'name' => 'Preenchimento',
            'slug' => 'preenchimento',
        ]);

        $filtered = $this->getJson('/api/v1/product-types?brand_id='.$allergan->id)->assertOk();
        $names = collect($filtered->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Botox'));
        $this->assertFalse($names->contains('Preenchimento'));
    }

    public function test_viewer_can_list_types_for_product_form(): void
    {
        $viewer = User::factory()->forClinic($this->clinic)->create();
        $viewer->givePermissionTo('products.view');
        Sanctum::actingAs($viewer);
        CurrentClinic::setId($this->clinic->id);

        $brand = Brand::factory()->create(['clinic_id' => $this->clinic->id]);
        ProductType::factory()->create([
            'clinic_id' => $this->clinic->id,
            'brand_id' => $brand->id,
            'name' => 'Ácido',
            'slug' => 'acido',
        ]);

        $this->getJson('/api/v1/product-types?brand_id='.$brand->id)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ácido');
    }
}
