<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\User controller — mutation actions and the
 * password sub-actions. Missing records redirect back with a "failure" flash.
 * destroy blocks self-deletion (DeleteUserRequest::authorize()).
 */
class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);

        Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    }

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_index_redirects_guests_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbids_non_admin_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('users.index'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    public function test_index_renders(): void
    {
        $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
    }

    public function test_show_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->get(route('users.show', $user->id))->assertOk();
    }

    public function test_show_missing_user_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.show', 999999))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('failure');
    }

    public function test_confirm_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->get(route('users.confirm', $user->id))->assertOk();
    }

    public function test_change_password_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->get(route('users.change_password', $user->id))->assertOk();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_user_and_redirects(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
                'roles' => ['editor'],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_store_validation_failure(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'password', 'roles']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_user_and_redirects(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user->id), [
                'name' => 'New Name',
                'email' => $user->email,
                'roles' => ['editor'],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_missing_user_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->put(route('users.update', 999999), [
                'name' => 'Nope',
                'email' => 'nope@example.com',
                'roles' => ['editor'],
            ])
            ->assertSessionHas('failure');
    }

    // -------------------------------------------------------------------------
    // password
    // -------------------------------------------------------------------------

    /**
     * KNOWN BUG (not fixed here — needs a design decision): the admin
     * "change another user's password" form submits only password +
     * password_confirmation, but the controller delegates to Fortify's
     * self-service UpdateUserPassword action, which requires current_password
     * via MatchCurrentPassword. So an admin password reset always fails with a
     * current_password validation error. This test documents the current
     * behavior and will start failing (a good prompt to update it) once the
     * controller stops requiring re-authentication for admin resets.
     */
    public function test_password_reset_currently_blocked_by_current_password_requirement(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('users.password', $user->id), [
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_password_validation_failure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('users.password', $user->id), [
                'password' => 'short',
                'password_confirmation' => 'mismatch',
            ])
            ->assertSessionHasErrors('password');
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_user_and_redirects(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $user->id), ['confirm_removal' => 1])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_destroy_blocks_self_deletion(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $this->admin->id), ['confirm_removal' => 1])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $user->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
