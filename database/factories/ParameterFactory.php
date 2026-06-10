<?php

namespace Database\Factories;

use App\Models\Parameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parameter>
 */
class ParameterFactory extends Factory
{
    /**
     * `sensitive` vem ANTES de `value` no array: o mutator condicional de
     * `value` lê `$this->sensitive`, e o fill processa as chaves na ordem do
     * definition — invertida, o valor sensível seria gravado em claro.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->lexify('grupo.chave.????'),
            'group' => 'geral',
            'type' => 'string',
            'sensitive' => false,
            'value' => null,
            'default_value' => fake()->word(),
            'validation_rules' => ['required', 'string'],
            'description' => fake()->sentence(3),
            'requires_connection_test' => false,
        ];
    }

    public function sensitive(): static
    {
        return $this->state(fn () => ['sensitive' => true]);
    }

    public function integer(string $default = '10'): static
    {
        return $this->state(fn () => [
            'type' => 'integer',
            'default_value' => $default,
            'validation_rules' => ['required', 'integer'],
        ]);
    }
}
