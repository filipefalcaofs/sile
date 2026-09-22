<?php

namespace Database\Factories;

use App\Models\DocumentRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequirement>
 */
class DocumentRequirementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'req-'.fake()->unique()->numerify('####'),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => null,
            'required' => true,
            'active' => true,
            'validation_instructions' => null,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['required' => true]);
    }

    public function optional(): static
    {
        return $this->state(fn () => ['required' => false]);
    }
}
