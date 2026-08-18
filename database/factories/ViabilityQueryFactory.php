<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\ViabilityQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViabilityQuery>
 */
class ViabilityQueryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entry_type' => 'endereco',
            'input' => [
                'endereco' => fake()->streetAddress(),
                'cnae' => '4712100',
                'area' => 120.0,
            ],
            'result' => [
                'veredito_locacional' => ['resultado' => 'pendente'],
            ],
            'rules_versions' => [
                'louos' => [],
                'risco' => [],
                'territorio' => [],
            ],
            'resultado' => 'pendente',
            'ip_address' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Consulta anônima (HU-060): sem dono, com a origem (IP) registrada — o
     * schema aceita null; gravar só quando autenticado é regra do controller.
     */
    public function anonima(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'ip_address' => fake()->ipv4(),
        ]);
    }
}
