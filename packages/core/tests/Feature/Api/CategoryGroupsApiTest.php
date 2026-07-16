<?php

namespace Tests\Feature\Api;

use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\Role;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Feature coverage for the CategoryGroups API controller (top-level resource).
 *
 * Gates: read/write abort 404 without "read category groups"; destroy aborts
 * 403 without "delete category group"; store/update authorize() require
 * "create category group" / "edit category group".
 */
class CategoryGroupsApiTest extends TestCase
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
        foreach (['read category groups', 'create category group', 'edit category group', 'delete category group'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        return User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_index_rejects_guests_with_401(): void
    {
        $this->getJson('/api/v1/category-groups')->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_groups_for_super_admin(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/category-groups')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('data.0.id', $group->id);
    }

    public function test_index_denies_user_without_read_permission_with_404(): void
    {
        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson('/api/v1/category-groups')->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_the_group_for_super_admin(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/category-groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $group->id);
    }

    public function test_show_returns_404_for_missing_group(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/category-groups/999999')->assertNotFound();
    }

    public function test_show_denies_user_without_read_permission_with_404(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson("/api/v1/category-groups/{$group->id}")->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_a_group_for_super_admin(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/category-groups', [
            'name' => 'Blog Tags',
            'handle' => 'blog-tags',
        ])->assertCreated()->assertJsonPath('data.name', 'Blog Tags');

        $this->assertDatabaseHas('category_groups', ['handle' => 'blog-tags']);
    }

    public function test_store_requires_name_and_handle(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/category-groups', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'handle']);
    }

    public function test_store_rejects_duplicate_handle(): void
    {
        CategoryGroup::factory()->create(['handle' => 'taken']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/category-groups', [
            'name' => 'Another',
            'handle' => 'taken',
        ])->assertStatus(422)->assertJsonValidationErrors('handle');
    }

    public function test_store_denies_user_without_create_permission_with_403(): void
    {
        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->postJson('/api/v1/category-groups', [
            'name' => 'Tags',
            'handle' => 'tags',
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_the_group_for_super_admin(): void
    {
        $group = CategoryGroup::factory()->create(['name' => 'Old']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson("/api/v1/category-groups/{$group->id}", [
            'name' => 'New Name',
            'handle' => $group->handle,
        ])->assertOk()->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('category_groups', ['id' => $group->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_group(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson('/api/v1/category-groups/999999', [
            'name' => 'Nope',
            'handle' => 'nope',
        ])->assertNotFound();
    }

    public function test_update_denies_user_without_edit_permission_with_403(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->putJson("/api/v1/category-groups/{$group->id}", [
            'name' => 'Nope',
            'handle' => $group->handle,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_the_group_for_super_admin(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson("/api/v1/category-groups/{$group->id}")->assertNoContent();

        $this->assertDatabaseMissing('category_groups', ['id' => $group->id]);
    }

    public function test_destroy_returns_404_for_missing_group(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson('/api/v1/category-groups/999999')->assertNotFound();
    }

    public function test_destroy_denies_user_without_delete_permission_with_403(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->deleteJson("/api/v1/category-groups/{$group->id}")->assertForbidden();

        $this->assertDatabaseHas('category_groups', ['id' => $group->id]);
    }
}
