<?php

namespace Database\Factories;

use AdAstra\Models\SettingDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SettingDomain>
 */
class SettingDomainFactory extends Factory
{
    protected $model = SettingDomain::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'handle' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'icon' => fake()->optional()->randomElement(['cog', 'mail', 'user', 'lock']),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
