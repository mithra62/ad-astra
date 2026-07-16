<?php

namespace Database\Factories;

use AdAstra\Models\EntryGroup;
use AdAstra\Models\StatusGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryGroup>
 */
class EntryGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status_group_id' => StatusGroup::factory(),
            'name' => fake()->words(2, true),
            'handle' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{4,8}'),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
