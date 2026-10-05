<?php

namespace Database\Factories;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViabilityRequestEtapa>
 */
class ViabilityRequestEtapaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory(),
            'etapa' => fake()->randomElement(EtapaMotor::cases()),
            'servico' => fn (array $atributos): string => ($atributos['etapa'] instanceof EtapaMotor
                ? $atributos['etapa']
                : EtapaMotor::from($atributos['etapa']))->servico(),
            'situacao' => SituacaoEtapa::Ok,
            'motivo' => null,
            'tentativas' => 1,
            'ultima_tentativa_em' => now(),
        ];
    }

    public function indisponivel(): static
    {
        return $this->state(fn (): array => [
            'situacao' => SituacaoEtapa::Indisponivel,
            'motivo' => 'Serviço sem resposta',
            'primeira_falha_em' => now(),
            'proxima_tentativa_em' => now()->subMinute(),
        ]);
    }
}
