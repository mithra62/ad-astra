<?php

namespace Tests\Feature\Api;

use AdAstra\Models\Category;
use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\Role;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Feature coverage for the Categories API controller (nested under
 * category-groups). Ownership is scoped: a category in a different group is 404.
 *
 * Gates: read/write abort 404 without "read categories"; destroy aborts 403
 * without "delete category"; store/update authorize() require
 * "create category" / "edit category".
 */
class CategoriesApiTest extends TestCase
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
        foreach (['read categories', 'create category', 'edit category', 'delete category'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        return User::factory()->create();
    }

    private function categoryIn(CategoryGroup $group, array $attributes = []): Category
    {
        return Category::factory()->create(array_merge(['group_id' => $group->id], $attributes));
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_index_rejects_guests_with_401(): void
    {
        $group = CategoryGroup::factory()->create();

        $this->getJson("/api/v1/category-groups/{$group->id}/categories")
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_root_categories_in_the_group(): void
    {
        $group = CategoryGroup::factory()->create();
        $root = $this->categoryIn($group);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/category-groups/{$group->id}/categories")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('data.0.id', $root->id);
    }

    public function test_index_excludes_child_categories_unless_all_is_passed(): void
    {
        $group = CategoryGroup::factory()->create();
        $root = $this->categoryIn($group);
        $child = $this->categoryIn($group, ['parent_id' => $root->id]);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $rootsOnly = $this->getJson("/api/v1/category-groups/{$group->id}/categories")->assertOk();
        $ids = array_column($rootsOnly->json('data'), 'id');
        $this->assertContains($root->id, $ids);
        $this->assertNotContains($child->id, $ids);

        $all = $this->getJson("/api/v1/category-groups/{$group->id}/categories?all=1")->assertOk();
        $allIds = array_column($all->json('data'), 'id');
        $this->assertContains($child->id, $allIds);
    }

    public function test_index_returns_404_for_missing_group(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/category-groups/999999/categories')->assertNotFound();
    }

    public function test_index_denies_user_without_read_permission_with_404(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson("/api/v1/category-groups/{$group->id}/categories")->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_the_category_for_super_admin(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = $this->categoryIn($group);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/category-groups/{$group->id}/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_show_returns_404_when_category_belongs_to_a_different_group(): void
    {
        $group = CategoryGroup::factory()->create();
        $other = CategoryGroup::factory()->create();
        $category = $this->categoryIn($other);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson("/api/v1/category-groups/{$group->id}/categories/{$category->id}")
            ->assertNotFound();
    }

    public function test_show_denies_user_without_read_permission_with_404(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = $this->categoryIn($group);

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->getJson("/api/v1/category-groups/{$group->id}/categories/{$category->id}")
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_a_category_for_super_admin(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson("/api/v1/category-groups/{$group->id}/categories", [
            'name' => 'PHP',
            'handle' => 'php',
        ])->assertCreated()->assertJsonPath('data.name', 'PHP');

        $this->assertDatabaseHas('categories', ['group_id' => $group->id, 'handle' => 'php']);
    }

    public function test_store_returns_404_for_missing_group(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson('/api/v1/category-groups/999999/categories', [
            'name' => 'PHP',
            'handle' => 'php',
        ])->assertNotFound();
    }

    public function test_store_requires_name_and_handle(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->postJson("/api/v1/category-groups/{$group->id}/categories", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'handle']);
    }

    public function test_store_denies_user_without_create_permission_with_403(): void
    {
        $group = CategoryGroup::factory()->create();

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->postJson("/api/v1/category-groups/{$group->id}/categories", [
            'name' => 'PHP',
            'handle' => 'php',
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_the_category_for_super_admin(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = $this->categoryIn($group, ['name' => 'Old']);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson("/api/v1/category-groups/{$group->id}/categories/{$category->id}", [
            'name' => 'New Name',
            'handle' => $category->handle,
        ])->assertOk()->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_when_category_belongs_to_a_different_group(): void
    {
        $group = CategoryGroup::factory()->create();
        $other = CategoryGroup::factory()->create();
        $category = $this->categoryIn($other);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->putJson("/api/v1/category-groups/{$group->id}/categories/{$category->id}", [
            'name' => 'Hijack',
            'handle' => $category->handle,
        ])->assertNotFound();
    }

    public function test_update_denies_user_without_edit_permission_with_403(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = $this->categoryIn($group);

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->putJson("/api/v1/category-groups/{$group->id}/categories/{$category->id}", [
            'name' => 'Nope',
            'handle' => $category->handle,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_the_category_for_super_admin(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = $this->categoryIn($group);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson("/api/v1/category-groups/{$group->id}/categories/{$category->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_destroy_returns_404_when_category_belongs_to_a_different_group(): void
    {
        $group = CategoryGroup::factory()->create();
        $other = CategoryGroup::factory()->create();
        $category = $this->categoryIn($other);

        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->deleteJson("/api/v1/category-groups/{$group->id}/categories/{$category->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_destroy_denies_user_without_delete_permission_with_403(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = $this->categoryIn($group);

        Sanctum::actingAs($this->plainUser(), ['*']);

        $this->deleteJson("/api/v1/category-groups/{$group->id}/categories/{$category->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
