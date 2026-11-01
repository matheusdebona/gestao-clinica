<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

/**
 * Regime tributário da clínica a partir de um mês de competência (effective_from = dia 1).
 * Vale até a próxima vigência cadastrada.
 */
class TaxSetting extends Model
{
    use BelongsToClinic;

    public const REGIME_SIMPLES = 'simples';

    public const REGIME_PRESUMIDO = 'presumido';

    public const REGIME_REAL = 'real';

    public const REGIME_MEI = 'mei';

    public const REGIME_NONE = 'none';

    public const REGIMES = [
        self::REGIME_SIMPLES,
        self::REGIME_PRESUMIDO,
        self::REGIME_REAL,
        self::REGIME_MEI,
        self::REGIME_NONE,
    ];

    protected $fillable = [
        'clinic_id',
        'effective_from',
        'regime',
        'simples_rate_percent',
        'iss_percent',
        'pis_percent',
        'cofins_percent',
        'irpj_percent',
        'csll_percent',
        'other_percent',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'simples_rate_percent' => 'decimal:4',
            'iss_percent' => 'decimal:4',
            'pis_percent' => 'decimal:4',
            'cofins_percent' => 'decimal:4',
            'irpj_percent' => 'decimal:4',
            'csll_percent' => 'decimal:4',
            'other_percent' => 'decimal:4',
        ];
    }

    /**
     * Alíquota efetiva sobre a receita (em %).
     * Simples: alíquota efetiva do DAS (já inclui ISS) + outros.
     * Presumido/Real: ISS + PIS + COFINS + IRPJ + CSLL + outros (IRPJ/CSLL como % efetivo sobre a receita).
     * MEI/sem regime: só "outros" (o DAS fixo do MEI entra como despesa mensal).
     */
    public function effectiveRatePercent(): float
    {
        $other = (float) $this->other_percent;

        return round(match ($this->regime) {
            self::REGIME_SIMPLES => (float) $this->simples_rate_percent + $other,
            self::REGIME_PRESUMIDO, self::REGIME_REAL => (float) $this->iss_percent
                + (float) $this->pis_percent
                + (float) $this->cofins_percent
                + (float) $this->irpj_percent
                + (float) $this->csll_percent
                + $other,
            default => $other,
        }, 4);
    }
}
