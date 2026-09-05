<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentConsumption;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentConsumption>
 */
class AppointmentConsumptionFactory extends Factory
{
    protected $model = AppointmentConsumption::class;

    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'product_id' => Product::factory(),
            'sale_item_id' => null,
            'source' => AppointmentConsumption::SOURCE_SUGGESTED,
            'quantity' => '1.0000',
            'is_complimentary' => false,
            'charged_amount' => '0.00',
        ];
    }
}
