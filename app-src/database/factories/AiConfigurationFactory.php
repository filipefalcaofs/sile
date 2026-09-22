<?php

namespace Database\Factories;

use App\Models\AiConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiConfiguration>
 */
class AiConfigurationFactory extends Factory
{
    protected $model = AiConfiguration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'provider' => 'openai',
            'capability' => 'text',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-'.$this->faker->regexify('[A-Za-z0-9]{24}'),
            'model' => 'gpt-5.4-mini',
            'temperature' => 0.1,
            'max_tokens' => 4096,
            'timeout_ms' => 60000,
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
