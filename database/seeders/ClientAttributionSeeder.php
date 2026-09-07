<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Support\EnsureDefaultClientOrigins;
use Illuminate\Database\Seeder;

class ClientAttributionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Clinic::query()->get() as $clinic) {
            EnsureDefaultClientOrigins::run($clinic);
        }
    }
}
