<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\EquipmentAsset;
use App\Models\Expense;
use App\Models\RecurringExpense;
use Carbon\CarbonImmutable;

/**
 * Gera (idempotente) as despesas do mês a partir das recorrências ativas e da depreciação dos
 * equipamentos. Lançamentos já existentes — inclusive editados ou excluídos — não são recriados.
 */
class ExpenseGenerator
{
    public function generateMonth(int $clinicId, CarbonImmutable $month): int
    {
        $m = $month->startOfMonth();
        $created = 0;

        $recurring = RecurringExpense::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->whereDate('starts_month', '<=', $m->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_month')->orWhereDate('ends_month', '>=', $m->toDateString()))
            ->get();

        foreach ($recurring as $r) {
            $exists = Expense::withTrashed()->withoutGlobalScope('clinic')
                ->where('recurring_expense_id', $r->id)
                ->whereDate('competence_month', $m->toDateString())
                ->exists();
            if ($exists) {
                continue;
            }
            Expense::query()->create([
                'clinic_id' => $clinicId,
                'expense_category_id' => $r->expense_category_id,
                'description' => $r->description,
                'amount' => $r->amount,
                'competence_month' => $m->toDateString(),
                'due_on' => $r->due_day ? $m->setDay(min($r->due_day, $m->daysInMonth))->toDateString() : null,
                'supplier' => $r->supplier,
                'recurring_expense_id' => $r->id,
                'client_origin_id' => $r->client_origin_id,
                'campaign_id' => $r->campaign_id,
            ]);
            $created++;
        }

        $assets = EquipmentAsset::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->get();

        foreach ($assets as $asset) {
            if (! $asset->depreciatesIn($m) || $asset->monthlyDepreciation() <= 0) {
                continue;
            }
            $exists = Expense::withTrashed()->withoutGlobalScope('clinic')
                ->where('equipment_asset_id', $asset->id)
                ->whereDate('competence_month', $m->toDateString())
                ->exists();
            if ($exists) {
                continue;
            }
            Expense::query()->create([
                'clinic_id' => $clinicId,
                'expense_category_id' => $asset->expense_category_id,
                'description' => "Depreciação — {$asset->name}",
                'amount' => number_format($asset->monthlyDepreciation(), 2, '.', ''),
                'competence_month' => $m->toDateString(),
                'equipment_asset_id' => $asset->id,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Gera do mês de início até o mês informado (ex.: depois de cadastrar uma recorrência antiga).
     */
    public function generateRange(int $clinicId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $count = 0;
        $cursor = $from->startOfMonth();
        $end = $to->startOfMonth();
        $guard = 0;
        while ($cursor->lte($end) && $guard < 120) {
            $count += $this->generateMonth($clinicId, $cursor);
            $cursor = $cursor->addMonthNoOverflow();
            $guard++;
        }

        return $count;
    }

    public function generateCurrentMonthForAll(): int
    {
        $count = 0;
        $month = CarbonImmutable::now('America/Sao_Paulo')->startOfMonth();
        Clinic::query()->each(function (Clinic $clinic) use (&$count, $month) {
            $count += $this->generateMonth($clinic->id, $month);
        });

        return $count;
    }
}
