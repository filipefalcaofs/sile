<?php

namespace Database\Factories;

use App\Models\EmailServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailServer>
 */
class EmailServerFactory extends Factory
{
    protected $model = EmailServer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)).' (SMTP)',
            'driver' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'timeout' => 30,
            'username' => $this->faker->unique()->safeEmail(),
            'password' => $this->faker->regexify('[A-Za-z0-9]{16}'),
            'from_address' => $this->faker->safeEmail(),
            'from_name' => 'SEDUR Salvador',
            'active' => true,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
