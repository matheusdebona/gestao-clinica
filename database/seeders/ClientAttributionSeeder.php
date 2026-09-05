<?php

namespace Database\Seeders;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Support\CurrentClinic;
use Illuminate\Database\Seeder;

class ClientAttributionSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->first();
        if ($clinic === null) {
            return;
        }

        CurrentClinic::setId($clinic->id);

        foreach (['Instagram', 'Facebook', 'Indicação', 'Google', 'Outros'] as $name) {
            ClientOrigin::query()->firstOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'name' => $name,
                ],
                [
                    'is_active' => true,
                ]
            );
        }

        CurrentClinic::forget();
    }
}
