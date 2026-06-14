<?php

namespace Database\Factories;

use App\Enums\RiscoSanitario;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SanitaryRiskClassification>
 */
class SanitaryRiskClassificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_version_id' => RuleVersion::factory(),
            'cnae_code' => $this->faker->numerify('#######'),
            'risco_sanitario' => $this->faker->randomElement(RiscoSanitario::cases()),
            'macroarea' => $this->faker->randomElement(['Alimentos', 'Serviços de saúde', 'Interesse da saúde']),
            'autorizado_escritorio_virtual' => $this->faker->boolean(),
            'autorizado_mei' => $this->faker->boolean(),
            'exige_rt' => $this->faker->boolean(),
            'observacao' => null,
        ];
    }
}
