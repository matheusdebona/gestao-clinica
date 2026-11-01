<?php

namespace App\Services;

use App\Models\TaxSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Alíquota de impostos sobre receita vigente em cada mês de competência.
 */
class TaxRateResolver
{
    /** @var array<int, Collection<int, TaxSetting>> */
    private array $cache = [];

    public function settingFor(int $clinicId, CarbonInterface $date): ?TaxSetting
    {
        $month = $date->copy()->startOfMonth()->toDateString();

        return $this->settings($clinicId)
            ->first(fn (TaxSetting $setting) => $setting->effective_from->toDateString() <= $month);
    }

    public function ratePercentFor(int $clinicId, CarbonInterface $date): float
    {
        return $this->settingFor($clinicId, $date)?->effectiveRatePercent() ?? 0.0;
    }

    public function taxFor(int $clinicId, CarbonInterface $date, float $revenue): float
    {
        return round($revenue * $this->ratePercentFor($clinicId, $date) / 100, 2);
    }

    /**
     * @return Collection<int, TaxSetting> mais recente primeiro
     */
    private function settings(int $clinicId): Collection
    {
        return $this->cache[$clinicId] ??= TaxSetting::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->orderByDesc('effective_from')
            ->get();
    }
}
