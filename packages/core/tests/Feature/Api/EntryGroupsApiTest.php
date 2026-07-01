<?php

namespace Tests\Feature\Api;

use AdAstra\Models\EntryGroup;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\Role;
use AdAstra\Models\StatusGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Feature coverage for the EntryGroups API controller (top-level resource).
 *
 * Gates: read/write abort 404 without "read entry groups"; destroy aborts 403
 * without "delete entry group"; store/update authorize() require
 * "create entry group" / "edit entry group".
 */
class EntryGroupsApiTest extends TestCase
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
        foreach (['read entry groups', 'create entry group', 'edit entry group', 'delete entry group'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        return User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_index_rejects_guests_with_401(): void
    {
        $this->getJson('/api/v1/entry-groups')->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_groups_for_super_admin(): void
    {
        $group = EntryGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/entry-groups')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('data.0.id', $group->id);
    }

    public function test_index_denies_user_without_read_permission_with_404(): void
    {
        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson('/api/v1/entry-groups')->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_the_group_for_super_admin(): void
    {
        $group = EntryGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/entry-groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $group->id);
    }

    public function test_show_returns_404_for_missing_group(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/entry-groups/999999')->assertNotFound();
    }

    public function test_show_denies_user_without_read_permission_with_404(): void
    {
        $group = EntryGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson("/api/v1/entry-groups/{$group->id}")->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_a_group_for_super_admin(): void
    {
        $statusGroup = StatusGroup::factory()->create();
        $layout = FieldLayout::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/entry-groups', [
            'name' => 'Blog Posts',
            'handle' => 'blog-posts',
            'status_group_id' => $statusGroup->id,
            'field_layout_id' => $layout->id,
        ])->assertCreated()->assertJsonPath('data.name', 'Blog Posts');

        $this->assertDatabaseHas('entry_groups', ['handle' => 'blog-posts']);
    }

    public function test_store_requires_name_handle_status_group_and_field_layout(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        // A field layout is mandatory on creation.
        $this->postJson('/api/v1/entry-groups', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'handle', 'status_group_id', 'field_layout_id']);
    }

    public function test_store_rejects_nonexistent_status_group(): void
    {
        $layout = FieldLayout::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/entry-groups', [
            'name' => 'Bad',
            'handle' => 'bad',
            'status_group_id' => 999999,
            'field_layout_id' => $layout->id,
        ])->assertStatus(422)->assertJsonValidationErrors('status_group_id');
    }

    public function test_store_denies_user_without_create_permission_with_403(): void
    {
        $statusGroup = StatusGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->postJson('/api/v1/entry-groups', [
            'name' => 'Blog',
            'handle' => 'blog',
            'status_group_id' => $statusGroup->id,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_the_group_for_super_admin(): void
    {
        $group = EntryGroup::factory()->create(['name' => 'Old']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson("/api/v1/entry-groups/{$group->id}", [
            'name' => 'New Name',
            'handle' => $group->handle,
            'status_group_id' => $group->status_group_id,
        ])->assertOk()->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('entry_groups', ['id' => $group->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_group(): void
    {
        $statusGroup = StatusGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson('/api/v1/entry-groups/999999', [
            'name' => 'Nope',
            'handle' => 'nope',
            'status_group_id' => $statusGroup->id,
        ])->assertNotFound();
    }

    public function test_update_denies_user_without_edit_permission_with_403(): void
    {
        $group = EntryGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->putJson("/api/v1/entry-groups/{$group->id}", [
            'name' => 'Nope',
            'handle' => $group->handle,
            'status_group_id' => $group->status_group_id,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_the_group_for_super_admin(): void
    {
        $group = EntryGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson("/api/v1/entry-groups/{$group->id}")->assertNoContent();

        $this->assertDatabaseMissing('entry_groups', ['id' => $group->id]);
    }

    public function test_destroy_returns_404_for_missing_group(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson('/api/v1/entry-groups/999999')->assertNotFound();
    }

    public function test_destroy_denies_user_without_delete_permission_with_403(): void
    {
        $group = EntryGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->deleteJson("/api/v1/entry-groups/{$group->id}")->assertForbidden();

        $this->assertDatabaseHas('entry_groups', ['id' => $group->id]);
    }
}
