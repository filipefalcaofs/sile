<?php

namespace Database\Factories;

use App\Models\AnalysisAttachment;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisAttachment>
 */
class AnalysisAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->lexify('documento-????').'.pdf';

        return [
            'viability_request_id' => ViabilityRequest::factory(),
            'disk' => 'local',
            'path' => 'analises/'.fake()->uuid().'/'.$nome,
            'original_name' => $nome,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(50_000, 2_000_000),
            'sha256' => hash('sha256', fake()->uuid()),
            'uploaded_by_user_id' => User::factory(),
        ];
    }
}
