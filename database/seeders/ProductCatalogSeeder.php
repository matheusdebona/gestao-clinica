<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Clinic;
use App\Models\ProductType;
use App\Models\UnitOfMeasure;
use App\Support\CurrentClinic;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->first();
        if ($clinic === null) {
            return;
        }

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

        $units = [
            ['name' => 'Unidade', 'symbol' => 'un'],
            ['name' => 'Mililitro', 'symbol' => 'ml'],
            ['name' => 'Miligrama', 'symbol' => 'mg'],
            ['name' => 'Quilograma', 'symbol' => 'kg'],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::query()->firstOrCreate(
                ['clinic_id' => $clinic->id, 'symbol' => $unit['symbol']],
                ['name' => $unit['name'], 'is_active' => true]
            );
        }

        CurrentClinic::forget();
    }
}
