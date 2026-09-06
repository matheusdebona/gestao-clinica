<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\User;
use App\Support\EnsureRolesAndPermissions;
use Illuminate\Support\Facades\DB;

class RegisterClinicAdminService
{
    /**
     * @param  array{clinic_name: string, name: string, email: string, password: string}  $payload
     */
    public function register(array $payload): User
    {
        EnsureRolesAndPermissions::run();

        return DB::transaction(function () use ($payload): User {
            $clinic = Clinic::query()->create([
                'name' => $payload['clinic_name'],
                'email' => $payload['email'],
                'is_active' => true,
                'settings' => [
                    'locale' => 'pt_BR',
                    'currency' => 'BRL',
                ],
            ]);

            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => $payload['password'],
                'clinic_id' => $clinic->id,
                'is_active' => true,
            ]);

            $user->assignRole('admin');

            return $user->load('clinic', 'roles', 'permissions');
        });
    }
}
