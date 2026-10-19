<?php

namespace Database\Factories;

use App\Enums\FonteEdificacao;
use App\Models\BuildingFootprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sem geometria: em SQLite a coluna não é preenchida; em PostGIS o teste grava
 * a geometria pelo repositório.
 *
 * @extends Factory<BuildingFootprint>
 */
class BuildingFootprintFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => FonteEdificacao::Overture,
            'source_id' => fake()->uuid(),
            'release' => '2026-09-23.1',
            'area_m2' => 120.0,
            'confidence' => null,
            'source_datasets' => null,
            'properties' => [],
        ];
    }
}
