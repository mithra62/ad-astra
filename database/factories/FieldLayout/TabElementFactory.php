<?php

namespace Database\Factories\FieldLayout;

use App\Models\Field;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TabElement>
 */
class TabElementFactory extends Factory
{
    protected $model = TabElement::class;

    public function definition(): array
    {
        return [
            'field_layout_tab_id' => Tab::factory(),
            'field_id' => Field::factory(),
            'required' => false,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
