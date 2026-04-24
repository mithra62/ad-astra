<?php

namespace Database\Factories;

use App\Models\EntryGroup;
use App\Models\EntryType;
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
            'class' => 'App\\EntryTypes\\BlogPostEntryType',
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
