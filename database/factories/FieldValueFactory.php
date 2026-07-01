<?php

namespace Database\Factories;

use AdAstra\Models\Field;
use AdAstra\Models\FieldValue;
use AdAstra\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldValue>
 */
class FieldValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'field_id' => Field::factory(),
            'fieldable_id' => User::factory(),
            'fieldable_type' => User::class,
            'value_text' => fake()->sentence(),
            'value_integer' => null,
            'value_float' => null,
            'value_date' => null,
            'value_boolean' => null,
            'value_json' => null,
        ];
    }
}
