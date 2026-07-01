<?php

namespace Tests\Unit\Actions\User;

use AdAstra\Enums\UserStatus;
use AdAstra\Events\UserLockChanged;
use AdAstra\Events\UserStatusChanged;
use AdAstra\Models\User;
use AdAstra\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }

    // -------------------------------------------------------------------------
    // setStatus()
    // -------------------------------------------------------------------------

    public function test_set_status_updates_user_status(): void
    {
        $user = User::factory()->active()->create();

        $this->service->setStatus($user, UserStatus::INACTIVE);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => UserStatus::INACTIVE,
        ]);
    }

    public function test_set_status_fires_user_status_changed_event(): void
    {
        Event::fake([UserStatusChanged::class]);

        $user = User::factory()->active()->create();

        $this->service->setStatus($user, UserStatus::INACTIVE, 'No longer needed');

        Event::assertDispatched(UserStatusChanged::class, function ($event) use ($user) {
            return $event->user->id === $user->id
                && $event->previousStatus === UserStatus::ACTIVE
                && $event->newStatus === UserStatus::INACTIVE
                && $event->reason === 'No longer needed';
        });
    }

    public function test_set_status_to_banned_sets_banned_at(): void
    {
        $user = User::factory()->active()->create();

        $this->service->setStatus($user, UserStatus::BANNED);

        $this->assertNotNull($user->fresh()->banned_at);
    }

    public function test_set_status_away_from_banned_clears_banned_at(): void
    {
        $user = User::factory()->banned()->create();

        $this->service->setStatus($user, UserStatus::INACTIVE);

        $this->assertNull($user->fresh()->banned_at);
    }

    public function test_set_status_away_from_suspended_clears_suspended_until(): void
    {
        $user = User::factory()->suspended()->create();

        $this->service->setStatus($user, UserStatus::ACTIVE);

        $this->assertNull($user->fresh()->suspended_until);
    }

    // -------------------------------------------------------------------------
    // suspend()
    // -------------------------------------------------------------------------

    public function test_suspend_sets_status_and_suspended_until(): void
    {
        Event::fake([UserStatusChanged::class]);

        $user = User::factory()->active()->create();
        $until = now()->addDays(7)->toDateTime();

        $this->service->suspend($user, $until, 'Temporary violation');

        $fresh = $user->fresh();
        $this->assertSame(UserStatus::SUSPENDED, $fresh->status);
        $this->assertNotNull($fresh->suspended_until);
    }

    public function test_suspend_fires_user_status_changed_event(): void
    {
        Event::fake([UserStatusChanged::class]);

        $user = User::factory()->active()->create();
        $until = now()->addDays(3)->toDateTime();

        $this->service->suspend($user, $until, 'spam');

        Event::assertDispatched(UserStatusChanged::class, function ($event) use ($user) {
            return $event->user->id === $user->id
                && $event->newStatus === UserStatus::SUSPENDED;
        });
    }

    // -------------------------------------------------------------------------
    // lockUser() / unlockUser()
    // -------------------------------------------------------------------------

    public function test_lock_user_sets_locked_until(): void
    {
        Event::fake([UserLockChanged::class]);

        $user = User::factory()->active()->create();
        $until = now()->addMinutes(30)->toDateTime();

        $this->service->lockUser($user, $until);

        $this->assertNotNull($user->fresh()->locked_until);
    }

    public function test_lock_user_fires_user_lock_changed_event(): void
    {
        Event::fake([UserLockChanged::class]);

        $user = User::factory()->active()->create();
        $until = now()->addMinutes(30)->toDateTime();

        $this->service->lockUser($user, $until);

        Event::assertDispatched(UserLockChanged::class, function ($event) use ($user) {
            return $event->user->id === $user->id
                && $event->newLockedUntil !== null;
        });
    }

    public function test_unlock_user_clears_locked_until(): void
    {
        Event::fake([UserLockChanged::class]);

        $user = User::factory()->locked()->create();

        $this->service->unlockUser($user);

        $this->assertNull($user->fresh()->locked_until);
    }

    public function test_unlock_user_fires_user_lock_changed_event(): void
    {
        Event::fake([UserLockChanged::class]);

        $user = User::factory()->locked()->create();

        $this->service->unlockUser($user);

        Event::assertDispatched(UserLockChanged::class, function ($event) use ($user) {
            return $event->user->id === $user->id
                && $event->newLockedUntil === null;
        });
    }
}
