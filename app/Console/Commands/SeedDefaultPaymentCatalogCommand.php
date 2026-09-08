<?php

namespace App\Console\Commands;

use App\Support\EnsureDefaultPaymentCatalog;
use Illuminate\Console\Command;

class SeedDefaultPaymentCatalogCommand extends Command
{
    protected $signature = 'payment-catalog:seed-defaults';

    protected $description = 'Seed default payment methods, card brands, and card operators for every clinic missing them.';

    public function handle(): int
    {
        $count = EnsureDefaultPaymentCatalog::runAll();

        $this->info("Payment catalog defaults applied to {$count} clinic(s).");

        return self::SUCCESS;
    }
}
