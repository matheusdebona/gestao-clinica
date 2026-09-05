<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DemoClinicSeeder::class,
            ProductCatalogSeeder::class,
            PaymentCatalogSeeder::class,
        ]);
    }
}
