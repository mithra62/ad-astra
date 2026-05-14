<?php

namespace Database\Factories;

use App\Models\SettingValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SettingValue>
 */
class SettingValueFactory extends Factory
{
    protected $model = SettingValue::class;

    public function definition(): array
    {
        return [
            'domain' => fake()->randomElement(['general', 'email', 'media', 'users']),
            'field_handle' => Str::snake(fake()->word()),
            'user_id' => null,
            'value_text' => fake()->sentence(),
            'value_integer' => null,
            'value_float' => null,
            'value_boolean' => null,
            'value_json' => null,
        ];
    }

    public function forUser(int $userId): static
    {
        return $this->state(['user_id' => $userId]);
    }

    public function integer(int $value): static
    {
        return $this->state(['value_text' => null, 'value_integer' => $value]);
    }

    public function boolean(bool $value): static
    {
        return $this->state(['value_text' => null, 'value_boolean' => $value]);
    }
}
