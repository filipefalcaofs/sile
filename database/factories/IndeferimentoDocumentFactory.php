<?php

namespace Database\Factories;

use App\Models\IndeferimentoDocument;
use App\Models\User;
use App\Models\ViabilityDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndeferimentoDocument>
 *
 * Default = documento de uma decisão INDEFERIDA, no disco 'local', com
 * verification_code único (espelho do TvlDocumentFactory).
 */
class IndeferimentoDocumentFactory extends Factory
{
    protected $model = IndeferimentoDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_decision_id' => ViabilityDecision::factory()->indeferida(),
            'disk' => 'local',
            'path' => 'indeferimento/'.fake()->uuid().'.pdf',
            'verification_code' => 'IND-DOC-'.fake()->unique()->numerify('########'),
            'generated_by_user_id' => User::factory(),
            'generated_at' => now(),
        ];
    }
}
