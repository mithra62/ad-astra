<?php

namespace Database\Factories\User;

use AdAstra\Models\User;
use AdAstra\Models\User\OauthToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OauthToken>
 */
class OauthTokenFactory extends Factory
{
    protected $model = OauthToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => $this->faker->word(),
            'provider_account' => $this->faker->email(),
            'provider_user_id' => $this->faker->uuid(),
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'token_type' => 'Bearer',
            'expires_at' => now()->addHours(1),
            'scopes' => ['read', 'write'],
            'meta' => ['foo' => 'bar'],
        ];
    }

    /**
     * Indicate that the token is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the token is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
