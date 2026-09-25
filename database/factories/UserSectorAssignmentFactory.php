<?php

namespace Database\Factories;

use App\Models\Sector;
use App\Models\User;
use App\Models\UserSectorAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSectorAssignment>
 */
class UserSectorAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sector_id' => Sector::factory(),
            'previous_sector_id' => null,
            'source' => 'discovery',
            'started_at' => now(),
            'ended_at' => null,
            'changed_by_user_id' => null,
        ];
    }
}
