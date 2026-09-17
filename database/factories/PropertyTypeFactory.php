<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'tipo_'.fake()->unique()->numerify('####'),
            'label' => ucfirst(fake()->words(2, true)),
            'drives_rule' => false,
            'active' => true,
        ];
    }

    public function drivesRule(): static
    {
        return $this->state(fn () => ['drives_rule' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
