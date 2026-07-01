<?php

namespace Database\Factories\Field;

use AdAstra\Models\Field\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'handle' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{4,8}'),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
