<?php

namespace Database\Factories;

use App\Models\TerritorialRule;
use App\Models\TerritorialRuleCnae;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TerritorialRuleCnae>
 */
class TerritorialRuleCnaeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'territorial_rule_id' => TerritorialRule::factory(),
            'cnae' => fake()->unique()->numerify('#######'),
        ];
    }
}
