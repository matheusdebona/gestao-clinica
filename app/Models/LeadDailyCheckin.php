<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadDailyCheckin extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'user_id', 'day', 'worked', 'change', 'notes'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
