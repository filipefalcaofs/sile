<?php

namespace Database\Factories;

use App\Enums\CompanyLinkRole;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyUser>
 */
class CompanyUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ];
    }

    public function responsavel(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CompanyLinkRole::Responsavel,
        ]);
    }

    public function procurador(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CompanyLinkRole::Procurador,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => now(),
            'ended_reason' => 'Encerrado em teste',
        ]);
    }
}
