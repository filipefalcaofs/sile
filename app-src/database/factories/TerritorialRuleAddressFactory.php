<?php

namespace Database\Factories;

use App\Models\TerritorialRule;
use App\Models\TerritorialRuleAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TerritorialRuleAddress>
 */
class TerritorialRuleAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'territorial_rule_id' => TerritorialRule::factory(),
            'rotulo' => fake()->company(),
            'cod_log' => fake()->numerify('####'),
            'logradouro_nome' => null,
            'numero_porta' => fake()->numerify('###'),
        ];
    }
}
