<?php

namespace Database\Factories;

use App\Enums\ParameterProposalStatus;
use App\Models\Parameter;
use App\Models\ParameterProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParameterProposal>
 */
class ParameterProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parameter_id' => Parameter::factory(),
            'proposed_value' => '1',
            'created_by' => User::factory(),
            'status' => ParameterProposalStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
