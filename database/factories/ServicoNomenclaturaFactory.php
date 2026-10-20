<?php

namespace Database\Factories;

use App\Models\ServicoNomenclatura;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicoNomenclatura>
 */
class ServicoNomenclaturaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = 'Serviço '.fake()->unique()->words(3, true);

        return [
            'de' => $nome,
            'para' => $nome,
            'exibir' => false,
        ];
    }
}
