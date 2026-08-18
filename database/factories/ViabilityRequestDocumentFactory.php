<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViabilityRequestDocument>
 */
class ViabilityRequestDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory(),
            'requirement_id' => null,
            'disk' => 'local',
            'path' => 'solicitacoes/'.fake()->uuid().'.pdf',
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 5_000_000),
            'sha256' => hash('sha256', fake()->uuid()),
            'uploaded_by_user_id' => User::factory(),
        ];
    }
}
