<?php

namespace Tests\Unit\Actions\Category\Group;

use AdAstra\Actions\Category\Group\CreateNewCategoryGroup;
use AdAstra\Actions\Category\Group\EditCategoryGroup;
use AdAstra\Models\Category\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryGroupActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CreateNewCategoryGroup
    // -------------------------------------------------------------------------

    public function test_create_returns_group_instance(): void
    {
        $action = app(CreateNewCategoryGroup::class);

        $result = $action->create(['name' => 'My Group', 'handle' => 'my-group', 'sort_order' => 0]);

        $this->assertInstanceOf(Group::class, $result);
    }

    public function test_create_persists_group_to_database(): void
    {
        $action = app(CreateNewCategoryGroup::class);

        $action->create(['name' => 'Blog Tags', 'handle' => 'blog-tags', 'sort_order' => 0]);

        $this->assertDatabaseHas('category_groups', [
            'name' => 'Blog Tags',
            'handle' => 'blog-tags',
        ]);
    }

    public function test_create_stores_sort_order(): void
    {
        $action = app(CreateNewCategoryGroup::class);

        $group = $action->create(['name' => 'Sorted', 'handle' => 'sorted', 'sort_order' => 7]);

        $this->assertEquals(7, $group->sort_order);
    }

    // -------------------------------------------------------------------------
    // EditCategoryGroup
    // -------------------------------------------------------------------------

    public function test_edit_returns_true_on_success(): void
    {
        $group = Group::factory()->create(['name' => 'Old Name']);
        $action = app(EditCategoryGroup::class);

        $result = $action->edit($group, ['name' => 'New Name', 'handle' => 'new-name']);

        $this->assertTrue($result);
    }

    public function test_edit_updates_group_name_and_handle(): void
    {
        $group = Group::factory()->create(['name' => 'Old Name', 'handle' => 'old-name']);
        $action = app(EditCategoryGroup::class);

        $action->edit($group, ['name' => 'Updated Name', 'handle' => 'updated-name']);

        $this->assertDatabaseHas('category_groups', [
            'id' => $group->id,
            'name' => 'Updated Name',
            'handle' => 'updated-name',
        ]);
    }

}
