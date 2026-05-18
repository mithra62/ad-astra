<?php

namespace Database\Factories;

use App\Models\Status;
use App\Models\StatusGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Status>
 */
class StatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status_group_id' => StatusGroup::factory(),
            'name' => fake()->word(),
            'handle' => fake()->unique()->regexify('[a-z]{4,8}'),
            'color' => fake()->hexColor(),
            'is_default' => false,
            'is_public' => false,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function default(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function public(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_public' => true,
        ]);
    }
}
