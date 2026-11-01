<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Sale;
use App\Services\Finance\SaleEconomics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gera lançamentos de comissão a partir das regras ativas.
 *
 * Vendedor: por venda confirmada (sold_by_user), competência = mês da venda.
 * Profissional: por sessão concluída (professional_user), competência = mês da sessão;
 * base = valor executado na sessão (itens vendidos aplicados + extras cobrados).
 *
 * Cada grupo de protocolo usa a regra mais específica (pessoa + protocolo > pessoa > protocolo > geral).
 * Percentual incide sobre a base (bruta ou líquida de taxas e impostos); fixo conta uma vez por
 * regra na venda/sessão. Lançamentos pagos nunca são alterados.
 */
class CommissionService
{
    private const RELATIONS = ['items', 'payments', 'treatment.appointments.consumptions.saleItem'];

    public function __construct(private readonly SaleEconomics $economics) {}

    public function syncSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale->loadMissing(self::RELATIONS);
            $existing = CommissionEntry::query()->withoutGlobalScope('clinic')
                ->where('sale_id', $sale->id)
                ->where('role', CommissionRule::ROLE_SELLER);

            if ($sale->status !== Sale::STATUS_CONFIRMED) {
                (clone $existing)->where('status', CommissionEntry::STATUS_PENDING)
                    ->update(['status' => CommissionEntry::STATUS_CANCELLED]);

                return;
            }

            if ((clone $existing)->where('status', CommissionEntry::STATUS_PAID)->exists()) {
                return;
            }
            (clone $existing)->delete();

            if ($sale->sold_by_user_id === null) {
                return;
            }

            $rules = $this->rules((int) $sale->clinic_id, CommissionRule::ROLE_SELLER);
            $this->create(
                rules: $rules,
                userId: (int) $sale->sold_by_user_id,
                groups: $this->economics->revenueByGroup($sale),
                netRatio: $this->economics->netRatio($sale),
                attributes: [
                    'clinic_id' => $sale->clinic_id,
                    'role' => CommissionRule::ROLE_SELLER,
                    'sale_id' => $sale->id,
                    'competence_month' => ($sale->sold_at ?? now())->copy()->startOfMonth()->toDateString(),
                ],
            );
        });
    }

    public function syncAppointment(Appointment $appointment): void
    {
        DB::transaction(function () use ($appointment) {
            $existing = CommissionEntry::query()->withoutGlobalScope('clinic')
                ->where('appointment_id', $appointment->id)
                ->where('role', CommissionRule::ROLE_PROFESSIONAL);

            if ($appointment->status !== Appointment::STATUS_COMPLETED) {
                (clone $existing)->where('status', CommissionEntry::STATUS_PENDING)->delete();

                return;
            }
            if ((clone $existing)->where('status', CommissionEntry::STATUS_PAID)->exists()) {
                return;
            }
            (clone $existing)->delete();

            if ($appointment->professional_user_id === null) {
                return;
            }

            $appointment->loadMissing(['consumptions.saleItem', 'treatment']);
            $sale = $appointment->treatment?->sale_id
                ? Sale::query()->withoutGlobalScope('clinic')->with(self::RELATIONS)->find($appointment->treatment->sale_id)
                : null;

            $groups = $this->economics->executedByGroup($appointment, $sale);
            if ($groups === []) {
                // Sessão sem itens vendidos aplicados: só regras fixas fazem sentido.
                $groups = [0 => 0.0];
                foreach ($sale?->items ?? [] as $item) {
                    if ($item->source_protocol_id) {
                        $groups[(int) $item->source_protocol_id] = 0.0;
                    }
                }
            }

            $this->create(
                rules: $this->rules((int) $appointment->clinic_id, CommissionRule::ROLE_PROFESSIONAL),
                userId: (int) $appointment->professional_user_id,
                groups: $groups,
                netRatio: $sale ? $this->economics->netRatio($sale) : 1.0,
                attributes: [
                    'clinic_id' => $appointment->clinic_id,
                    'role' => CommissionRule::ROLE_PROFESSIONAL,
                    'sale_id' => $sale?->id,
                    'appointment_id' => $appointment->id,
                    'competence_month' => ($appointment->finished_at ?? now())->copy()->startOfMonth()->toDateString(),
                ],
            );
        });
    }

    /**
     * Regera os lançamentos não pagos de um mês (vendas confirmadas e sessões concluídas no mês).
     */
    public function rebuildMonth(int $clinicId, CarbonImmutable $month): int
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        $count = 0;

        Sale::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->whereIn('status', [Sale::STATUS_CONFIRMED, Sale::STATUS_CANCELLED])
            ->whereBetween('sold_at', [$from, $to])
            ->with(self::RELATIONS)
            ->chunkById(100, function ($sales) use (&$count) {
                foreach ($sales as $sale) {
                    $this->syncSale($sale);
                    $count++;
                }
            });

        Appointment::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereBetween('finished_at', [$from, $to])
            ->chunkById(100, function ($appointments) use (&$count) {
                foreach ($appointments as $appointment) {
                    $this->syncAppointment($appointment);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     * @param  array<int, float>  $groups
     * @param  array<string, mixed>  $attributes
     */
    private function create(Collection $rules, int $userId, array $groups, float $netRatio, array $attributes): void
    {
        // Um lançamento por regra e grupo de protocolo (0 = avulso), para a margem por protocolo.
        $byRule = [];
        foreach ($groups as $protocolKey => $value) {
            $rule = $this->resolve($rules, $userId, $protocolKey === 0 ? null : $protocolKey);
            if ($rule === null) {
                continue;
            }
            $base = $rule->base === CommissionRule::BASE_NET ? $value * $netRatio : $value;
            $byRule[$rule->id]['rule'] = $rule;
            $byRule[$rule->id]['groups'][$protocolKey] = $base;
        }

        foreach ($byRule as ['rule' => $rule, 'groups' => $bases]) {
            $isPercent = $rule->calc_type === CommissionRule::TYPE_PERCENT;
            $sum = array_sum($bases);
            foreach ($bases as $protocolKey => $base) {
                if ($isPercent) {
                    $amount = $base * (float) $rule->value / 100;
                } else {
                    // Fixo conta uma vez por venda/sessão, repartido entre os grupos.
                    $share = $sum > 0 ? $base / $sum : 1 / count($bases);
                    $amount = (float) $rule->value * $share;
                }
                if (round($amount, 2) <= 0) {
                    continue;
                }

                CommissionEntry::query()->create([
                    ...$attributes,
                    'user_id' => $userId,
                    'commission_rule_id' => $rule->id,
                    'protocol_id' => $protocolKey === 0 ? null : $protocolKey,
                    'base_amount' => number_format($base, 2, '.', ''),
                    'rate_percent' => $isPercent ? $rule->value : null,
                    'amount' => number_format($amount, 2, '.', ''),
                    'status' => CommissionEntry::STATUS_PENDING,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     */
    private function resolve(Collection $rules, int $userId, ?int $protocolId): ?CommissionRule
    {
        return $rules
            ->filter(fn (CommissionRule $r) => ($r->user_id === null || (int) $r->user_id === $userId)
                && ($r->protocol_id === null || (int) $r->protocol_id === $protocolId))
            ->sortByDesc(fn (CommissionRule $r) => $r->specificity() * 1_000_000 + $r->id)
            ->first();
    }

    /**
     * @return Collection<int, CommissionRule>
     */
    private function rules(int $clinicId, string $role): Collection
    {
        return CommissionRule::query()->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('role', $role)
            ->where('is_active', true)
            ->get();
    }
}
