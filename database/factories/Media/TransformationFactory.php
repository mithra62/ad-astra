<?php

namespace Database\Factories\Media;

use AdAstra\Models\Media;
use AdAstra\Models\Media\Transformation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transformation>
 */
class TransformationFactory extends Factory
{
    protected $model = Transformation::class;

    public function definition(): array
    {
        $fileName = Str::uuid().'.jpg';

        return [
            'media_id' => Media::factory(),
            'key' => fake()->randomElement(['thumb', 'medium', 'large', 'webp']),
            'disk' => 'local',
            'path' => 'transformations/'.$fileName,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1024, 512 * 1024),
            'width' => fake()->numberBetween(100, 2000),
            'height' => fake()->numberBetween(100, 2000),
            'params' => [],
            'driver' => 'gd',
            'status' => 'complete',
            'error' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'path' => null,
            'size' => null,
            'width' => null,
            'height' => null,
            'status' => 'pending',
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error' => 'Transformation driver error.',
        ]);
    }
}
