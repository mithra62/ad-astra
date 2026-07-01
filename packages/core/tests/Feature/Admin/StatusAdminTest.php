<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Status controller (statuses nested under a
 * status group).
 *
 * Admin auth model: the `auth` middleware redirects guests to login; the
 * Admin\Controller constructor aborts 403 for any authenticated user lacking
 * the "access admin" gate. A super admin passes every gate via Gate::before.
 * Mutation actions additionally run their FormRequest authorize() gates
 * (create/edit/delete status) and validation.
 */
class StatusAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    private function plainUser(): User
    {
        return User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_create_redirects_guests_to_login(): void
    {
        $group = StatusGroup::factory()->create();

        $this->get(route('statuses.create', ['group_id' => $group->id]))
            ->assertRedirect(route('login'));
    }

    public function test_create_forbids_non_admin_user(): void
    {
        $group = StatusGroup::factory()->create();

        $this->actingAs($this->plainUser())
            ->get(route('statuses.create', ['group_id' => $group->id]))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // create (render)
    // -------------------------------------------------------------------------

    public function test_create_renders_for_admin(): void
    {
        $group = StatusGroup::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('statuses.create', ['group_id' => $group->id]))
            ->assertOk();
    }

    public function test_create_returns_404_for_missing_group(): void
    {
        $this->actingAs($this->admin)
            ->get(route('statuses.create', ['group_id' => 999999]))
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_status_and_redirects_to_group(): void
    {
        $group = StatusGroup::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('statuses.store', ['group_id' => $group->id]), [
                'name' => 'Draft',
                'handle' => 'draft',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('statuses.groups.show', $group->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('statuses', [
            'status_group_id' => $group->id,
            'handle' => 'draft',
        ]);
    }

    public function test_store_validation_failure_redirects_back_with_errors(): void
    {
        $group = StatusGroup::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('statuses.store', ['group_id' => $group->id]), [])
            ->assertSessionHasErrors(['name', 'handle', 'sort_order']);

        $this->assertDatabaseCount('statuses', 0);
    }

    // -------------------------------------------------------------------------
    // edit / confirm (render)
    // -------------------------------------------------------------------------

    public function test_edit_renders_for_admin(): void
    {
        $status = Status::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('statuses.edit', $status->id))
            ->assertOk();
    }

    public function test_edit_returns_404_for_missing_status(): void
    {
        $this->actingAs($this->admin)
            ->get(route('statuses.edit', 999999))
            ->assertNotFound();
    }

    public function test_confirm_renders_for_admin(): void
    {
        $status = Status::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('statuses.confirm', $status->id))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_status_and_redirects(): void
    {
        $group = StatusGroup::factory()->create();
        $status = Status::factory()->create([
            'status_group_id' => $group->id,
            'name' => 'Old',
            'handle' => 'old',
        ]);

        $this->actingAs($this->admin)
            ->put(route('statuses.update', $status->id), [
                'name' => 'Published',
                'handle' => 'old',
                'sort_order' => 0,
            ])
            ->assertRedirect(route('statuses.groups.show', $group->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('statuses', ['id' => $status->id, 'name' => 'Published']);
    }

    public function test_update_returns_404_for_missing_status(): void
    {
        $this->actingAs($this->admin)
            ->put(route('statuses.update', 999999), [
                'name' => 'Nope',
                'handle' => 'nope',
                'sort_order' => 0,
            ])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_status_and_redirects(): void
    {
        $group = StatusGroup::factory()->create();
        $status = Status::factory()->create(['status_group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->delete(route('statuses.destroy', $status->id), ['confirm_removal' => 1])
            ->assertRedirect(route('statuses.groups.show', $group->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('statuses', ['id' => $status->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $status = Status::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('statuses.destroy', $status->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('statuses', ['id' => $status->id]);
    }
}
