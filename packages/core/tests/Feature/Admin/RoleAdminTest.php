<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Role as RoleModel;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Role controller.
 *
 * Role destroy is gated by Role::canDelete() — roles with id 1-3 are locked, so
 * fixtures create enough roles that the target's id clears the locked set.
 */
class RoleAdminTest extends TestCase
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

    private function permission(string $name = 'edit posts'): Permission
    {
        return Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    /** A role whose id is guaranteed to be outside the locked [1,2,3] set. */
    private function deletableRole(): RoleModel
    {
        return RoleModel::factory()->count(4)->create()->last();
    }

    // -------------------------------------------------------------------------
    // Auth boundaries
    // -------------------------------------------------------------------------

    public function test_index_redirects_guests_to_login(): void
    {
        $this->get(route('roles.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbids_non_admin_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Render actions
    // -------------------------------------------------------------------------

    public function test_index_renders(): void
    {
        $this->actingAs($this->admin)->get(route('roles.index'))->assertOk();
    }

    public function test_create_renders(): void
    {
        $this->permission();

        $this->actingAs($this->admin)->get(route('roles.create'))->assertOk();
    }

    public function test_edit_renders(): void
    {
        $this->permission();
        $role = RoleModel::factory()->create();

        $this->actingAs($this->admin)->get(route('roles.edit', $role->id))->assertOk();
    }

    public function test_edit_returns_404_for_missing_role(): void
    {
        $this->actingAs($this->admin)->get(route('roles.edit', 999999))->assertNotFound();
    }

    public function test_confirm_renders(): void
    {
        $role = RoleModel::factory()->create();

        $this->actingAs($this->admin)->get(route('roles.confirm', $role->id))->assertOk();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_role_and_redirects(): void
    {
        $this->permission('edit posts');

        $this->actingAs($this->admin)
            ->post(route('roles.store'), [
                'name' => 'Content Editor',
                'permissions' => ['edit posts'],
            ])
            ->assertSessionHas('success')
            ->assertRedirectContains('/admin/roles/');

        $this->assertDatabaseHas('roles', ['name' => 'Content Editor']);
    }

    public function test_store_requires_name_and_permissions(): void
    {
        $this->actingAs($this->admin)
            ->post(route('roles.store'), [])
            ->assertSessionHasErrors(['name', 'permissions']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_role_and_redirects(): void
    {
        $this->permission('edit posts');
        $role = RoleModel::factory()->create(['name' => 'Old Role']);

        $this->actingAs($this->admin)
            ->put(route('roles.update', $role->id), [
                'name' => 'Renamed Role',
                'permissions' => ['edit posts'],
            ])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Renamed Role']);
    }

    public function test_update_returns_404_for_missing_role(): void
    {
        $this->permission('edit posts');

        $this->actingAs($this->admin)
            ->put(route('roles.update', 999999), [
                'name' => 'Nope',
                'permissions' => ['edit posts'],
            ])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_unlocked_role_and_redirects(): void
    {
        $role = $this->deletableRole();

        $this->actingAs($this->admin)
            ->delete(route('roles.destroy', $role->id), ['confirm_removal' => 1])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $role = $this->deletableRole();

        $this->actingAs($this->admin)
            ->delete(route('roles.destroy', $role->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
