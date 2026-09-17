<?php

namespace Database\Factories;

use App\Models\ReginSimulacaoExecucao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReginSimulacaoExecucao>
 */
class ReginSimulacaoExecucaoFactory extends Factory
{
    protected $model = ReginSimulacaoExecucao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => '43747',
            'relatorio' => [
                'codigo' => '43747',
                'origem' => 'simulacao_protocolo',
            ],
        ];
    }
}
