<?php

namespace Database\Factories\FieldLayout;

use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tab>
 */
class TabFactory extends Factory
{
    protected $model = Tab::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'field_layout_id' => FieldLayout::factory(),
            'name' => $name,
            'handle' => Str::slug($name),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
