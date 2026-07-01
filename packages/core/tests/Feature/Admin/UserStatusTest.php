<?php

namespace Tests\Feature\Admin;

use AdAstra\Enums\UserStatus;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    public function test_admin_can_set_user_status_to_inactive(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->patch(route('users.status.update', $target->id), [
                'status' => UserStatus::INACTIVE,
                'reason' => 'Account closed by request.',
            ])
            ->assertRedirect(route('users.show', $target->id));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => UserStatus::INACTIVE,
        ]);
    }

    // -------------------------------------------------------------------------
    // PATCH /admin/users/{id}/status
    // -------------------------------------------------------------------------

    public function test_admin_can_suspend_user_with_date(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->patch(route('users.status.update', $target->id), [
                'status' => UserStatus::SUSPENDED,
                'reason' => 'Violation of terms.',
                'suspended_until' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('users.show', $target->id));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => UserStatus::SUSPENDED,
        ]);
    }

    public function test_status_change_writes_audit_log(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->patch(route('users.status.update', $target->id), [
                'status' => UserStatus::BANNED,
                'reason' => 'Severe violation.',
            ]);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by_user_id' => $this->admin->id,
            'new_status' => UserStatus::BANNED,
        ]);
    }

    public function test_suspend_requires_suspended_until_date(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->patch(route('users.status.update', $target->id), [
                'status' => UserStatus::SUSPENDED,
                'reason' => 'Some reason.',
                // suspended_until intentionally omitted
            ])
            ->assertSessionHasErrors('suspended_until');
    }

    public function test_status_change_requires_reason_for_non_active(): void
    {
        $target = User::factory()->active()->create();

        $this->actingAs($this->admin)
            ->patch(route('users.status.update', $target->id), [
                'status' => UserStatus::INACTIVE,
                // reason intentionally omitted
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_user_without_permission_cannot_change_status(): void
    {
        $unprivileged = User::factory()->active()->create();
        $target = User::factory()->active()->create();

        $this->actingAs($unprivileged)
            ->patch(route('users.status.update', $target->id), [
                'status' => UserStatus::INACTIVE,
                'reason' => 'Some reason.',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_unlock_a_locked_user(): void
    {
        $target = User::factory()->active()->locked()->create();

        $this->actingAs($this->admin)
            ->delete(route('users.lock.destroy', $target->id))
            ->assertRedirect(route('users.show', $target->id));

        $this->assertNull($target->fresh()->locked_until);
    }

    // -------------------------------------------------------------------------
    // DELETE /admin/users/{id}/lock
    // -------------------------------------------------------------------------

    public function test_unlock_writes_audit_log(): void
    {
        $target = User::factory()->active()->locked()->create();

        $this->actingAs($this->admin)
            ->delete(route('users.lock.destroy', $target->id));

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'new_locked_until' => null,
        ]);
    }

    public function test_user_without_permission_cannot_unlock(): void
    {
        $unprivileged = User::factory()->active()->create();
        $target = User::factory()->active()->locked()->create();

        $this->actingAs($unprivileged)
            ->delete(route('users.lock.destroy', $target->id))
            ->assertForbidden();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'manage user status']);
        Permission::firstOrCreate(['name' => 'access admin']);

        $this->admin = User::factory()->active()->create();
        $this->admin->givePermissionTo(['access admin', 'manage user status']);
    }
}
