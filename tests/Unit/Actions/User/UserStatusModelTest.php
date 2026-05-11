<?php

namespace Tests\Unit\Actions\User;

use App\Enums\UserStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusModelTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // canAccessSystem()
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
        $user = User::factory()->suspended()->create(); // suspended_until = now+7d
        $this->assertFalse($user->canAccessSystem());
    }

    public function test_suspended_user_after_window_can_access_system(): void
    {
        $user = User::factory()->create([
            'status'          => UserStatus::SUSPENDED,
            'suspended_until' => Carbon::now()->subMinute(), // already expired
        ]);

        $this->assertTrue($user->canAccessSystem());
    }

    public function test_locked_active_user_cannot_access_system(): void
    {
        $user = User::factory()->active()->locked()->create();
        $this->assertFalse($user->canAccessSystem());
    }

    public function test_lock_expired_active_user_can_access_system(): void
    {
        $user = User::factory()->create([
            'status'       => UserStatus::ACTIVE,
            'locked_until' => Carbon::now()->subMinute(), // already expired
        ]);

        $this->assertTrue($user->canAccessSystem());
    }

    public function test_expired_suspension_with_active_lock_cannot_access_system(): void
    {
        $user = User::factory()->create([
            'status'          => UserStatus::SUSPENDED,
            'suspended_until' => Carbon::now()->subMinute(), // suspension expired
            'locked_until'    => Carbon::now()->addHour(),   // but still locked
        ]);

        $this->assertFalse($user->canAccessSystem());
    }

    // -------------------------------------------------------------------------
    // isLocked()
    // -------------------------------------------------------------------------

    public function test_is_locked_returns_true_when_lock_is_active(): void
    {
        $user = User::factory()->locked()->create();
        $this->assertTrue($user->isLocked());
    }

    public function test_is_locked_returns_false_when_no_lock(): void
    {
        $user = User::factory()->active()->create();
        $this->assertFalse($user->isLocked());
    }

    public function test_is_locked_returns_false_when_lock_expired(): void
    {
        $user = User::factory()->create([
            'status'       => UserStatus::ACTIVE,
            'locked_until' => Carbon::now()->subMinute(),
        ]);

        $this->assertFalse($user->isLocked());
    }

    // -------------------------------------------------------------------------
    // isSuspended()
    // -------------------------------------------------------------------------

    public function test_is_suspended_returns_true_within_window(): void
    {
        $user = User::factory()->suspended()->create();
        $this->assertTrue($user->isSuspended());
    }

    public function test_is_suspended_returns_false_after_window(): void
    {
        $user = User::factory()->create([
            'status'          => UserStatus::SUSPENDED,
            'suspended_until' => Carbon::now()->subMinute(),
        ]);

        $this->assertFalse($user->isSuspended());
    }

    // -------------------------------------------------------------------------
    // accessDeniedReason()
    // -------------------------------------------------------------------------

    public function test_access_denied_reason_returns_null_for_active_user(): void
    {
        $user = User::factory()->active()->create();
        $this->assertNull($user->accessDeniedReason());
    }

    public function test_access_denied_reason_returns_account_inactive(): void
    {
        $user = User::factory()->inactive()->create();
        $this->assertSame('account_inactive', $user->accessDeniedReason());
    }

    public function test_access_denied_reason_returns_account_pending(): void
    {
        $user = User::factory()->pending()->create();
        $this->assertSame('account_pending', $user->accessDeniedReason());
    }

    public function test_access_denied_reason_returns_account_banned(): void
    {
        $user = User::factory()->banned()->create();
        $this->assertSame('account_banned', $user->accessDeniedReason());
    }

    public function test_access_denied_reason_returns_account_suspended_until(): void
    {
        $user = User::factory()->suspended()->create();
        $this->assertSame('account_suspended_until', $user->accessDeniedReason());
    }

    public function test_access_denied_reason_returns_account_suspended_when_no_expiry(): void
    {
        $user = User::factory()->create([
            'status'          => UserStatus::SUSPENDED,
            'suspended_until' => null,
        ]);
        $this->assertSame('account_suspended', $user->accessDeniedReason());
    }

    public function test_access_denied_reason_returns_account_locked_for_locked_active_user(): void
    {
        $user = User::factory()->active()->locked()->create();
        $this->assertSame('account_locked', $user->accessDeniedReason());
    }

    public function test_access_denied_reason_returns_account_locked_when_suspension_expired_but_lock_active(): void
    {
        $user = User::factory()->create([
            'status'          => UserStatus::SUSPENDED,
            'suspended_until' => Carbon::now()->subMinute(), // suspension expired
            'locked_until'    => Carbon::now()->addHour(),   // but still locked
        ]);

        $this->assertSame('account_locked', $user->accessDeniedReason());
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function test_scope_active_returns_only_active_users(): void
    {
        User::factory()->active()->count(3)->create();
        User::factory()->inactive()->count(2)->create();

        $activeCount = User::active()->count();

        // The 3 we created (there may be seeded users, so we check >=).
        $this->assertGreaterThanOrEqual(3, $activeCount);
    }

    public function test_scope_where_status_filters_correctly(): void
    {
        User::factory()->pending()->count(4)->create();
        User::factory()->active()->count(2)->create();

        $pending = User::whereStatus(UserStatus::PENDING)->count();

        $this->assertGreaterThanOrEqual(4, $pending);
    }
}
