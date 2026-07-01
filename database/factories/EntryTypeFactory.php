<?php

namespace Database\Factories;

use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryType>
 */
class EntryTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entry_group_id' => EntryGroup::factory(),
            'name' => fake()->words(2, true),
            'handle' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{4,8}'),
            'entry_behavior_id' => EntryBehavior::where('handle', 'blog-post')->value('id'),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
