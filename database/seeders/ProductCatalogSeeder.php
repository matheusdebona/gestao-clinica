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

        $types = [
            ['name' => 'Botox', 'slug' => 'botox'],
            ['name' => 'Preenchimento', 'slug' => 'preenchimento'],
            ['name' => 'Toxina botulínica', 'slug' => 'toxina-botulinica'],
            ['name' => 'Ácido', 'slug' => 'acido'],
        ];

        foreach ($types as $type) {
            ProductType::query()->firstOrCreate(
                ['clinic_id' => $clinic->id, 'slug' => $type['slug']],
                ['name' => $type['name'], 'is_active' => true]
            );
        }

        foreach (['Allergan', 'Galderma', 'IPEN'] as $brand) {
            Brand::query()->firstOrCreate(
                ['clinic_id' => $clinic->id, 'name' => $brand],
                ['is_active' => true]
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
