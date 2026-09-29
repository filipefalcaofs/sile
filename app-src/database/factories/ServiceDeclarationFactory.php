<?php

namespace Database\Factories;

use App\Models\ServiceDeclaration;
use App\Models\ViabilityServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceDeclaration>
 */
class ServiceDeclarationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_type_id' => ViabilityServiceType::factory(),
            'body' => 'Declaro que as informações prestadas são verdadeiras.',
            'version' => 1,
            'required' => true,
            'valid_from' => now()->subDay(),
            'valid_to' => null,
        ];
    }
}
