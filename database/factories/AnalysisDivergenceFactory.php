<?php

namespace Database\Factories;

use App\Models\AnalysisDivergence;
use App\Models\AnalysisRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisDivergence>
 */
class AnalysisDivergenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'analysis_record_id' => AnalysisRecord::factory(),
            'cnae' => fake()->numerify('#######'),
            'field' => fake()->randomElement(['grupo_uso', 'valor_tll', 'status', 'condicionantes', 'vagas_exigidas']),
            'suggested_value' => 'permitido',
            'final_value' => 'permitido_com_condicoes',
            'justification' => 'Divergência justificada pela análise da documentação apresentada.',
        ];
    }
}
