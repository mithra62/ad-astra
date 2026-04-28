<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\EntryMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryMetric>
 */
class EntryMetricFactory extends Factory
{
    protected $model = EntryMetric::class;

    public function definition(): array
    {
        return [
            'entry_id'      => Entry::factory(),
            'metric'        => fake()->randomElement(['views', 'downloads', 'plays']),
            'value'         => fake()->numberBetween(1, 1000),
            'recorded_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ];
    }
}
