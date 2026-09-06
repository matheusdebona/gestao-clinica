<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const DEFAULT_DURATION_MINUTES = 60;

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'treatment_id',
        'client_id',
        'professional_user_id',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'duration_minutes',
        'total_cost',
        'total_charged_on_appointment',
        'stock_warning',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_SCHEDULED,
        'total_cost' => '0.0000',
        'total_charged_on_appointment' => '0.00',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_minutes' => 'integer',
            'total_cost' => 'decimal:4',
            'total_charged_on_appointment' => 'decimal:2',
            'stock_warning' => 'array',
        ];
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function professionalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_user_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(AppointmentConsumption::class);
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
