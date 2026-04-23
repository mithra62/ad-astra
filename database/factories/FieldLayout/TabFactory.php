<?php

namespace Database\Factories\FieldLayout;

use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tab>
 */
class TabFactory extends Factory
{
    protected $model = Tab::class;

    public function definition(): array
    {
        return [
            'field_layout_id' => FieldLayout::factory(),
            'name' => fake()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
