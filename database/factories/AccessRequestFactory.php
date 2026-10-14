<?php

namespace Database\Factories;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<AccessRequest>
 */
class AccessRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->ad(),
            'role_id' => Role::firstOrCreate(['name' => 'Coordenador', 'guard_name' => 'web'])->id,
            'justificativa' => fake()->sentence(8),
            'status' => AccessRequestStatus::Pendente,
        ];
    }

    public function aprovada(): static
    {
        return $this->state(fn () => [
            'status' => AccessRequestStatus::Aprovada,
            'decided_at' => now(),
        ]);
    }

    public function recusada(string $motivo = 'Perfil não compatível com a lotação.'): static
    {
        return $this->state(fn () => [
            'status' => AccessRequestStatus::Recusada,
            'decided_at' => now(),
            'decision_note' => $motivo,
        ]);
    }
}
