<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        $qty = 1;
        $unitPrice = '100.00';

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'source_protocol_id' => null,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'unit_cost' => '10.0000',
            'min_unit_price' => '80.00',
            'line_total' => number_format($qty * (float) $unitPrice, 2, '.', ''),
        ];
    }
}
