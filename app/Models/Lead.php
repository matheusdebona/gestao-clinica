<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Support\LeadPhone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'approached_at',
        'approached_by_user_id',
        'replied_at',
        'replied_by_user_id',
        'booked_at',
        'booked_by_user_id',
    ];

    /** Funnel milestones in order: Abordado → Respondeu → Agendou. */
    public const MILESTONES = ['approached', 'replied', 'booked'];

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
            'approached_at' => 'datetime',
            'replied_at' => 'datetime',
            'booked_at' => 'datetime',
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

    public function approachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approached_by_user_id');
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by_user_id');
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
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

    /** Open = not final (not converted into a patient, not cancelled). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('leads.client_id')->whereNull('leads.cancelled_at');
    }

    /**
     * "Parado": open lead with no activity (creation, comment, status change, WhatsApp…) since $cutoff.
     */
    public function scopeStale(Builder $query, CarbonInterface $cutoff): Builder
    {
        return $query->open()
            ->where('leads.created_at', '<', $cutoff)
            ->whereDoesntHave('activities', fn (Builder $activities) => $activities->where('created_at', '>=', $cutoff));
    }

    public function scopeWithLastActivity(Builder $query): Builder
    {
        return $query->withMax('activities', 'created_at');
    }

    /** Latest activity time (falls back to the creation time). */
    public function lastActivityAt(): ?CarbonImmutable
    {
        $max = $this->getAttribute('activities_max_created_at');

        if ($max === null && $this->relationLoaded('activities')) {
            $max = $this->activities->max('created_at');
        }

        $value = $max ?? $this->created_at;

        return $value !== null ? CarbonImmutable::parse($value) : null;
    }

    public function isFinal(): bool
    {
        return $this->client_id !== null || $this->cancelled_at !== null;
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
