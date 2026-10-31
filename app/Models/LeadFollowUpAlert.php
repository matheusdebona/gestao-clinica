<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class LeadFollowUpAlert extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'lead_id', 'days', 'alerted_at'];

    protected function casts(): array
    {
        return ['days' => 'integer', 'alerted_at' => 'datetime'];
    }
}
