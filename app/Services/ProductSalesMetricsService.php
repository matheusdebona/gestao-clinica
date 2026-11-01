<?php

namespace App\Services;

use App\Models\Sale;
use App\Support\ClinicMonth;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Vendas por produto: unidades vendidas e faturamento de cada produto no período.
 *
 * Mesmo recorte do faturamento das métricas comerciais: vendas confirmadas com `sold_at` dentro
 * dos dias de São Paulo do período.
 *
 * Faturamento do item = valor efetivo da venda repartido entre os itens pelo total de cada linha
 * (line_total já tem o desconto do item; o fator efetivo/soma das linhas aplica o desconto ou
 * ajuste da venda) — o mesmo rateio de SaleEconomics. Assim a soma dos produtos é o faturamento
 * das métricas comerciais.
 *
 * Protocolos não viram produto: ao aplicar um protocolo, a venda ganha as linhas dos produtos dele
 * (source_protocol_id marca a origem). Cada produto conta pelo que foi vendido, venha de protocolo
 * ou avulso; a visão "por protocolo" agrupa as mesmas linhas pela origem.
 *
 * Venda confirmada sem itens (ou com itens zerados) entra só como "sem produto", para os totais
 * baterem com o faturamento. Extras cobrados na sessão não entram (não estão no valor da venda;
 * a DRE e a margem de contribuição os somam à receita).
 */
class ProductSalesMetricsService
{
    /**
     * @param  array{product_type_id?: int|null, product_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): array
    {
        [$fromStart, $toEnd] = ClinicMonth::dayBounds($from, $to);
        $clinicId = (int) CurrentClinic::id();
        $typeId = $filters['product_type_id'] ?? null;
        $productId = $filters['product_id'] ?? null;
        $filtered = $typeId !== null || $productId !== null;

        $periodRevenue = (float) Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$fromStart, $toEnd])
            ->sum('effective_amount');
        $periodSales = (int) Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$fromStart, $toEnd])
            ->count();

        $revenueExpr = 'si.line_total * s.effective_amount / lt.lines_total';

        $rows = $this->lines($clinicId, $fromStart, $toEnd)
            ->when($typeId !== null, fn (Builder $q) => $q->where('p.product_type_id', $typeId))
            ->when($productId !== null, fn (Builder $q) => $q->where('si.product_id', $productId))
            ->groupBy('si.product_id', 'p.name', 'pt.id', 'pt.name', 'u.symbol')
            ->select('si.product_id', 'p.name as product_name', 'pt.id as product_type_id', 'pt.name as product_type_name', 'u.symbol as unit_symbol')
            ->selectRaw('SUM(si.quantity) as units')
            ->selectRaw("SUM({$revenueExpr}) as revenue")
            ->selectRaw('SUM(si.line_total) as lines_total')
            ->selectRaw('SUM(si.list_line_total) as list_total')
            ->selectRaw('COUNT(DISTINCT si.sale_id) as sales_count')
            ->selectRaw("SUM(CASE WHEN si.source_protocol_id IS NULL THEN 0 ELSE {$revenueExpr} END) as protocol_revenue")
            ->get();

        $products = $rows->map(fn ($r) => [
            'product_id' => (int) $r->product_id,
            'product_name' => (string) $r->product_name,
            'product_type_id' => $r->product_type_id !== null ? (int) $r->product_type_id : null,
            'product_type_name' => $r->product_type_name,
            'unit_symbol' => $r->unit_symbol,
            'units' => $this->qty((float) $r->units),
            'revenue' => $this->money((float) $r->revenue),
            'sales_count' => (int) $r->sales_count,
            'avg_unit_price' => (float) $r->units > 0 ? $this->money((float) $r->revenue / (float) $r->units) : null,
            'share_percent' => $periodRevenue > 0 ? $this->percent((float) $r->revenue / $periodRevenue * 100) : null,
            'list_total' => $this->money((float) $r->list_total),
            'discount_total' => $this->money(max(0.0, (float) $r->list_total - (float) $r->revenue)),
            'protocol_revenue' => $this->money((float) $r->protocol_revenue),
        ])
            ->sortByDesc(fn (array $row) => (float) $row['revenue'])
            ->values();

        $productsRevenue = (float) $products->sum(fn (array $row) => (float) $row['revenue']);
        $productsSales = $filtered
            ? (int) (clone $this->lines($clinicId, $fromStart, $toEnd))
                ->when($typeId !== null, fn (Builder $q) => $q->where('p.product_type_id', $typeId))
                ->when($productId !== null, fn (Builder $q) => $q->where('si.product_id', $productId))
                ->distinct()->count('si.sale_id')
            : null;
        // Sem filtro, o que faltar para o faturamento do período é venda sem itens (ou arredondamento).
        $unallocated = $filtered ? 0.0 : round($periodRevenue - round($productsRevenue, 2), 2);

        $protocols = $filtered ? [] : $this->byProtocol($clinicId, $fromStart, $toEnd, $periodRevenue);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'date_field' => 'sold_at',
            'filters' => [
                'product_type_id' => $typeId,
                'product_id' => $productId,
            ],
            'totals' => [
                'units' => $this->qty((float) $products->sum(fn (array $row) => (float) $row['units'])),
                'revenue' => $this->money($productsRevenue),
                'products_count' => $products->count(),
                'sales_count' => $productsSales ?? $periodSales,
                'share_percent' => $periodRevenue > 0 ? $this->percent($productsRevenue / $periodRevenue * 100) : null,
                'unallocated_revenue' => $this->money($unallocated),
                'period_revenue' => $this->money($periodRevenue),
                'period_sales_count' => $periodSales,
            ],
            'products' => $products->all(),
            'protocols' => $protocols,
            'options' => $this->options($clinicId, $fromStart, $toEnd),
        ];
    }

    /** Linhas de itens das vendas confirmadas do período, com a soma das linhas de cada venda. */
    private function lines(int $clinicId, CarbonImmutable $fromStart, CarbonImmutable $toEnd): Builder
    {
        $lineTotals = DB::table('sale_items')
            ->select('sale_id')
            ->selectRaw('SUM(line_total) as lines_total')
            ->groupBy('sale_id');

        return DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->joinSub($lineTotals, 'lt', 'lt.sale_id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('product_types as pt', 'pt.id', '=', 'p.product_type_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'p.unit_of_measure_id')
            ->where('s.clinic_id', $clinicId)
            ->where('s.status', Sale::STATUS_CONFIRMED)
            ->whereNotNull('s.sold_at')
            ->whereBetween('s.sold_at', [$fromStart, $toEnd])
            ->where('lt.lines_total', '>', 0);
    }

