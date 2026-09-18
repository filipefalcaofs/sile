<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ZonaFactory extends Factory
{
    public function definition(): array
    {
        $sufixo = fake()->unique()->numerify('####');

        return [
            'codigo' => 'ZT-'.$sufixo,
            'nome' => 'Zona de Teste '.$sufixo,
            'macrozona' => null,
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
