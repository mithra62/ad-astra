<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Status\Group controller (status groups).
 *
 * Note the missing-record behavior differs from a hard 404: destroy and confirm
 * redirect back with a "failure" flash when the group does not exist.
 */
class StatusGroupAdminTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_index_redirects_guests_to_login(): void
    {
        $this->get(route('statuses.groups'))->assertRedirect(route('login'));
    }

    public function test_index_forbids_non_admin_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('statuses.groups'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    public function test_index_renders(): void
    {
        StatusGroup::factory()->count(2)->create();

        $this->actingAs($this->admin)->get(route('statuses.groups'))->assertOk();
    }

    public function test_create_renders(): void
    {
        $this->actingAs($this->admin)->get(route('statuses.groups.create'))->assertOk();
    }

    public function test_show_renders(): void
    {
        $group = StatusGroup::factory()->create();

        $this->actingAs($this->admin)->get(route('statuses.groups.show', $group->id))->assertOk();
    }

    public function test_show_returns_404_for_missing_group(): void
    {
        $this->actingAs($this->admin)->get(route('statuses.groups.show', 999999))->assertNotFound();
    }

    public function test_edit_renders(): void
    {
        $group = StatusGroup::factory()->create();

        $this->actingAs($this->admin)->get(route('statuses.groups.edit', $group->id))->assertOk();
    }

    public function test_confirm_renders(): void
    {
        $group = StatusGroup::factory()->create();

        $this->actingAs($this->admin)->get(route('statuses.groups.confirm', $group->id))->assertOk();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_group_and_redirects(): void
    {
        $this->actingAs($this->admin)
            ->post(route('statuses.groups.store'), [
                'name' => 'Publishing Workflow',
                'handle' => 'publishing-workflow',
                'sort_order' => 1,
            ])
            ->assertSessionHas('success')
            ->assertRedirectContains('/admin/statuses/groups/');

        $this->assertDatabaseHas('status_groups', ['handle' => 'publishing-workflow']);
    }

    public function test_store_validation_failure(): void
    {
        $this->actingAs($this->admin)
            ->post(route('statuses.groups.store'), [])
            ->assertSessionHasErrors(['name', 'handle', 'sort_order']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_group_and_redirects(): void
    {
        $group = StatusGroup::factory()->create(['name' => 'Old']);

        $this->actingAs($this->admin)
            ->put(route('statuses.groups.update', $group->id), [
                'name' => 'New Name',
                'handle' => $group->handle,
                'sort_order' => 2,
            ])
            ->assertRedirect(route('statuses.groups'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('status_groups', ['id' => $group->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_group(): void
    {
        $this->actingAs($this->admin)
            ->put(route('statuses.groups.update', 999999), [
                'name' => 'Nope',
                'handle' => 'nope',
                'sort_order' => 0,
            ])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_group_and_redirects(): void
    {
        $group = StatusGroup::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('statuses.groups.destroy', $group->id), ['confirm_removal' => 1])
            ->assertRedirect(route('statuses.groups'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('status_groups', ['id' => $group->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $group = StatusGroup::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('statuses.groups.destroy', $group->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('status_groups', ['id' => $group->id]);
    }

    public function test_destroy_missing_group_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('statuses.groups.destroy', 999999), ['confirm_removal' => 1])
            ->assertRedirect(route('statuses.groups'))
            ->assertSessionHas('failure');
    }
}
