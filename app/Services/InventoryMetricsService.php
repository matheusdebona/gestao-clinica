<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InventoryMetricsService
{
    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     kpis: array<string, mixed>,
     *     low_stock_products: list<array<string, mixed>>,
     *     notes: list<string>
     * }
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $fromStart = $from->startOfDay();
        $toEnd = $to->endOfDay();
        $windowDays = max(1, $fromStart->diffInDays($to->startOfDay()) + 1);

        $activeProducts = Product::query()->where('is_active', true);

        $lowStockCount = (int) (clone $activeProducts)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->count();

        $negativeStockCount = (int) (clone $activeProducts)
            ->where('stock_quantity', '<', 0)
            ->count();

        $inventoryValue = (float) (clone $activeProducts)
            ->selectRaw('COALESCE(SUM(stock_quantity * cost), 0) as inventory_value')
            ->value('inventory_value');

        $consumptionQuantity = (float) StockMovement::query()
            ->where('type', StockMovementType::Out->value)
            ->where('reason', 'appointment_complete')
            ->whereBetween('created_at', [$fromStart, $toEnd])
            ->sum('quantity');

        $consumptionByProduct = StockMovement::query()
            ->select('product_id', DB::raw('COALESCE(SUM(quantity), 0) as qty'))
            ->where('type', StockMovementType::Out->value)
            ->where('reason', 'appointment_complete')
            ->whereBetween('created_at', [$fromStart, $toEnd])
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $lowStockProducts = Product::query()
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($consumptionByProduct, $windowDays) {
                $consumed = (float) ($consumptionByProduct[$product->id] ?? 0);
                $avgDaily = $consumed / $windowDays;
                $stock = (float) $product->stock_quantity;
                $coverageDays = $avgDaily > 0
                    ? number_format($stock / $avgDaily, 2, '.', '')
                    : null;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock_quantity' => number_format($stock, 4, '.', ''),
                    'min_stock' => number_format((float) $product->min_stock, 4, '.', ''),
                    'lead_time_days' => (int) $product->lead_time_days,
                    'coverage_days' => $coverageDays,
                ];
            })
            ->values()
            ->all();

        return [
            'from' => $fromStart->toDateString(),
            'to' => $toEnd->toDateString(),
            'kpis' => [
                'low_stock_count' => $lowStockCount,
                'inventory_value' => $this->money($inventoryValue),
                'negative_stock_count' => $negativeStockCount,
                'consumption_quantity' => number_format($consumptionQuantity, 4, '.', ''),
            ],
            'low_stock_products' => $lowStockProducts,
            'notes' => [
                'Snapshot KPIs use current stock; consumption and coverage_days use the selected window.',
                'coverage_days = current stock ÷ average daily appointment consumption in the window (null when consumption is 0).',
            ],
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
