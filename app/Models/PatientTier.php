<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

/**
 * Faixa de paciente: quem comprou pelo menos `min_amount` nos últimos 12 meses
 * (e menos que o mínimo da próxima faixa) fica nesta faixa.
 */
class PatientTier extends Model
{
    use BelongsToClinic;

    /** Paleta fixa de cores das faixas (badges claros e escuros no app). */
    public const COLORS = ['bronze', 'silver', 'gold', 'diamond', 'purple', 'success', 'muted'];

    public const MAX_AMOUNT = 999_999_999.99;

    protected $fillable = ['clinic_id', 'name', 'color', 'min_amount'];

    protected function casts(): array
    {
        return ['min_amount' => 'decimal:2'];
    }

    public function minCents(): int
    {
        return (int) round(((float) $this->min_amount) * 100);
    }
}
