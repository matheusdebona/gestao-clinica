<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Services\GoalRemunerationService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'clinic_id', 'is_active'])]
#[Hidden(['password', 'remember_token', 'temporary_password_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToClinic, HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected static function booted(): void
    {
        // Desativado não ganha metas/faixas/faturamento: refaz o mês atual ao mudar o status.
        static::updated(function (User $user): void {
            if ($user->wasChanged('is_active')) {
                app(GoalRemunerationService::class)->onUserActiveChanged($user);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'temporary_password_expires_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return $this->clinic_id === null && $this->hasRole('super-admin');
    }
}
