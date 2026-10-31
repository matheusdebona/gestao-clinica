<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Support\LeadPhone;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'phone',
        'client_origin_id',
        'lead_status_id',
        'notes',
        'created_by_user_id',
        'client_id',
        'converted_at',
        'cancelled_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (Lead $lead): void {
            $lead->phone_digits = LeadPhone::canonical((string) $lead->phone);
        });
    }

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function clientOrigin(): BelongsTo
    {
        return $this->belongsTo(ClientOrigin::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('created_at')->latest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->where('type', LeadActivity::TYPE_COMMENT);
    }

    public function isConverted(): bool
    {
        return $this->client_id !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
