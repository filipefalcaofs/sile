<?php

namespace Database\Factories;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViabilityRequestTransition>
 */
class ViabilityRequestTransitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory(),
            'from_status' => ViabilityRequestStatus::Rascunho,
            'to_status' => ViabilityRequestStatus::Protocolada,
            'reason' => null,
            'public_label' => null,
            'actor_user_id' => null,
        ];
    }
}
