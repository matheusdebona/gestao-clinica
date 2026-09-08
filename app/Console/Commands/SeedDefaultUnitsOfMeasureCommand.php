<?php

namespace App\Console\Commands;

use App\Support\EnsureDefaultUnitsOfMeasure;
use Illuminate\Console\Command;

class SeedDefaultUnitsOfMeasureCommand extends Command
{
    protected $signature = 'units:seed-defaults';

    protected $description = 'Seed default units of measure for every clinic missing them.';

    public function handle(): int
    {
        $count = EnsureDefaultUnitsOfMeasure::runAll();

        $this->info("Units of measure defaults applied to {$count} clinic(s).");

        return self::SUCCESS;
    }
}
