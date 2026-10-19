<?php

namespace Database\Factories;

use App\Enums\PoligonoFonte;
use App\Enums\StatusResolucaoPoligono;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LotPolygonResolution>
 */
class LotPolygonResolutionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory(),
            'source' => PoligonoFonte::Overture,
            'status' => StatusResolucaoPoligono::NaoEncontrado,
            'official' => false,
            'latency_ms' => 5,
        ];
    }
}
