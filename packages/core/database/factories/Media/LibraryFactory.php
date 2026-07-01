<?php

namespace Database\Factories\Media;

use AdAstra\Models\Media\Library;
use AdAstra\Models\StatusGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Library>
 */
class LibraryFactory extends Factory
{
    protected $model = Library::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' Library',
            'handle' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{4,8}'),
            'adapter' => 'local',
            'adapter_settings' => null,
            'allowed_types' => null,
            'max_size' => 10,
            'sort_order' => 0,
        ];
    }

    public function withImages(): static
    {
        return $this->state(fn() => [
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        ]);
    }

    public function withStatusGroup(): static
    {
        return $this->state(fn() => [
            'status_group_id' => StatusGroup::factory(),
        ]);
    }
}
