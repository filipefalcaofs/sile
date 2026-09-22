<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Inspection;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory(),
            'tipo' => InspectionType::Localizacao,
            'status' => InspectionStatus::EmPreenchimento,
            'vistoriador_user_id' => User::factory(),
            'opened_at' => now(),
        ];
    }

    /**
     * Ficha concluída (parecer preenchido + concluded_at) — imutável.
     */
    public function concluida(): static
    {
        return $this->state(fn () => [
            'status' => InspectionStatus::Concluida,
            'parecer' => fake()->paragraph(),
            'concluded_at' => now(),
        ]);
    }
}
