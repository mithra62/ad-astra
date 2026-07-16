<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Category;
use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Category controller — focused on the mutation
 * and redirect actions (store/update/destroy, index/show redirects) that the
 * screen-render tests do not exercise.
 */
class CategoryAdminTest extends TestCase
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
    // Redirect-only actions
    // -------------------------------------------------------------------------

    public function test_index_redirects_to_groups(): void
    {
        $this->actingAs($this->admin)
            ->get(route('categories.index'))
            ->assertRedirect(route('categories.groups'));
    }

    public function test_show_redirects_to_edit(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = Category::factory()->create(['group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->get(route('categories.show', $category->id))
            ->assertRedirect(route('categories.edit', $category->id));
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_root_category_and_redirects_to_group(): void
    {
        $group = CategoryGroup::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('categories.store', ['group_id' => $group->id]), [
                'name' => 'PHP',
                'handle' => 'php',
            ])
            ->assertRedirect(route('categories.groups.show', $group->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['group_id' => $group->id, 'handle' => 'php']);
    }

    public function test_store_child_category_redirects_to_parent_edit(): void
    {
        $group = CategoryGroup::factory()->create();
        $parent = Category::factory()->create(['group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->post(route('categories.store', ['group_id' => $group->id]), [
                'name' => 'Child',
                'handle' => 'child',
                'parent_id' => $parent->id,
            ])
            ->assertRedirect(route('categories.edit', $parent->id))
            ->assertSessionHas('success');
    }

    public function test_store_requires_name_and_handle(): void
    {
        $group = CategoryGroup::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('categories.store', ['group_id' => $group->id]), [])
            ->assertSessionHasErrors(['name', 'handle']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_category_and_redirects(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = Category::factory()->create(['group_id' => $group->id, 'name' => 'Old']);

        $this->actingAs($this->admin)
            ->put(route('categories.update', $category->id), [
                'name' => 'New Name',
                'handle' => $category->handle,
            ])
            ->assertRedirect(route('categories.edit', $category->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_category(): void
    {
        $this->actingAs($this->admin)
            ->put(route('categories.update', 999999), ['name' => 'Nope', 'handle' => 'nope'])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_category_and_redirects_to_group(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = Category::factory()->create(['group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->delete(route('categories.destroy', $category->id), ['confirm_removal' => 1])
            ->assertRedirect(route('categories.groups.show', $group->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $group = CategoryGroup::factory()->create();
        $category = Category::factory()->create(['group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->delete(route('categories.destroy', $category->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_destroy_returns_404_for_missing_category(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('categories.destroy', 999999), ['confirm_removal' => 1])
            ->assertNotFound();
    }
}
