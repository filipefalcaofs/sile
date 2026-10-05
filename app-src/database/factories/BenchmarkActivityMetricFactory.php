<?php

namespace Database\Factories;

use App\Models\BenchmarkActivityMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BenchmarkActivityMetric>
 */
class BenchmarkActivityMetricFactory extends Factory
{
    protected $model = BenchmarkActivityMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $media = fake()->randomFloat(2, 0.5, 120);

        return [
            'escopo' => BenchmarkActivityMetric::ESCOPO_SALVADOR,
            'cnae' => (string) fake()->unique()->numerify('#######'),
            'ano' => 2026,
            'mes' => 8,
            'n' => fake()->numberBetween(1, 300),
            'media' => $media,
            'p50' => round($media * 0.5, 2),
            'p90' => round($media * 1.7, 2),
        ];
    }

    public function brasil(): static
    {
        return $this->state(fn (): array => ['escopo' => BenchmarkActivityMetric::ESCOPO_BRASIL]);
    }
}
