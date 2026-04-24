<?php

namespace Database\Factories;

use App\Models\FieldLayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldLayout>
 */
class FieldLayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
        ];
    }
}
