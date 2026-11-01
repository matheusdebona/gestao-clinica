<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Protocol;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\TaxSetting;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Finance\SaleEconomics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Margem de contribuição por venda (coorte: vendas confirmadas no período).
 *
 * receita = valor efetivo da venda + extras cobrados nas sessões dela
 * − insumos: custo real das sessões concluídas + custo previsto dos itens ainda não aplicados
 *   (enquanto o tratamento está aberto; tratamento concluído/cancelado = só o real)
 * − taxas de cartão e antecipação gravadas nos pagamentos
 * − impostos: alíquota do mês da venda × receita
 * − comissões lançadas (vendedor e profissionais) da venda
 * = margem de contribuição da venda
 *
 * Total da clínica = soma das vendas − perdas de estoque valorizadas no período − remuneração
 * por metas e por faturamento (lançamentos sem venda, role goal/revenue) dos meses do período.
 * Esses lançamentos seguem a mesma base das comissões de venda/sessão: competência (pendentes e
 * pagos; cancelados fora), pelo mês de competência (mês civil de São Paulo).
 */
class ContributionMarginService
{
    public const COMPONENTS = ['revenue', 'supplies', 'card_fees', 'anticipation', 'taxes', 'commissions'];

    public function __construct(private readonly SaleEconomics $economics) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to, int $salesLimit = 50): array
    {
        $fromStart = $from->startOfDay();
        $toEnd = $to->endOfDay();

        $sales = Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$fromStart, $toEnd])
            ->with([
                'items',
                'payments',
                'client:id,name,client_origin_id',
                'client.clientOrigin:id,name',
                'soldByUser:id,name',
                'treatment.appointments.consumptions.saleItem',
            ])
            ->orderByDesc('sold_at')
            ->get();

        $commissions = CommissionEntry::query()
            ->whereIn('sale_id', $sales->pluck('id'))
            ->where('status', '!=', CommissionEntry::STATUS_CANCELLED)
            ->get()
            ->groupBy('sale_id');

        $protocolNames = Protocol::query()->pluck('name', 'id');
        $professionalNames = User::query()->pluck('name', 'id');

        $totals = $this->zero() + ['supplies_estimated' => 0.0];
        $byProtocol = [];
        $byProfessional = [];
        $bySeller = [];
        $byOrigin = [];
        $rows = [];

        foreach ($sales as $sale) {
            $groups = $this->saleGroups($sale, $commissions->get($sale->id, collect()));
            $saleTotal = $this->sumGroups($groups);
            $estimated = array_sum(array_column($groups, 'supplies_estimated'));
            $totals = $this->add($totals, $saleTotal);
            $totals['supplies_estimated'] += $estimated;

            foreach ($groups as $key => $g) {
                $label = $key === 0 ? 'Itens avulsos e extras' : ($protocolNames[$key] ?? 'Protocolo removido');
                $this->accumulate($byProtocol, (string) $key, $label, $g, $sale->id);
            }

            $seller = $sale->soldByUser;
            $this->accumulate($bySeller, (string) ($seller?->id ?? 0), $seller?->name ?? 'Sem vendedor', $saleTotal, $sale->id);

            $origin = $sale->client?->clientOrigin;
            $this->accumulate($byOrigin, (string) ($origin?->id ?? 0), $origin?->name ?? 'Sem origem', $saleTotal, $sale->id);

            foreach ($this->professionalWeights($sale) as $userId => $weight) {
                $label = $userId === 0 ? 'Sem sessão concluída' : ($professionalNames[$userId] ?? 'Profissional');
                $this->accumulate($byProfessional, (string) $userId, $label, $this->scale($saleTotal, $weight), $sale->id);
            }

            if (count($rows) < $salesLimit) {
                $rows[] = [
                    'sale_id' => $sale->id,
                    'client_name' => $sale->client?->name,
                    'sold_at' => $sale->sold_at?->toIso8601String(),
                    'seller_name' => $seller?->name,
                    'supplies_estimated' => $estimated > 0.004,
                    ...$this->format($saleTotal),
                ];
            }
        }

        $losses = StockMovement::query()
            ->whereNotNull('loss_reason')
            ->whereBetween('created_at', [$fromStart, $toEnd])
            ->selectRaw('loss_reason, SUM(loss_value) as total')
            ->groupBy('loss_reason')
            ->pluck('total', 'loss_reason')
            ->map(fn ($v) => (float) $v);
        $lossTotal = (float) $losses->sum();

        $goalCommissions = $this->goalCommissions($from, $to);

        $total = $this->format($totals);
        $clinicMargin = $totals['contribution_margin'] - $lossTotal - $goalCommissions;

        return [
            'from' => $fromStart->toDateString(),
            'to' => $toEnd->toDateString(),
            'totals' => [
                ...$total,
                'supplies_estimated' => $this->money($totals['supplies_estimated']),
                'losses' => $this->money($lossTotal),
                'losses_by_reason' => [
                    StockMovement::LOSS_EXPIRED => $this->money($losses[StockMovement::LOSS_EXPIRED] ?? 0),
                    StockMovement::LOSS_DAMAGED => $this->money($losses[StockMovement::LOSS_DAMAGED] ?? 0),
                    StockMovement::LOSS_OTHER => $this->money($losses[StockMovement::LOSS_OTHER] ?? 0),
                ],
                'goal_commissions' => $this->money($goalCommissions),
                'clinic_contribution_margin' => $this->money($clinicMargin),
                'clinic_contribution_margin_percent' => $totals['revenue'] > 0
                    ? number_format($clinicMargin / $totals['revenue'] * 100, 2, '.', '')
                    : null,
                'sales_count' => $sales->count(),
            ],
            'by_protocol' => $this->rank($byProtocol),
            'by_professional' => $this->rank($byProfessional),
            'by_seller' => $this->rank($bySeller),
            'by_origin' => $this->rank($byOrigin),
            'sales' => $rows,
            'checks' => [
                'tax_configured' => TaxSetting::query()->exists(),
                'commission_rules' => CommissionRule::query()->where('is_active', true)->exists(),
                'card_payments_without_fee' => $sales->flatMap->payments
                    ->filter(fn ($p) => $p->card_operator_id !== null && $p->fee_source === PaymentFeeService::SOURCE_NONE)
                    ->count(),
            ],
        ];
    }

    /**
     * Remuneração por metas e faturamento com competência num mês que toca o período
     * (do mês de $from até $to), pendente ou paga, como as comissões de venda.
     */
    public function goalCommissions(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) CommissionEntry::query()
            ->whereIn('role', CommissionEntry::MONTHLY_ROLES)
            ->where('status', '!=', CommissionEntry::STATUS_CANCELLED)
            ->whereDate('competence_month', '>=', $from->startOfMonth()->toDateString())
            ->whereDate('competence_month', '<=', $to->toDateString())
            ->sum('amount');
    }

    /**
     * @param  Collection<int, CommissionEntry>  $commissions
     * @return array<int, array<string, float>>
     */
    public function saleGroups(Sale $sale, Collection $commissions): array
    {
        $revenue = $this->economics->revenueByGroup($sale);
        $groups = [];
        foreach ($revenue as $key => $value) {
            $groups[$key] = $this->zero() + ['supplies_estimated' => 0.0];
            $groups[$key]['revenue'] = $value;
        }
        $ensure = function (int $key) use (&$groups) {
            $groups[$key] ??= $this->zero() + ['supplies_estimated' => 0.0];
        };

        $consumedQty = [];
        foreach ($this->economics->completedAppointments($sale) as $appointment) {
            foreach ($appointment->consumptions as $c) {
                $key = (int) ($c->saleItem?->source_protocol_id ?? 0);
                $ensure($key);
                $groups[$key]['supplies'] += (float) $c->line_cost;
                $groups[$key]['revenue'] += (float) $c->charged_amount;
                if ($c->sale_item_id !== null) {
                    $consumedQty[$c->sale_item_id] = ($consumedQty[$c->sale_item_id] ?? 0) + (float) $c->quantity;
                }
            }
        }

        $open = $sale->treatment === null || $sale->treatment->status === Treatment::STATUS_OPEN;
        if ($open) {
            foreach ($sale->items as $item) {
                $remaining = max(0.0, (float) $item->quantity - ($consumedQty[$item->id] ?? 0));
                if ($remaining <= 0) {
                    continue;
                }
                $key = (int) ($item->source_protocol_id ?? 0);
                $ensure($key);
                $cost = $remaining * (float) $item->unit_cost;
                $groups[$key]['supplies'] += $cost;
                $groups[$key]['supplies_estimated'] += $cost;
            }
        }

        $totalRevenue = array_sum(array_column($groups, 'revenue'));
        $payment = $this->economics->paymentCosts($sale);
        $taxRate = $this->economics->taxRatePercent($sale);
        $share = fn (array $g) => $totalRevenue > 0 ? $g['revenue'] / $totalRevenue : 1 / max(1, count($groups));

        $proportionalCommission = 0.0;
        foreach ($commissions as $entry) {
            $key = (int) ($entry->protocol_id ?? 0);
            if (isset($groups[$key])) {
                $groups[$key]['commissions'] += (float) $entry->amount;
            } else {
                $proportionalCommission += (float) $entry->amount;
            }
        }

        foreach ($groups as $key => $g) {
            $s = $share($g);
            $groups[$key]['card_fees'] = $payment['card_fees'] * $s;
            $groups[$key]['anticipation'] = $payment['anticipation'] * $s;
            $groups[$key]['taxes'] = $g['revenue'] * $taxRate / 100;
            $groups[$key]['commissions'] += $proportionalCommission * $s;
            $groups[$key]['contribution_margin'] = $groups[$key]['revenue']
                - $groups[$key]['supplies']
                - $groups[$key]['card_fees']
                - $groups[$key]['anticipation']
                - $groups[$key]['taxes']
                - $groups[$key]['commissions'];
        }

        return $groups;
    }

    /**
     * Peso de cada profissional na venda: valor executado nas sessões concluídas
     * (ou número de sessões, se não houver valor). 0 = ainda sem sessão concluída.
     *
     * @return array<int, float>
     */
    private function professionalWeights(Sale $sale): array
    {
        $value = [];
        $count = [];
        foreach ($this->economics->completedAppointments($sale) as $appointment) {
            /** @var Appointment $appointment */
            $id = (int) ($appointment->professional_user_id ?? 0);
            $value[$id] = ($value[$id] ?? 0) + array_sum($this->economics->executedByGroup($appointment, $sale));
            $count[$id] = ($count[$id] ?? 0) + 1;
        }
        $weights = array_sum($value) > 0 ? $value : $count;
        $sum = array_sum($weights);
        if ($sum <= 0) {
            return [0 => 1.0];
        }

        return array_map(fn ($w) => $w / $sum, $weights);
    }

    /** @return array<string, float> */
    private function zero(): array
    {
        return array_fill_keys([...self::COMPONENTS, 'contribution_margin'], 0.0);
    }

    /**
     * @param  array<int, array<string, float>>  $groups
     * @return array<string, float>
     */
    private function sumGroups(array $groups): array
    {
        $sum = $this->zero();
        foreach ($groups as $g) {
            $sum = $this->add($sum, $g);
        }

        return $sum;
    }

    /**
     * @param  array<string, float>  $a
     * @param  array<string, float>  $b
     * @return array<string, float>
     */
    private function add(array $a, array $b): array
    {
        foreach ([...self::COMPONENTS, 'contribution_margin'] as $k) {
            $a[$k] = ($a[$k] ?? 0) + ($b[$k] ?? 0);
        }

        return $a;
    }

    /**
     * @param  array<string, float>  $values
     * @return array<string, float>
     */
    private function scale(array $values, float $factor): array
    {
        $out = [];
        foreach ([...self::COMPONENTS, 'contribution_margin'] as $k) {
            $out[$k] = ($values[$k] ?? 0) * $factor;
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $bucket
     * @param  array<string, float>  $values
     */
    private function accumulate(array &$bucket, string $key, string $label, array $values, int $saleId): void
    {
        $bucket[$key] ??= ['key' => $key, 'label' => $label, 'sales' => [], ...$this->zero()];
        foreach ([...self::COMPONENTS, 'contribution_margin'] as $k) {
            $bucket[$key][$k] += $values[$k] ?? 0;
        }
        $bucket[$key]['sales'][$saleId] = true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $bucket
     * @return list<array<string, mixed>>
     */
    private function rank(array $bucket): array
    {
        $rows = array_map(fn (array $row) => [
            'key' => $row['key'],
            'label' => $row['label'],
            'sales_count' => count($row['sales']),
            ...$this->format($row),
        ], array_values($bucket));

        usort($rows, fn ($a, $b) => (float) $b['contribution_margin'] <=> (float) $a['contribution_margin']);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string|null>
     */
    private function format(array $values): array
    {
        $out = [];
        foreach ([...self::COMPONENTS, 'contribution_margin'] as $k) {
            $out[$k] = $this->money((float) ($values[$k] ?? 0));
        }
        $revenue = (float) ($values['revenue'] ?? 0);
        $out['contribution_margin_percent'] = $revenue > 0
            ? number_format((float) $values['contribution_margin'] / $revenue * 100, 2, '.', '')
            : null;

        return $out;
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2) + 0.0, 2, '.', '');
    }
}
