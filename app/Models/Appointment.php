<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Database\Factories\AppointmentFactory;
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

    public const CONFIRMATION_PENDING = 'pending';

    public const CONFIRMATION_CONFIRMED = 'confirmed';

    public const CONFIRMATION_NOT_CONFIRMED = 'not_confirmed';

    /** "Desmarcou": the patient said they will not come. */
    public const CONFIRMATION_DECLINED = 'declined';

    /** @var list<string> */
    public const CONFIRMATION_STATUSES = [
        self::CONFIRMATION_PENDING,
        self::CONFIRMATION_CONFIRMED,
        self::CONFIRMATION_NOT_CONFIRMED,
        self::CONFIRMATION_DECLINED,
    ];

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** @use HasFactory<AppointmentFactory> */
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
        'confirmation_sent_at',
        'confirmation_sent_by_user_id',
        'confirmation_status',
        'confirmation_status_at',
        'confirmation_status_by_user_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_SCHEDULED,
        'confirmation_status' => self::CONFIRMATION_PENDING,
        'total_cost' => '0.0000',
        'total_charged_on_appointment' => '0.00',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'confirmation_status_at' => 'datetime',
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

    public function confirmationSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmation_sent_by_user_id');
    }

    public function confirmationStatusBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmation_status_by_user_id');
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
