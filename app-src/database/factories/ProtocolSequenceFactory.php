<?php

namespace Database\Factories;

use App\Models\ProtocolSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProtocolSequence>
 */
class ProtocolSequenceFactory extends Factory
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