    /**
     * Mesmas linhas agrupadas pela origem (protocolo aplicado ou avulso).
     *
     * @return list<array<string, mixed>>
     */
    private function byProtocol(int $clinicId, CarbonImmutable $fromStart, CarbonImmutable $toEnd, float $periodRevenue): array
    {
        return $this->lines($clinicId, $fromStart, $toEnd)
            ->leftJoin('protocols as pr', 'pr.id', '=', 'si.source_protocol_id')
            ->groupBy('si.source_protocol_id', 'pr.name')
            ->select('si.source_protocol_id', 'pr.name as protocol_name')
            ->selectRaw('SUM(si.line_total * s.effective_amount / lt.lines_total) as revenue')
            ->selectRaw('COUNT(DISTINCT si.sale_id) as sales_count')
            ->selectRaw('COUNT(DISTINCT si.product_id) as products_count')
            ->get()
            ->map(fn ($r) => [
                'protocol_id' => $r->source_protocol_id !== null ? (int) $r->source_protocol_id : null,
                'protocol_name' => $r->source_protocol_id !== null ? (string) ($r->protocol_name ?? 'Protocolo') : null,
                'revenue' => $this->money((float) $r->revenue),
                'sales_count' => (int) $r->sales_count,
                'products_count' => (int) $r->products_count,
                'share_percent' => $periodRevenue > 0 ? $this->percent((float) $r->revenue / $periodRevenue * 100) : null,
            ])
            ->sortByDesc(fn (array $row) => (float) $row['revenue'])
            ->values()
            ->all();
    }

    /**
     * Opções dos filtros: tipos de produto da clínica e produtos vendidos no período.
     *
     * @return array{product_types: list<array{id: int, name: string}>, products: list<array{id: int, name: string, product_type_id: int|null}>}
     */
    private function options(int $clinicId, CarbonImmutable $fromStart, CarbonImmutable $toEnd): array
    {
        $types = DB::table('product_types')->where('clinic_id', $clinicId)->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'name' => (string) $t->name])->all();

        $products = $this->lines($clinicId, $fromStart, $toEnd)
            ->select('p.id', 'p.name', 'p.product_type_id')
            ->distinct()
            ->orderBy('p.name')
            ->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'product_type_id' => $p->product_type_id !== null ? (int) $p->product_type_id : null,
            ])->all();

        return ['product_types' => $types, 'products' => $products];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function qty(float $value): string
    {
        return number_format($value, 4, '.', '');
    }

    private function percent(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
