<?php

namespace Database\Factories;

use App\Models\TvlSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TvlSequence>
 */
class TvlSequenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => (int) now()->year,
            'last_number' => 0,
        ];
    }
}
