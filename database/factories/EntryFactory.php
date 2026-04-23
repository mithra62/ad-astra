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
            'status' => 'draft',
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now()->subHour(),
        ]);
    }

    public function scheduledForFuture(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now()->addDay(),
        ]);
    }
}
