<?php

namespace Database\Factories;

use AdAstra\Models\FieldLayout;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FieldLayout>
 */
class FieldLayoutFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => $name,
            'handle' => Str::slug($name),
        ];
    }
}
