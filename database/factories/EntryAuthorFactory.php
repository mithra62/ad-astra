<?php

namespace Database\Factories;

use AdAstra\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\AdAstra\Models\EntryAuthor>
 */
class EntryAuthorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'display_name' => null,
            'status'       => 'active',
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function disabled(): static
    {
        return $this->state(['status' => 'disabled']);
    }
}
