<?php

namespace Database\Seeders;

use App\Support\EnsureDefaultPaymentCatalog;
use Illuminate\Database\Seeder;

class PaymentCatalogSeeder extends Seeder
{
    public function run(): void
    {
        EnsureDefaultPaymentCatalog::runAll();
    }
}
