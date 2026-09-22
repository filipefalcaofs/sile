<?php

namespace Database\Factories;

use App\Models\ViabilityServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViabilityServiceType>
 */
class ViabilityServiceTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'svc-'.fake()->unique()->numerify('####'),
            'name' => ucfirst(fake()->words(3, true)),
            'flow_hint' => null,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
