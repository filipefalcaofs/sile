<?php

namespace Database\Factories;

use App\Models\FineMeshReferral;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FineMeshReferral>
 *
 * Default = encaminhamento à malha fina EM ABERTO (resolved_at null, HU-136).
 * reason é sempre obrigatório. O state resolvido() dá baixa.
 */
class FineMeshReferralFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory()->protocoled(),
            'referred_by_user_id' => User::factory(),
            'reason' => fake()->sentence(),
            'resolved_at' => null,
        ];
    }

    /**
     * Encaminhamento resolvido (baixa da malha fina).
     */
    public function resolvido(): static
    {
        return $this->state(fn () => [
            'resolved_at' => now(),
        ]);
    }
}
