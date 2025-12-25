<?php

namespace Database\Factories;

use App\Models\BbValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BbValue>
 */
class BbValueFactory extends Factory
{
    protected $model = BbValue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'field_name' => $this->faker->word(),
            'field_value' => $this->faker->sentence(),
            'ip_address' => $this->faker->ipv4(),
        ];
    }
}
