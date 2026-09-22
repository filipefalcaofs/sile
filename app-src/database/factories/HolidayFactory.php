<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'),
            'name' => $this->faker->sentence(2),
            'recurring_annually' => false,
            'active' => true,
        ];
    }

    /**
     * Feriado fixo recorrente anualmente (ex.: 25/12 Natal).
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes): array => ['recurring_annually' => true]);
    }

    /**
     * Feriado inativo (mantido no histórico, fora do cálculo de dias úteis).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['active' => false]);
    }
}
