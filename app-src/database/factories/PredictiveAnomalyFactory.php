<?php

namespace Database\Factories;

use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use App\Models\PredictiveAnomaly;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PredictiveAnomaly>
 */
class PredictiveAnomalyFactory extends Factory
{
    protected $model = PredictiveAnomaly::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory(),
            'score' => $this->faker->numberBetween(70, 100),
            'severity' => AbuseSeverity::Media,
            'status' => AbuseAlertStatus::Aberto,
            'fingerprint' => 'req:'.$this->faker->unique()->numberBetween(1, 100000),
            'factors' => [['chave' => 'volume_cnpj', 'peso' => 40]],
            'window_start' => now()->subDays(30),
            'window_end' => now(),
            'detected_at' => now(),
        ];
    }

    public function alta(): static
    {
        return $this->state(fn () => ['severity' => AbuseSeverity::Alta, 'score' => 90]);
    }

    public function confirmada(): static
    {
        return $this->state(fn () => ['status' => AbuseAlertStatus::Confirmado, 'resolved_at' => now()]);
    }
}
