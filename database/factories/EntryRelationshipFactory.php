<?php

namespace Database\Factories;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryRelationship;
use AdAstra\Models\Field;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryRelationship>
 */
class EntryRelationshipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory(),
            'related_entry_id' => Entry::factory(),
            'field_id' => Field::factory(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
