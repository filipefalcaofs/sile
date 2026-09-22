<?php

namespace Database\Factories;

use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyTypeAliasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_type_id' => PropertyType::factory(),
            'alias' => fake()->unique()->words(2, true),
        ];
    }
}
