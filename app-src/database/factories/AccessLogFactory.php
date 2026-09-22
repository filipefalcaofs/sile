<?php

namespace Database\Factories;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessLog>
 */
class AccessLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->safeEmail(),
            'event' => 'login',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'channel' => 'portal',
            'created_at' => now(),
        ];
    }
}
