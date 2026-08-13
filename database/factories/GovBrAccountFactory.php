<?php

namespace Database\Factories;

use App\Models\GovBrAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GovBrAccount>
 */
class GovBrAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reliability_level' => 'bronze',
            'reliability_levels' => [1],
            'linked_at' => now(),
            'last_authenticated_at' => null,
        ];
    }

    public function prata(): static
    {
        return $this->state(fn () => [
            'reliability_level' => 'prata',
            'reliability_levels' => [1, 2],
        ]);
    }

    public function ouro(): static
    {
        return $this->state(fn () => [
            'reliability_level' => 'ouro',
            'reliability_levels' => [1, 2, 3],
        ]);
    }
}
