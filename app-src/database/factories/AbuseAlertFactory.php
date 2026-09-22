<?php

namespace Database\Factories;

use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use App\Models\AbuseAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbuseAlert>
 *
 * Default = alerta ABERTO de severidade média, recém-detectado (detected_at
 * now), sem baixa. fingerprint único por desenho (o índice parcial só barra
 * dois ABERTOS com o mesmo rule_key+fingerprint). States por status/severity.
 */
class AbuseAlertFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_key' => fake()->randomElement([
                'volume_cnpj',
                'volume_contador',
                'poligono_repetido',
                'escritorio_virtual',
            ]),
            'severity' => AbuseSeverity::Media,
            'status' => AbuseAlertStatus::Aberto,
            'fingerprint' => fake()->unique()->sha256(),
            'evidence' => ['ocorrencias' => fake()->numberBetween(2, 50)],
            'viability_request_id' => null,
            'window_start' => null,
            'window_end' => null,
            'detected_at' => now(),
            'resolved_by_user_id' => null,
            'resolved_at' => null,
            'justification' => null,
            'fine_mesh_referral_id' => null,
        ];
    }

    /**
     * Alerta aberto (estado inicial — aguardando triagem humana).
     */
    public function aberto(): static
    {
        return $this->state(fn (): array => [
            'status' => AbuseAlertStatus::Aberto,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
            'justification' => null,
        ]);
    }

    /**
     * Alerta confirmado por um gestor (baixa com justificativa obrigatória).
     */
    public function confirmado(): static
    {
        return $this->state(fn (): array => [
            'status' => AbuseAlertStatus::Confirmado,
            'resolved_by_user_id' => User::factory(),
            'resolved_at' => now(),
            'justification' => fake()->sentence(),
        ]);
    }

    /**
     * Alerta descartado por um gestor (falso positivo, baixa justificada).
     */
    public function descartado(): static
    {
        return $this->state(fn (): array => [
            'status' => AbuseAlertStatus::Descartado,
            'resolved_by_user_id' => User::factory(),
            'resolved_at' => now(),
            'justification' => fake()->sentence(),
        ]);
    }

    public function baixa(): static
    {
        return $this->state(fn (): array => ['severity' => AbuseSeverity::Baixa]);
    }

    public function media(): static
    {
        return $this->state(fn (): array => ['severity' => AbuseSeverity::Media]);
    }

    public function alta(): static
    {
        return $this->state(fn (): array => ['severity' => AbuseSeverity::Alta]);
    }
}
