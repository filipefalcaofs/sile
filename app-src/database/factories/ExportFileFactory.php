<?php

namespace Database\Factories;

use App\Models\ExportFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportFile>
 *
 * Default = exportação CSV concluída de um usuário, no disco 'local', com
 * contagem de linhas e bag de filtros plausíveis (HU-131).
 */
class ExportFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'relatorios/exportacoes/'.fake()->uuid().'.csv',
            'filename' => 'relatorio-'.fake()->unique()->numerify('########').'.csv',
            'format' => 'csv',
            'row_count' => fake()->numberBetween(1, 5000),
            'filtros' => ['grupo' => 'em_andamento'],
        ];
    }
}
