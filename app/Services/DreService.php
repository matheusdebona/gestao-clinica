<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Sale;
use Carbon\CarbonImmutable;

/**
 * DRE gerencial do mês (competência).
 *
 * Receita e custos variáveis vêm da margem de contribuição (vendas confirmadas no mês; insumos,
 * taxas, antecipação, impostos e comissões dessas vendas; perdas de estoque do mês). Despesas
 * vêm do módulo de despesas pelo mês de competência, agrupadas pela natureza da categoria.
 * Remuneração por metas e por faturamento (lançamentos de comissão sem venda) entra nos custos
 * variáveis pelo mês de competência, pendente ou paga, como as comissões de venda/sessão.
 *
 * Ponto de equilíbrio = despesas do mês (exceto variáveis) ÷ margem de contribuição %.
 */
class DreService
{
    private const EXPENSE_LINES = [
        ExpenseCategory::KIND_FIXED => 'fixed',
        ExpenseCategory::KIND_PERSONNEL => 'personnel',
        ExpenseCategory::KIND_MARKETING => 'marketing',
        ExpenseCategory::KIND_TAXES => 'expense_taxes',
        ExpenseCategory::KIND_FINANCIAL => 'financial',
        ExpenseCategory::KIND_DEPRECIATION => 'depreciation',
    ];

    public function __construct(
        private readonly ContributionMarginService $margin,
        private readonly ExpenseGenerator $generator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compare(int $clinicId, CarbonImmutable $month): array
    {
        $m = $month->startOfMonth();
        $previous = $m->subMonthNoOverflow();

        return [
            'month' => $m->format('Y-m'),
            'previous_month' => $previous->format('Y-m'),
            'current' => $this->forMonth($clinicId, $m),
            'previous' => $this->forMonth($clinicId, $previous),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forMonth(int $clinicId, CarbonImmutable $month): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();

        if ($from->lte(CarbonImmutable::now()->startOfMonth())) {
            $this->generator->generateMonth($clinicId, $from);
        }

        $cm = $this->margin->summarize($from, $to, salesLimit: 0)['totals'];

        $sales = Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('items:id,sale_id,line_total,list_line_total')
            ->get(['id', 'effective_amount']);
        $netRevenue = (float) $cm['revenue'];
        $effective = 0.0;
        $listTotal = 0.0;
        foreach ($sales as $sale) {
            $eff = (float) $sale->effective_amount;
            // Preço de tabela por item; itens antigos sem tabela usam o preço negociado.
            $list = $sale->items->sum(fn ($i) => (float) $i->list_line_total > 0 ? (float) $i->list_line_total : (float) $i->line_total);
            $effective += $eff;
            // Venda acima da tabela não gera desconto negativo.
            $listTotal += max($list, $eff);
        }
        $extras = $netRevenue - $effective;
        $grossRevenue = $listTotal + $extras;
        $discounts = $grossRevenue - $netRevenue;

        $expenses = Expense::query()
            ->whereDate('competence_month', $from->toDateString())
            ->with('category')
            ->get();

        $byKind = [];
        foreach ($expenses as $e) {
            $kind = $e->category?->kind ?? ExpenseCategory::KIND_FIXED;
            $name = $e->category?->name ?? 'Sem categoria';
            $byKind[$kind][$name] = ($byKind[$kind][$name] ?? 0) + (float) $e->amount;
        }

        $otherVariable = array_sum($byKind[ExpenseCategory::KIND_VARIABLE] ?? []);
        $variable = (float) $cm['supplies'] + (float) $cm['card_fees'] + (float) $cm['anticipation']
            + (float) $cm['taxes'] + (float) $cm['commissions'] + (float) $cm['goal_commissions']
            + (float) $cm['losses'] + $otherVariable;
        $contribution = $netRevenue - $variable;

        $lines = [
            'gross_revenue' => $grossRevenue,
            'discounts' => $discounts,
            'net_revenue' => $netRevenue,
            'supplies' => (float) $cm['supplies'],
            'card_fees' => (float) $cm['card_fees'],
            'anticipation' => (float) $cm['anticipation'],
            'taxes' => (float) $cm['taxes'],
            'commissions' => (float) $cm['commissions'],
            'goal_commissions' => (float) $cm['goal_commissions'],
            'losses' => (float) $cm['losses'],
            'other_variable' => $otherVariable,
            'variable_costs' => $variable,
            'contribution_margin' => $contribution,
        ];

        $breakdown = [];
        $operating = 0.0;
        foreach (self::EXPENSE_LINES as $kind => $key) {
            $cats = $byKind[$kind] ?? [];
            arsort($cats);
            $total = array_sum($cats);
            $lines[$key] = $total;
            $operating += $total;
            $breakdown[$key] = collect($cats)->map(fn ($v, $n) => ['name' => $n, 'amount' => $this->money($v)])->values()->all();
        }
        $breakdown['other_variable'] = collect($byKind[ExpenseCategory::KIND_VARIABLE] ?? [])
            ->map(fn ($v, $n) => ['name' => $n, 'amount' => $this->money($v)])->values()->all();

        $lines['operating_expenses'] = $operating;
        $lines['net_result'] = $contribution - $operating;

        $mcRatio = $netRevenue > 0 ? $contribution / $netRevenue : null;
        $breakEven = $mcRatio !== null && $mcRatio > 0 ? $operating / $mcRatio : null;

        return [
            'lines' => array_map(fn ($v) => $this->money($v), $lines),
            'breakdown' => $breakdown,
            'contribution_margin_percent' => $mcRatio !== null ? number_format($mcRatio * 100, 2, '.', '') : null,
            'net_margin_percent' => $netRevenue > 0 ? number_format($lines['net_result'] / $netRevenue * 100, 2, '.', '') : null,
            'break_even_revenue' => $breakEven !== null ? $this->money($breakEven) : null,
            'break_even_gap' => $breakEven !== null ? $this->money($netRevenue - $breakEven) : null,
            'supplies_estimated' => $cm['supplies_estimated'],
            'sales_count' => $cm['sales_count'],
            'expenses_count' => $expenses->count(),
        ];
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2) + 0.0, 2, '.', '');
    }
}
