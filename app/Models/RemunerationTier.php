<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Faixa de remuneração por gatilho (um modelo para os dois usos):
 *  - appointments: gatilho = nº de novos agendamentos do colaborador no mês (config da clínica);
 *    % sobre as vendas confirmadas no mês dos pacientes desses agendamentos;
 *  - revenue: gatilho = faturamento total da clínica no mês (config por colaborador);
 *    % sobre esse faturamento.
 * Só a maior faixa atingida vale (não acumula): valor fixo + % da base.
 */
class RemunerationTier extends Model
{
    use BelongsToClinic;

    public const KIND_APPOINTMENTS = 'appointments';

    public const KIND_REVENUE = 'revenue';

    public const KINDS = [self::KIND_APPOINTMENTS, self::KIND_REVENUE];

    public const MAX_TIERS = 10;

    protected $fillable = [
        'clinic_id',
        'kind',
        'user_id',
        'threshold',
        'fixed_amount',
        'percent',
        'updated_by_user_id',
    ];

    protected $attributes = [
        'fixed_amount' => '0.00',
        'percent' => '0.0000',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'percent' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Maior faixa atingida (gatilho <= realizado), ou null.
     *
     * @param  iterable<RemunerationTier>  $tiers
     */
    public static function reached(iterable $tiers, float $actual): ?self
    {
        return Collection::make($tiers)
            ->filter(fn (self $t) => round((float) $t->threshold, 2) <= round($actual, 2))
            ->sortByDesc(fn (self $t) => (float) $t->threshold)
            ->first();
    }

    /** Valor da faixa: fixo + % da base, arredondado a centavos. */
    public function amountFor(float $base): float
    {
        return round((float) $this->fixed_amount + $base * (float) $this->percent / 100, 2);
    }
}
