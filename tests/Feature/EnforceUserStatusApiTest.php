<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforceUserStatusApiTest extends TestCase
{
    use RefreshDatabase;

    // The simplest authenticated API endpoint available.
    private string $endpoint = '/api/v1/account';

    // -------------------------------------------------------------------------
    // Blocked users — JSON path → 403
    // -------------------------------------------------------------------------

    public function test_inactive_user_receives_403_json(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->getJson($this->endpoint)
            ->assertStatus(403)
            ->assertJson(['message' => __('auth.account_inactive')]);
    }

    public function test_banned_user_receives_403_json(): void
    {
        $user = User::factory()->banned()->create();

        $this->actingAs($user)
            ->getJson($this->endpoint)
            ->assertStatus(403)
            ->assertJson(['message' => __('auth.account_inactive')]);
    }

    public function test_suspended_user_within_window_receives_403_json(): void
    {
        $user = User::factory()->suspended()->create();

        $this->actingAs($user)
            ->getJson($this->endpoint)
            ->assertStatus(403)
            ->assertJson(['message' => __('auth.account_inactive')]);
    }

    public function test_locked_user_receives_403_json(): void
    {
        $user = User::factory()->active()->locked()->create();

        $this->actingAs($user)
            ->getJson($this->endpoint)
            ->assertStatus(403)
            ->assertJson(['message' => __('auth.account_inactive')]);
    }

    // -------------------------------------------------------------------------
    // Permitted users — pass through
    // -------------------------------------------------------------------------

    public function test_active_user_is_not_blocked(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user)
            ->getJson($this->endpoint)
            ->assertStatus(200);
    }

    public function test_suspended_user_past_window_is_not_blocked(): void
    {
        $user = User::factory()->create([
            'status'          => \App\Enums\UserStatus::SUSPENDED,
            'suspended_until' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->getJson($this->endpoint)
            ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Unauthenticated — middleware passes through; Sanctum returns 401
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401_not_403(): void
    {
        // EnforceUserStatusApi only acts when $request->user() is present.
        // An unauthenticated request should pass through it and be rejected
        // by auth:sanctum with 401, not by our middleware with 403.
        $this->getJson($this->endpoint)
            ->assertStatus(401);
    }
}
