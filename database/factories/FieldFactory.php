<?php

namespace Database\Factories;

use App\Models\Field;
use App\Models\Field\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Field>
 */
class FieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'field_type_id' => Type::factory(),
            'name' => fake()->words(2, true),
            'handle' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{4,8}'),
            'label' => fake()->words(2, true),
            'instructions' => fake()->optional()->sentence(),
            'settings' => [],
            'hidden' => false,
        ];
    }
}
