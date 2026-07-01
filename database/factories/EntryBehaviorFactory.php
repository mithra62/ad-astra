<?php

namespace Database\Factories;

use AdAstra\Models\EntryBehavior;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryBehavior>
 */
class EntryBehaviorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'handle' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{4,8}'),
            'class' => 'behavior.' . fake()->unique()->slug(2),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
