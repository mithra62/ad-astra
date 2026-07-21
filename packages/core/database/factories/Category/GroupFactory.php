<?php

namespace Database\Factories\Category;

use AdAstra\Models\Category\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true) . Str::random(8);

        return [
            'name' => $name,
            'handle' => Str::slug($name),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
