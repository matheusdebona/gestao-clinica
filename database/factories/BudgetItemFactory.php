<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetItem>
 */
class BudgetItemFactory extends Factory
{
    protected $model = BudgetItem::class;

    public function definition(): array
    {
        $qty = 1;
        $list = '120.00';
        $price = '100.00';

        return [
            'budget_id' => Budget::factory(),
            'product_id' => Product::factory(),
            'source_protocol_id' => null,
            'product_name' => fake()->words(3, true),
            'quantity' => $qty,
            'list_unit_price' => $list,
            'list_line_total' => number_format($qty * (float) $list, 2, '.', ''),
            'unit_price' => $price,
            'line_total' => number_format($qty * (float) $price, 2, '.', ''),
            'unit_cost' => '10.0000',
            'min_unit_price' => '80.00',
        ];
    }
}
