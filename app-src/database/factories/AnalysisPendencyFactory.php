<?php

namespace Database\Factories;

use App\Enums\AnalysisPendencyStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisPendency>
 *
 * Default = pendência ABERTA com prazo de 15 dias (HU-083/084 — ciclo interno).
 * Os states cobrem a resposta dentro do prazo e a expiração sem resposta.
 */
class AnalysisPendencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory()->protocoled(),
            'requested_by_user_id' => User::factory(),
            'description' => fake()->sentence(),
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->addDays(15),
            'responded_at' => null,
            'response' => null,
        ];
    }

    /**
     * Pendência respondida pelo requerente (dentro do prazo).
     */
    public function respondida(): static
    {
        return $this->state(fn () => [
            'status' => AnalysisPendencyStatus::Respondida,
            'responded_at' => now(),
            'response' => fake()->sentence(),
        ]);
    }

    /**
     * Pendência expirada — prazo vencido sem resposta.
     */
    public function expirada(): static
    {
        return $this->state(fn () => [
            'status' => AnalysisPendencyStatus::Expirada,
            'due_at' => now()->subDay(),
        ]);
    }
}
