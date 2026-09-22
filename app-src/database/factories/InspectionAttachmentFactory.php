<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionAttachment>
 */
class InspectionAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->lexify('vistoria-????').'.jpg';

        return [
            'inspection_id' => Inspection::factory(),
            'disk' => 'local',
            'path' => 'vistorias/'.fake()->uuid().'/'.$nome,
            'original_name' => $nome,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 2_000_000),
            'sha256' => hash('sha256', fake()->uuid()),
            'uploaded_by_user_id' => User::factory(),
        ];
    }
}
