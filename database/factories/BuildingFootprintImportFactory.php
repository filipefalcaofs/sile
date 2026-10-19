<?php

namespace Database\Factories;

use App\Enums\FonteEdificacao;
use App\Models\BuildingFootprintImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuildingFootprintImport>
 */
class BuildingFootprintImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => FonteEdificacao::Overture,
            'release' => '2026-09-23.1',
            'checksum' => hash('sha256', fake()->uuid()),
            'status' => BuildingFootprintImport::CONCLUIDA,
            'counters' => [],
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
            'error' => null,
        ];
    }
}
