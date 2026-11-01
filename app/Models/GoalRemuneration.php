<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

/**
 * Quanto a clínica paga por tipo de meta: valor fixo por ocorrência e bônus ao bater a meta do mês.
 */
class GoalRemuneration extends Model
{
    use BelongsToClinic;

    /** Novo agendamento: lead convertido em paciente com avaliação marcada (marco "Agendou"). */
    public const METRIC_APPOINTMENTS = 'appointments';

    /** Lead abordado (marco "Abordado"). */
    public const METRIC_APPROACHED = 'approached';

    /** Valor de vendas: só bônus ao bater a meta (comissão por venda fica nas regras de comissão). */
    public const METRIC_SALES = 'sales';

    public const METRICS = [self::METRIC_APPOINTMENTS, self::METRIC_APPROACHED, self::METRIC_SALES];

    /** Métricas que aceitam valor por ocorrência. */
    public const PER_OCCURRENCE_METRICS = [self::METRIC_APPOINTMENTS, self::METRIC_APPROACHED];

    public const MAX_AMOUNT = 9_999_999.99;

    protected $fillable = [
        'clinic_id',
        'metric',
        'per_occurrence_amount',
        'target_bonus_amount',
        'updated_by_user_id',
    ];

    protected $attributes = [
        'per_occurrence_amount' => '0.00',
        'target_bonus_amount' => '0.00',
    ];

    protected function casts(): array
    {
        return [
            'per_occurrence_amount' => 'decimal:2',
            'target_bonus_amount' => 'decimal:2',
        ];
    }
}
