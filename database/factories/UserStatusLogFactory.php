<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserStatusLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserStatusLog>
 */
class UserStatusLogFactory extends Factory
{
    protected $model = UserStatusLog::class;

    public function definition(): array
    {
        $statuses = UserStatus::ALL;

        return [
            'user_id' => User::factory(),
            'changed_by_user_id' => User::factory(),
            'previous_status' => fake()->randomElement($statuses),
            'new_status' => fake()->randomElement($statuses),
            'previous_locked_until' => null,
            'new_locked_until' => null,
            'reason' => fake()->optional()->sentence(),
            'context' => null,
            'created_at' => now(),
        ];
    }
}
