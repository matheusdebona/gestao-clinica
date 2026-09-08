<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Clinic;
use App\Models\ProductType;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultUnitsOfMeasure;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->first();
        if ($clinic !== null) {
            CurrentClinic::setId($clinic->id);

            $brands = [];
            foreach (['Allergan', 'Galderma', 'IPEN'] as $brandName) {
                $brands[$brandName] = Brand::query()->firstOrCreate(
                    ['clinic_id' => $clinic->id, 'name' => $brandName],
                    ['is_active' => true]
                );
            }

            $types = [
                ['name' => 'Botox', 'slug' => 'botox', 'brand' => 'Allergan'],
                ['name' => 'Preenchimento', 'slug' => 'preenchimento', 'brand' => 'Galderma'],
                ['name' => 'Toxina botulínica', 'slug' => 'toxina-botulinica', 'brand' => 'Allergan'],
                ['name' => 'Ácido', 'slug' => 'acido', 'brand' => 'IPEN'],
            ];

            foreach ($types as $type) {
                ProductType::query()->firstOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'brand_id' => $brands[$type['brand']]->id,
                        'slug' => $type['slug'],
                    ],
                    ['name' => $type['name'], 'is_active' => true]
                );
            }

            CurrentClinic::forget();
        }

        EnsureDefaultUnitsOfMeasure::runAll();
    }
}
