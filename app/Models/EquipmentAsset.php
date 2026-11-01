<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equipamento depreciado em linha reta: (valor de compra − valor residual) ÷ vida útil (meses),
 * a partir do mês de início, por useful_life_months meses.
 */
class EquipmentAsset extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'expense_category_id',
        'name',
        'purchase_value',
        'residual_value',
        'purchased_on',
        'useful_life_months',
        'depreciation_start_month',
        'is_active',
        'notes',
    ];

    protected $attributes = ['is_active' => true, 'residual_value' => 0];

    protected function casts(): array
    {
        return [
            'purchase_value' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'purchased_on' => 'date',
            'useful_life_months' => 'integer',
            'depreciation_start_month' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function monthlyDepreciation(): float
    {
        $life = max(1, (int) $this->useful_life_months);

        return round(max(0, (float) $this->purchase_value - (float) $this->residual_value) / $life, 2);
    }

    public function lastDepreciationMonth(): CarbonInterface
    {
        return $this->depreciation_start_month->copy()->startOfMonth()->addMonthsNoOverflow(max(1, (int) $this->useful_life_months) - 1);
    }

    public function depreciatesIn(CarbonInterface $month): bool
    {
        $m = $month->copy()->startOfMonth();

        return $m->gte($this->depreciation_start_month->copy()->startOfMonth())
            && $m->lte($this->lastDepreciationMonth());
    }

    /** Meses já depreciados até o mês informado (inclusive). */
    public function monthsElapsed(CarbonInterface $month): int
    {
        $start = $this->depreciation_start_month->copy()->startOfMonth();
        $m = $month->copy()->startOfMonth();
        if ($m->lt($start)) {
            return 0;
        }

        return min((int) $this->useful_life_months, (int) $start->diffInMonths($m) + 1);
    }
}
