<?php

namespace Database\Factories;

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
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
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
}
