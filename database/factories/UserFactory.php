<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'             => fake()->name(),
            'email'            => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'         => static::$password ??= Hash::make('password'),
            'remember_token'   => Str::random(10),
            'status'           => UserStatus::ACTIVE,
            'suspended_until'  => null,
            'banned_at'        => null,
            'locked_until'     => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Status states
    // -------------------------------------------------------------------------

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => UserStatus::PENDING,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => UserStatus::INACTIVE,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status'          => UserStatus::SUSPENDED,
            'suspended_until' => now()->addDays(7),
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn(array $attributes) => [
            'status'    => UserStatus::BANNED,
            'banned_at' => now(),
        ]);
    }

    /**
     * Lock the account for a given number of minutes (default 30).
     * Lock is a parallel flag — can be combined with any status state.
     */
    public function locked(int $minutes = 30): static
    {
        return $this->state(fn(array $attributes) => [
            'locked_until' => now()->addMinutes($minutes),
        ]);
    }
}
