<?php

namespace Database\Factories;

use App\Models\BenchmarkMunicipalityMetric;
use App\Support\Capitais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BenchmarkMunicipalityMetric>
 */
class BenchmarkMunicipalityMetricFactory extends Factory
{
    protected $model = BenchmarkMunicipalityMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $media = fake()->randomFloat(2, 0.5, 120);

        return [
            'uf' => 'BA',
            'municipio' => fake()->unique()->city(),
            'ano' => 2026,
            'mes' => 8,
            'n' => fake()->numberBetween(1, 900),
            'media' => $media,
            'p50' => round($media * 0.4, 2),
            'p75' => round($media * 0.9, 2),
            'p90' => round($media * 1.6, 2),
            'p95' => round($media * 2.1, 2),
            'p99' => round($media * 2.4, 2),
            'maximo' => round($media * 2.6, 2),
        ];
    }

    /**
     * Métrica de uma capital, pelo nome exato com que a base federal a publica.
     */
    public function capital(string $uf): static
    {
        return $this->state(fn (): array => [
            'uf' => $uf,
            'municipio' => Capitais::todas()[$uf],
        ]);
    }

    public function salvador(): static
    {
        return $this->capital(Capitais::UF_SALVADOR);
    }

    public function comMedia(float $media): static
    {
        return $this->state(fn (): array => ['media' => $media]);
    }
}
