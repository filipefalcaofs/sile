<?php

namespace Database\Factories;

use App\Enums\TipoGatilho;
use App\Models\ExpressoQueda;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpressoQueda>
 */
class ExpressoQuedaFactory extends Factory
{
    /**
     * Default = queda por CNAE com gatilho semi-expresso REAL (ZEIS especial) e
     * dimensão decisiva municipal — o caso classificado por gatilho da HU-145.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory()->protocoled(),
            'cnae' => fake()->numerify('#######'),
            'tipo_gatilho' => TipoGatilho::ZeisEspecial->value,
            'dimensao' => 'municipal',
            'motivo' => 'CNAE em ZEIS especial encaminhado à análise técnica',
        ];
    }

    /**
     * Queda de nível-PROCESSO (motor degradado): cnae/tipo_gatilho/dimensao NULL,
     * só o motivo textual — a linha honesta gravada quando o motor não resolveu
     * (toggle off / veredito pendente sem zona). NUNCA inventa gatilho (RN-001).
     */
    public function nivelProcesso(string $motivo = 'fluxo expresso desativado'): static
    {
        return $this->state(fn (): array => [
            'cnae' => null,
            'tipo_gatilho' => null,
            'dimensao' => null,
            'motivo' => $motivo,
        ]);
    }

    /**
     * Queda por CNAE NÃO classificado (sem gatilho): tipo_gatilho NULL com cnae
     * presente — o motor não tinha classificação vigente e encaminhou à análise.
     */
    public function semGatilho(): static
    {
        return $this->state(fn (): array => [
            'tipo_gatilho' => null,
            'dimensao' => 'municipal',
            'motivo' => 'Classificação de risco não parametrizada para o CNAE',
        ]);
    }
}
