<?php

namespace Database\Factories;

use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViabilityRequestEvento>
 */
class ViabilityRequestEventoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory(),
            'codigo' => '101',
            'nome' => 'Inscrição de primeiro estabelecimento',
        ];
    }
}
