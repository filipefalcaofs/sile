<?php

namespace Database\Factories;

use App\Models\LegalTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalTerm>
 */
class LegalTermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'lgpd',
            'version' => 1,
            'title' => 'Termo de Consentimento LGPD',
            'content' => fake()->paragraphs(3, true),
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the term is published (in force).
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
        ]);
    }
}
