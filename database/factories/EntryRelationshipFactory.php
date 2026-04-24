<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\EntryRelationship;
use App\Models\Field;
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
