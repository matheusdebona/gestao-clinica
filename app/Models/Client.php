<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'whatsapp',
        'notes',
        'main_pains',
        'service_duration_minutes',
        'client_origin_id',
        'campaign_id',
        'initial_consultation_amount',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'service_duration_minutes' => 'integer',
            'initial_consultation_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function clientOrigin(): BelongsTo
    {
        return $this->belongsTo(ClientOrigin::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
