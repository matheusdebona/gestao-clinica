<?php

namespace Tests\Feature\Api\V1;

use App\Models\Clinic;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultUnitsOfMeasure;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitOfMeasureDefaultsTest extends TestCase
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

    public function test_product_catalog_seeder_creates_defaults_for_all_clinics(): void
    {
        $other = Clinic::factory()->create();

        $this->seed(ProductCatalogSeeder::class);

        $this->assertDefaultUnitsForClinic($this->clinic->id);
        $this->assertDefaultUnitsForClinic($other->id);
    }

    public function test_creating_clinic_seeds_default_units_of_measure(): void
    {
        $manager = User::factory()->forClinic($this->clinic)->create();
        $manager->givePermissionTo('clinics.manage');
        Sanctum::actingAs($manager);

        $id = $this->postJson('/api/v1/clinics', [
            'name' => 'Clínica Recém-aberta',
        ])->assertCreated()
            ->json('data.id');

        $this->assertDefaultUnitsForClinic((int) $id);
    }

    public function test_default_units_are_idempotent_by_symbol(): void
    {
        UnitOfMeasure::factory()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Unidade da casa',
            'symbol' => 'un',
        ]);

        EnsureDefaultUnitsOfMeasure::run($this->clinic);
        EnsureDefaultUnitsOfMeasure::run($this->clinic);

        $this->assertDefaultUnitsForClinic($this->clinic->id);
        $this->assertSame(
            1,
            UnitOfMeasure::query()
                ->withoutGlobalScopes()
                ->where('clinic_id', $this->clinic->id)
                ->where('symbol', 'un')
                ->count()
        );
        $this->assertDatabaseHas('units_of_measure', [
            'clinic_id' => $this->clinic->id,
            'symbol' => 'un',
            'name' => 'Unidade da casa',
        ]);
    }

    public function test_artisan_backfill_seeds_clinics_missing_defaults(): void
    {
        $other = Clinic::factory()->create();

        $this->artisan('units:seed-defaults')
            ->assertSuccessful();

        $this->assertDefaultUnitsForClinic($this->clinic->id);
        $this->assertDefaultUnitsForClinic($other->id);

        $this->artisan('units:seed-defaults')
            ->assertSuccessful();

        $this->assertSame(
            count(EnsureDefaultUnitsOfMeasure::UNITS),
            UnitOfMeasure::query()
                ->withoutGlobalScopes()
                ->where('clinic_id', $this->clinic->id)
                ->count()
        );
        $this->assertSame(
            count(EnsureDefaultUnitsOfMeasure::UNITS),
            UnitOfMeasure::query()
                ->withoutGlobalScopes()
                ->where('clinic_id', $other->id)
                ->count()
        );
    }

    protected function assertDefaultUnitsForClinic(int $clinicId): void
    {
        $units = UnitOfMeasure::query()
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->get();

        $this->assertEqualsCanonicalizing(
            array_column(EnsureDefaultUnitsOfMeasure::UNITS, 'symbol'),
            $units->pluck('symbol')->all()
        );
        $this->assertTrue($units->every(fn (UnitOfMeasure $unit) => $unit->is_active));
    }
}
