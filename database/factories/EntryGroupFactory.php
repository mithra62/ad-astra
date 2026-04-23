<?php

namespace Database\Factories;

use App\Models\EntryGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryGroup>
 */
class EntryGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'handle' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{4,8}'),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
