<?php

namespace Database\Factories;

use App\Models\AssistedAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistedAttendance>
 */
class AssistedAttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendant_user_id' => User::factory(),
            'citizen_user_id' => User::factory(),
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'ended_at' => null,
        ];
    }

    /**
     * Atendimento vigente (dentro da janela, não encerrado).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'ended_at' => null,
        ]);
    }

    /**
     * Atendimento com a janela já vencida (exige reabertura — CA-03).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subHour(),
            'expires_at' => now()->subMinutes(5),
            'ended_at' => null,
        ]);
    }

    /**
     * Atendimento encerrado manualmente pelo atendente.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => now(),
        ]);
    }
}
