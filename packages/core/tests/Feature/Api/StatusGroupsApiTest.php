<?php

namespace Tests\Feature\Api;

use AdAstra\Models\Role;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Feature coverage for the StatusGroups API controller (top-level resource).
 *
 * Gate quirks encoded faithfully: read/write require "read status groups";
 * store/update authorize() require "create status" / "edit status" (shared with
 * the Statuses controller); destroy aborts 403 without "delete status".
 */
class StatusGroupsApiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function plainUser(): User
    {
        foreach (['read status groups', 'create status', 'edit status', 'delete status'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        return User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_index_rejects_guests_with_401(): void
    {
        $this->getJson('/api/v1/status-groups')->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // index / show
    // -------------------------------------------------------------------------

    public function test_index_lists_groups_for_super_admin(): void
    {
        $group = StatusGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/status-groups')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('data.0.id', $group->id);
    }

    public function test_index_denies_user_without_read_permission_with_404(): void
    {
        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson('/api/v1/status-groups')->assertNotFound();
    }

    public function test_show_returns_the_group_for_super_admin(): void
    {
        $group = StatusGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/status-groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $group->id);
    }

    public function test_show_returns_404_for_missing_group(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/status-groups/999999')->assertNotFound();
    }

    public function test_show_denies_user_without_read_permission_with_404(): void
    {
        $group = StatusGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson("/api/v1/status-groups/{$group->id}")->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_a_group_for_super_admin(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/status-groups', [
            'name' => 'Publishing Workflow',
            'handle' => 'publishing-workflow',
            'sort_order' => 1,
        ])->assertCreated()->assertJsonPath('data.name', 'Publishing Workflow');

        $this->assertDatabaseHas('status_groups', ['handle' => 'publishing-workflow']);
    }

    public function test_store_requires_name_handle_and_sort_order(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/status-groups', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'handle', 'sort_order']);
    }

    public function test_store_denies_user_without_create_permission_with_403(): void
    {
        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->postJson('/api/v1/status-groups', [
            'name' => 'Flow',
            'handle' => 'flow',
            'sort_order' => 0,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_the_group_for_super_admin(): void
    {
        $group = StatusGroup::factory()->create(['name' => 'Old']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson("/api/v1/status-groups/{$group->id}", [
            'name' => 'New Name',
            'handle' => $group->handle,
            'sort_order' => 2,
        ])->assertOk()->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('status_groups', ['id' => $group->id, 'name' => 'New Name']);
    }

    public function test_update_denies_user_without_edit_permission_with_403(): void
    {
        $group = StatusGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->putJson("/api/v1/status-groups/{$group->id}", [
            'name' => 'Nope',
            'handle' => $group->handle,
            'sort_order' => 0,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_the_group_for_super_admin(): void
    {
        $group = StatusGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson("/api/v1/status-groups/{$group->id}")->assertNoContent();

        $this->assertDatabaseMissing('status_groups', ['id' => $group->id]);
    }

    public function test_destroy_returns_404_for_missing_group(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson('/api/v1/status-groups/999999')->assertNotFound();
    }

    public function test_destroy_denies_user_without_delete_permission_with_403(): void
    {
        $group = StatusGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->deleteJson("/api/v1/status-groups/{$group->id}")->assertForbidden();

        $this->assertDatabaseHas('status_groups', ['id' => $group->id]);
    }
}
