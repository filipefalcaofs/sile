<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GeoServerLayerFactory extends Factory
{
    public function definition(): array
    {
        $sufixo = fake()->unique()->numerify('####');

        return [
            'workspace' => 'louos_teste_'.$sufixo,
            'type_name' => 'VM_L_Z_USO_TESTE_'.$sufixo,
            'label' => null,
            'ativo' => true,
            'ordem' => 0,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
