<?php

namespace Database\Factories;

use App\Models\ReginRecebimento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReginRecebimento>
 */
class ReginRecebimentoFactory extends Factory
{
    protected $model = ReginRecebimento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $protocolo = (string) fake()->unique()->numerify('#####');

        return [
            'protocolo' => $protocolo,
            'cnpj_destino' => '13927801000149',
            'cnpj_empresa' => '13927801000149',
            'cnpj_origem' => '13927801000149',
            'cod_funcao' => 103,
            'nire' => '00000000000',
            'servico' => 'WsProSol098',
            'data_geracao' => now(),
            'corpo' => ['INFORMACOES_COMPLEMENTARES' => ['RES_AREA' => 834]],
            'envelope' => [
                'protocolo' => $protocolo,
                'codFuncao' => 103,
            ],
        ];
    }
}
