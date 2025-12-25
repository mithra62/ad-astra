<?php

namespace Database\Factories;

use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiLog>
 */
class ApiLogFactory extends Factory
{
    protected $model = ApiLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_route' => $this->faker->url(),
            'method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE', 'PATCH']),
            'request_payload' => json_encode(['foo' => 'bar']),
            'request_headers' => json_encode(['Content-Type' => 'application/json']),
            'response_payload' => json_encode(['success' => true]),
            'response_headers' => json_encode(['Content-Type' => 'application/json']),
            'response_status_code' => 200,
            'user_id' => User::factory(),
        ];
    }
}
