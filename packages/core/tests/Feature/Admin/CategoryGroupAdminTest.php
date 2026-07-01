<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage for the Admin\Category\Group controller — focused on the
 * mutation actions (store/update/destroy) that the screen-render tests do not
 * exercise.
 */
class CategoryGroupAdminTest extends TestCase
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
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_group_and_redirects(): void
    {
        $this->actingAs($this->admin)
            ->post(route('categories.groups.store'), [
                'name' => 'Blog Tags',
                'handle' => 'blog-tags',
            ])
            ->assertSessionHas('success')
            ->assertRedirectContains('/admin/categories/groups/');

        $this->assertDatabaseHas('category_groups', ['handle' => 'blog-tags']);
    }

    public function test_store_requires_name_and_handle(): void
    {
        $this->actingAs($this->admin)
            ->post(route('categories.groups.store'), [])
            ->assertSessionHasErrors(['name', 'handle']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_changes_group_and_redirects(): void
    {
        $group = CategoryGroup::factory()->create(['name' => 'Old']);

        $this->actingAs($this->admin)
            ->put(route('categories.groups.update', $group->id), [
                'name' => 'New Name',
                'handle' => $group->handle,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('category_groups', ['id' => $group->id, 'name' => 'New Name']);
    }

    public function test_update_returns_404_for_missing_group(): void
    {
        $this->actingAs($this->admin)
            ->put(route('categories.groups.update', 999999), ['name' => 'Nope', 'handle' => 'nope'])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_group_and_redirects(): void
    {
        $group = CategoryGroup::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('categories.groups.destroy', $group->id), ['confirm_removal' => 1])
            ->assertRedirect(route('categories.groups'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('category_groups', ['id' => $group->id]);
    }

    public function test_destroy_requires_confirmation(): void
    {
        $group = CategoryGroup::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('categories.groups.destroy', $group->id), [])
            ->assertSessionHasErrors('confirm_removal');

        $this->assertDatabaseHas('category_groups', ['id' => $group->id]);
    }

    public function test_destroy_missing_group_redirects_with_failure(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('categories.groups.destroy', 999999), ['confirm_removal' => 1])
            ->assertRedirect(route('categories.groups'))
            ->assertSessionHas('failure');
    }
}
