<?php

namespace Database\Factories;

use AdAstra\Models\Entry;
use AdAstra\Models\EntryTree;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EntryTree>
 */
class EntryTreeFactory extends Factory
{
    protected $model = EntryTree::class;

    public function definition(): array
    {
        $handle = Str::slug(fake()->unique()->words(2, true));

        return [
            'entry_id' => Entry::factory(),
            'parent_id' => null,
            'handle' => $handle,
            'uri' => $handle,
            'depth' => 0,
            'sort_order' => fake()->numberBetween(1, 100),
            'redirect_url' => null,
            'template' => null,
            'is_home' => false,
        ];
    }

    public function home(): static
    {
        return $this->state([
            'handle' => 'home',
            'uri' => '/',
            'depth' => 0,
            'is_home' => true,
        ]);
    }

    public function childOf(EntryTree $parent): static
    {
        $handle = Str::slug(fake()->unique()->words(2, true));

        return $this->state([
            'parent_id' => $parent->id,
            'uri' => $parent->uri.'/'.$handle,
            'handle' => $handle,
            'depth' => $parent->depth + 1,
        ]);
    }
}
