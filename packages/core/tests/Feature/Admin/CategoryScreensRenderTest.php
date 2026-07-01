<?php

namespace Tests\Feature\Admin;

use AdAstra\Models\Category;
use AdAstra\Models\Category\Group;
use AdAstra\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke tests that the category admin screens render (HTTP 200) after the
 * UI-kit conversion. Exercises the _ui macros and the _card / _page-header /
 * _table embeds (incl. section_nav) through the real Twig render path.
 */
class CategoryScreensRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    public function test_groups_index_renders(): void
    {
        Group::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->get(route('categories.groups'))
            ->assertOk()
            ->assertSee('Category Groups')
            ->assertSee('All Items');
    }

    public function test_groups_create_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('categories.groups.create'))
            ->assertOk()
            ->assertSee('Create Category Group');
    }

    public function test_groups_show_renders(): void
    {
        $group = Group::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('categories.groups.show', $group))
            ->assertOk()
            ->assertSee($group->name)
            ->assertSee('Category List');
    }

    public function test_groups_edit_renders(): void
    {
        $group = Group::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('categories.groups.edit', $group))
            ->assertOk();
    }

    public function test_groups_delete_renders(): void
    {
        $group = Group::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('categories.groups.confirm', $group))
            ->assertOk();
    }

    public function test_category_create_renders(): void
    {
        $group = Group::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('categories.create', ['group_id' => $group->id]))
            ->assertOk()
            ->assertSee('Create Category');
    }

    public function test_category_edit_renders(): void
    {
        $group = Group::factory()->create();
        $category = Category::factory()->create(['group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->get(route('categories.edit', $category))
            ->assertOk();
    }

    public function test_category_delete_renders(): void
    {
        $group = Group::factory()->create();
        $category = Category::factory()->create(['group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->get(route('categories.confirm', $category))
            ->assertOk();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }
}
