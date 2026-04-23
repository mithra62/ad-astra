<?php

namespace Database\Factories;

use App\Models\StatusGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusGroup>
 */
class StatusGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'handle' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{4,8}'),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
