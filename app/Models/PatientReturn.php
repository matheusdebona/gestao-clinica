<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientReturn extends Model
{
    use BelongsToClinic;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const REASON_APPOINTMENT = 'appointment';

    public const REASON_SALE = 'sale';

    public const REASON_MANUAL = 'manual';

    protected $fillable = [
        'clinic_id',
        'client_id',
        'protocol_id',
        'sale_id',
        'treatment_id',
        'label',
        'due_on',
        'status',
        'done_at',
        'done_reason',
        'done_by_user_id',
        'whatsapp_sent_at',
        'whatsapp_sent_by_user_id',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date:Y-m-d',
            'done_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
