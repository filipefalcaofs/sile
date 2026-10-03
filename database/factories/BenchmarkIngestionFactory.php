<?php

namespace Database\Factories;

use App\Models\BenchmarkIngestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BenchmarkIngestion>
 */
class BenchmarkIngestionFactory extends Factory
{
    protected $model = BenchmarkIngestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uf' => fake()->randomElement(['BA', 'SP', 'MG', 'RJ']),
            'ano' => 2026,
            'mes' => fake()->numberBetween(1, 12),
            'status' => BenchmarkIngestion::STATUS_SUCESSO,
            'url' => 'https://estatistica.redesim.gov.br/tempos-abertura-redesim/exportar/solicitacoes/BA?ano=2026&mes=8',
            'bytes' => fake()->numberBetween(100_000, 20_000_000),
            'hash' => fake()->sha256(),
            'linhas_lidas' => fake()->numberBetween(10, 40_000),
            'linhas_descartadas' => fake()->numberBetween(0, 500),
            'motivo' => null,
        ];
    }

    /**
     * Competência que não pôde ser ingerida — a tela mostra a lacuna.
     */
    public function falha(string $motivo = 'HTTP 500'): static
    {
        return $this->state(fn (): array => [
            'status' => BenchmarkIngestion::STATUS_FALHA,
            'bytes' => 0,
            'hash' => null,
            'linhas_lidas' => 0,
            'linhas_descartadas' => 0,
            'motivo' => $motivo,
        ]);
    }
}
