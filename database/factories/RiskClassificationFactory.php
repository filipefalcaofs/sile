<?php

namespace Database\Factories;

use App\Enums\RiscoMunicipal;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskClassification>
 */
class RiskClassificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_version_id' => RuleVersion::factory(),
            'cnae_code' => $this->faker->numerify('#######'),
            'risco_municipal' => $this->faker->randomElement(RiscoMunicipal::cases()),
            'condicionantes' => [],
            'observacao' => null,
        ];
    }
}
