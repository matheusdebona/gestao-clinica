<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGoal extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'user_id',
        'month',
        'appointments_target',
        'sales_target',
        'approached_target',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'appointments_target' => 'integer',
            'sales_target' => 'decimal:2',
            'approached_target' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
