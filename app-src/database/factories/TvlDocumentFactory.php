<?php

namespace Database\Factories;

use App\Models\TvlDocument;
use App\Models\User;
use App\Models\ViabilityDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TvlDocument>
 *
 * Default = documento TVL de uma decisão DEFERIDA (ViabilityDecisionFactory já é
 * deferida por padrão), no disco 'local', com verification_code único (HU-132).
 */
class TvlDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_decision_id' => ViabilityDecision::factory(),
            'disk' => 'local',
            'path' => 'tvl/'.fake()->uuid().'.pdf',
            'verification_code' => 'TVL-DOC-'.fake()->unique()->numerify('########'),
            'generated_by_user_id' => User::factory(),
            'generated_at' => now(),
        ];
    }
}
