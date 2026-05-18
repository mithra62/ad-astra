<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entry_group_id' => EntryGroup::factory(),
            'entry_type_id' => EntryType::factory(),
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(4, false),
            'handle' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{4,8}-[a-z]{4,8}'),
            'status_handle' => 'draft',
            'status_is_public' => false,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn(array $attributes) => [
            'status_handle' => 'published',
            'status_is_public' => true,
            'published_at' => now()->subHour(),
        ]);
    }

    public function scheduledForFuture(): static
    {
        return $this->state(fn(array $attributes) => [
            'published_at' => now()->addDay(),
        ]);
    }
}
