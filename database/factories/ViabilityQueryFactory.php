<?php

namespace Database\Factories;

use App\Models\Cidadao;
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
            'cidadao_id' => Cidadao::factory(),
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
            'cidadao_id' => null,
            'ip_address' => fake()->ipv4(),
        ]);
    }
}
