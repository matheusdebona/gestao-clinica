<?php

namespace Database\Seeders;

use App\Support\EnsureRolesAndPermissions;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        EnsureRolesAndPermissions::run();
    }
}
