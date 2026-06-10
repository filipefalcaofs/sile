<?php

namespace Database\Factories;

use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'cpf' => $this->generateCpf(),
            'phone' => fake()->numerify('(71) 9####-####'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Generate a valid CPF (digits only) with correct check digits.
     */
    private function generateCpf(): string
    {
        $n = [];

        for ($i = 0; $i < 9; $i++) {
            $n[] = random_int(0, 9);
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;

            for ($c = 0; $c < $t; $c++) {
                $d += $n[$c] * (($t + 1) - $c);
            }

            $n[$t] = ((10 * $d) % 11) % 10;
        }

        return implode('', $n);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign the cidadao role (role must be seeded first).
     */
    public function cidadao(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('cidadao'));
    }

    /**
     * Assign the analista role (role must be seeded first).
     */
    public function analista(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('analista'));
    }

    /**
     * Assign the gestor role (role must be seeded first).
     */
    public function gestor(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('gestor'));
    }

    /**
     * Assign the administrador role (role must be seeded first).
     */
    public function administrador(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('administrador'));
    }

    /**
     * Accept the current LGPD term (creates a published v1 if none exists).
     */
    public function withAcceptedLgpdTerm(): static
    {
        return $this->afterCreating(function (User $user) {
            $term = LegalTerm::current('lgpd')
                ?? LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 1]);

            LegalTermAcceptance::create([
                'user_id' => $user->id,
                'legal_term_id' => $term->id,
                'ip_address' => '127.0.0.1',
                'accepted_at' => now(),
            ]);
        });
    }
}
