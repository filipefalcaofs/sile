<?php

namespace Database\Factories;

use App\Models\Cidadao;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalTermAcceptance>
 */
class LegalTermAcceptanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'accepted_by_type' => Cidadao::class,
            'accepted_by_id' => Cidadao::factory(),
            'legal_term_id' => LegalTerm::factory()->published(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'accepted_at' => now(),
        ];
    }
}
