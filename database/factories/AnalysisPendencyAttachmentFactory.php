<?php

namespace Database\Factories;

use App\Models\AnalysisPendency;
use App\Models\AnalysisPendencyAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisPendencyAttachment>
 */
class AnalysisPendencyAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'analysis_pendency_id' => AnalysisPendency::factory(),
            'disk' => 'local',
            'path' => 'convites/'.fake()->uuid().'.pdf',
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 5_000_000),
            'sha256' => hash('sha256', fake()->uuid()),
        ];
    }
}
