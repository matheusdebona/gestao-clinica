<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoClinicSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->firstOrCreate(
            ['name' => env('DEMO_CLINIC_NAME', 'Clínica Demo')],
            [
                'email' => env('DEMO_CLINIC_EMAIL', 'contato@clinica-demo.test'),
                'phone' => env('DEMO_CLINIC_PHONE', '11999999999'),
                'is_active' => true,
                'settings' => [
                    'locale' => 'pt_BR',
                    'currency' => 'BRL',
                ],
            ]
        );

        $adminEmail = env('DEMO_ADMIN_EMAIL', 'admin@clinica-demo.test');
        $adminPassword = env('DEMO_ADMIN_PASSWORD', 'ChangeMe!123');

        $admin = User::query()->firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => env('DEMO_ADMIN_NAME', 'Admin Clínica'),
                'password' => Hash::make($adminPassword),
                'clinic_id' => $clinic->id,
                'is_active' => true,
            ]
        );

        if ($admin->clinic_id !== $clinic->id) {
            $admin->update(['clinic_id' => $clinic->id]);
        }

        $admin->syncRoles(['admin']);

        $superEmail = env('SUPER_ADMIN_EMAIL');
        $superPassword = env('SUPER_ADMIN_PASSWORD');

        if ($superEmail && $superPassword) {
            $super = User::query()->firstOrCreate(
                ['email' => $superEmail],
                [
                    'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                    'password' => Hash::make($superPassword),
                    'clinic_id' => null,
                    'is_active' => true,
                ]
            );

            $super->syncRoles(['super-admin']);
        }
    }
}
