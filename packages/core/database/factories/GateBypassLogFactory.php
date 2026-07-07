<?php

namespace Database\Factories;

use AdAstra\Models\GateBypassLog;
use AdAstra\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GateBypassLog>
 */
class GateBypassLogFactory extends Factory
{
    protected $model = GateBypassLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ability' => $this->faker->randomElement(['view', 'create', 'update', 'delete']),
            'subject_type' => null,
            'subject_id' => null,
            'method' => null,
            'url' => null,
            'route_name' => null,
            'ip' => null,
            'occurrences' => 1,
            'context' => null,
            'created_at' => now(),
        ];
    }
}
