<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEntry extends Model
{
    use BelongsToClinic;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /** Lançamento de remuneração por meta (role), ao lado de seller e professional. */
    public const ROLE_GOAL = 'goal';

    /** Valor por ocorrência (um lançamento por lead/evento). */
    public const GOAL_KIND_OCCURRENCE = 'occurrence';

    /** Bônus ao bater a meta do mês (um lançamento por métrica e mês). */
    public const GOAL_KIND_BONUS = 'bonus';

    protected $fillable = [
        'clinic_id',
        'user_id',
        'commission_rule_id',
        'role',
        'sale_id',
        'appointment_id',
        'lead_id',
        'goal_metric',
        'goal_kind',
        'protocol_id',
        'competence_month',
        'base_amount',
        'rate_percent',
        'amount',
        'status',
        'paid_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'competence_month' => 'date',
            'base_amount' => 'decimal:2',
            'rate_percent' => 'decimal:4',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }
}
