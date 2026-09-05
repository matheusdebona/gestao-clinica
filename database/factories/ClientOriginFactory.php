<?php

namespace Database\Factories;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientOrigin>
 */
class ClientOriginFactory extends Factory
{
    protected $model = ClientOrigin::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->unique()->randomElement([
                'Instagram',
                'Facebook',
                'Indicação',
                'Google',
                'Outros',
            ]).' '.fake()->unique()->numerify('##'),
            'is_active' => true,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
