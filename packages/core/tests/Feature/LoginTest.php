<?php

namespace Tests\Feature;

use AdAstra\Enums\UserStatus;
use AdAstra\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests for the user-status layer of the authentication system.
 *
 * We test two orthogonal concerns:
 *
 *  1. canAccessSystem() — the model method that decides whether a user may
 *     enter the system.  These are pure unit-style assertions on a factory-
 *     created model; no HTTP request is needed.
 *
 *  2. EnforceUserStatus middleware — already-authenticated users whose status
 *     is later changed to a blocking value should be kicked out on their next
 *     request.  Tested by acting as the user and hitting a protected route.
 *
 * We intentionally do NOT drive the full Fortify login form here. Fortify's
 * authenticateUsing callback is covered by the service-provider unit tests;
 * the form-POST flow involves Fortify internals (rate limiting, 2FA redirect,
 * CSRF timing) that belong in a dedicated integration suite once the full
 * test database is in place.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // canAccessSystem() — model-level logic
    // -------------------------------------------------------------------------

    public function test_active_user_can_access_system(): void
    {
        $user = User::factory()->active()->create();
        $this->assertTrue($user->canAccessSystem());
    }

    public function test_inactive_user_cannot_access_system(): void
    {
        $user = User::factory()->inactive()->create();
        $this->assertFalse($user->canAccessSystem());
    }

    public function test_pending_user_cannot_access_system(): void
    {
        $user = User::factory()->pending()->create();
        $this->assertFalse($user->canAccessSystem());
    }

    public function test_banned_user_cannot_access_system(): void
    {
        $user = User::factory()->banned()->create();
        $this->assertFalse($user->canAccessSystem());
    }

    public function test_suspended_user_within_window_cannot_access_system(): void
    {
        $user = User::factory()->suspended()->create(); // suspended_until = now()+7d
        $this->assertFalse($user->canAccessSystem());
    }

    public function test_suspended_user_past_window_can_access_system(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::SUSPENDED,
            'suspended_until' => Carbon::now()->subMinute(),
        ]);
        $this->assertTrue($user->canAccessSystem());
    }

    public function test_locked_active_user_cannot_access_system(): void
    {
        $user = User::factory()->active()->locked()->create();
        $this->assertFalse($user->canAccessSystem());
    }

    public function test_lock_expired_user_can_access_system(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
            'locked_until' => Carbon::now()->subMinute(),
        ]);
        $this->assertTrue($user->canAccessSystem());
    }

    // -------------------------------------------------------------------------
    // EnforceUserStatus middleware — session invalidation on status change
    // -------------------------------------------------------------------------

    /**
     * A user who is already logged in and whose status is later changed to
     * inactive should be booted from the system on their next web request.
     */
    public function test_middleware_logs_out_user_whose_status_changes_to_inactive(): void
    {
        $user = User::factory()->active()->create();

        // Change status BEFORE the next request — simulates an admin action.
        $user->forceFill(['status' => UserStatus::INACTIVE])->save();

        // Acting as the user with the new, blocking status should be redirected
        // to the login page by the EnforceUserStatus middleware.
        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('login'));

        // The user should no longer be authenticated.
        $this->assertGuest();
    }

    public function test_middleware_logs_out_banned_user(): void
    {
        $user = User::factory()->active()->create();

        $user->forceFill([
            'status' => UserStatus::BANNED,
            'banned_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_middleware_allows_active_user_through(): void
    {
        // Super-admin bypasses all permission checks; create the role first
        // since it is not seeded in the test database.
        Role::firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);

        $user = User::factory()->active()->create();
        $user->assignRole('super admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_middleware_logs_out_user_whose_lock_is_still_active(): void
    {
        $user = User::factory()->active()->locked()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_middleware_allows_user_whose_lock_has_expired(): void
    {
        Role::firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
            'locked_until' => Carbon::now()->subMinute(),
        ]);
        $user->assignRole('super admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
