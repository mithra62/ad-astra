<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\EntryGroup;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Entry\Group controller — focused on the
 * mutation actions (store/update/destroy) and create/confirm renders that the
 * existing view tests do not exercise.
 *
 * The store path also guards the EntryGroupService::create field_layout_id
 * regression (creating without a field layout must not error).
 */
class EntryGroupAdminTest extends TestCase
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

    public function test_create_redirects_guests_to_login(): void
    {
        $this->get(route('entries.groups.create'))->assertRedirect(route('login'));
    }

    public function test_create_forbids_non_admin_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('entries.groups.create'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    public function test_create_renders(): void
    {
        $this->actingAs($this->admin)->get(route('entries.groups.create'))->assertOk();
    }

    public function test_confirm_renders(): void
    {
        $group = EntryGroup::factory()->create();

        $this->actingAs($this->admin)->get(route('entries.groups.confirm', $group->id))->assertOk();
    }

    public function test_confirm_missing_group_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->get(route('entries.groups.confirm', 999999))
            ->assertRedirect(route('entries.groups'))
            ->assertSessionHas('failure');
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_group_without_field_layout_and_redirects(): void
    {
        $statusGroup = StatusGroup::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('entries.groups.store'), [
                'name' => 'Blog Posts',
                'handle' => 'blog-posts',
                'status_group_id' => $statusGroup->id,
            ])
            ->assertSessionHas('success')
            ->assertRedirectContains('/admin/entries/groups/');

        $this->assertDatabaseHas('entry_groups', ['handle' => 'blog-posts']);
    }

    public function test_store_requires_name_handle_and_status_group(): void
    {
        $this->actingAs($this->admin)
            ->post(route('entries.groups.store'), [])
            ->assertSessionHasErrors(['name', 'handle', 'status_group_id']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_group_and_redirects_to_edit(): void
    {
        $group = EntryGroup::factory()->create(['name' => 'Old']);

        $this->actingAs($this->admin)
            ->put(route('entries.groups.update', $group->id), [
                'name' => 'New Name',
                'handle' => $group->handle,
                'status_group_id' => $group->status_group_id,
            ])
            ->assertRedirect(route('entries.groups.edit', $group->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('entry_groups', ['id' => $group->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_group(): void
    {
        $statusGroup = StatusGroup::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('entries.groups.update', 999999), [
                'name' => 'Nope',
                'handle' => 'nope',
                'status_group_id' => $statusGroup->id,
            ])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_group_and_redirects(): void
    {
        $group = EntryGroup::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('entries.groups.destroy', $group->id), ['confirm_removal' => 1])
            ->assertRedirect(route('entries.groups'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('entry_groups', ['id' => $group->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $group = EntryGroup::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('entries.groups.destroy', $group->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('entry_groups', ['id' => $group->id]);
    }

    public function test_destroy_missing_group_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('entries.groups.destroy', 999999), ['confirm_removal' => 1])
            ->assertRedirect(route('entries.groups'))
            ->assertSessionHas('failure');
    }
}
