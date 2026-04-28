<?php

namespace Tests\Unit\Actions\Category;

use App\Actions\Category\CreateNewCategory;
use App\Actions\Category\EditCategory;
use App\Models\Category;
use App\Models\Category\Group;
use App\Models\FieldLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeGroup(): Group
    {
        $layout = FieldLayout::create(['name' => 'Test Layout']);
        return Group::create([
            'name'            => 'Test Group',
            'handle'          => 'test-group',
            'sort_order'      => 0,
            'field_layout_id' => $layout->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // CreateNewCategory
    // -------------------------------------------------------------------------

    public function test_create_returns_category_instance(): void
    {
        $group  = $this->makeGroup();
        $action = app(CreateNewCategory::class);

        $result = $action->create([
            'group_id' => $group->id,
            'name'     => 'My Category',
            'handle'   => 'my-category',
        ]);

        $this->assertInstanceOf(Category::class, $result);
    }

    public function test_create_persists_category_to_database(): void
    {
        $group  = $this->makeGroup();
        $action = app(CreateNewCategory::class);

        $action->create([
            'group_id' => $group->id,
            'name'     => 'Persisted Category',
            'handle'   => 'persisted-category',
        ]);

        $this->assertDatabaseHas('categories', [
            'group_id' => $group->id,
            'name'     => 'Persisted Category',
            'handle'   => 'persisted-category',
        ]);
    }

    public function test_create_assigns_category_to_correct_group(): void
    {
        $group  = $this->makeGroup();
        $action = app(CreateNewCategory::class);

        $result = $action->create([
            'group_id' => $group->id,
            'name'     => 'Grouped Category',
            'handle'   => 'grouped-category',
        ]);

        $this->assertEquals($group->id, $result->group_id);
    }

    public function test_create_auto_generates_handle_from_name_when_omitted(): void
    {
        $group  = $this->makeGroup();
        $action = app(CreateNewCategory::class);

        $result = $action->create([
            'group_id' => $group->id,
            'name'     => 'Auto Handle Category',
        ]);

        $this->assertEquals('auto-handle-category', $result->handle);
    }

    public function test_create_stores_sort_order(): void
    {
        $group  = $this->makeGroup();
        $action = app(CreateNewCategory::class);

        $result = $action->create([
            'group_id'   => $group->id,
            'name'       => 'Sorted Category',
            'sort_order' => 5,
        ]);

        $this->assertEquals(5, $result->sort_order);
    }

    public function test_create_supports_parent_category(): void
    {
        $group  = $this->makeGroup();
        $action = app(CreateNewCategory::class);

        $parent = $action->create([
            'group_id' => $group->id,
            'name'     => 'Parent',
            'handle'   => 'parent',
        ]);

        $child = $action->create([
            'group_id'  => $group->id,
            'name'      => 'Child',
            'handle'    => 'child',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
    }

    public function test_create_throws_when_group_not_found(): void
    {
        $action = app(CreateNewCategory::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $action->create([
            'group_id' => 99999,
            'name'     => 'Bad Group Category',
        ]);
    }

    // -------------------------------------------------------------------------
    // EditCategory
    // -------------------------------------------------------------------------

    public function test_edit_returns_category_instance(): void
    {
        $group    = $this->makeGroup();
        $category = Category::factory()->for($group)->create(['name' => 'Old Name']);
        $action   = app(EditCategory::class);

        $result = $action->edit($category, ['name' => 'New Name', 'handle' => 'new-name']);

        $this->assertInstanceOf(Category::class, $result);
    }

    public function test_edit_updates_name_and_handle(): void
    {
        $group    = $this->makeGroup();
        $category = Category::factory()->for($group)->create(['name' => 'Old Name', 'handle' => 'old-name']);
        $action   = app(EditCategory::class);

        $action->edit($category, ['name' => 'Updated Name', 'handle' => 'updated-name']);

        $this->assertDatabaseHas('categories', [
            'id'     => $category->id,
            'name'   => 'Updated Name',
            'handle' => 'updated-name',
        ]);
    }

    public function test_edit_updates_sort_order(): void
    {
        $group    = $this->makeGroup();
        $category = Category::factory()->for($group)->create(['sort_order' => 1]);
        $action   = app(EditCategory::class);

        $result = $action->edit($category, [
            'name'       => $category->name,
            'handle'     => $category->handle,
            'sort_order' => 10,
        ]);

        $this->assertEquals(10, $result->sort_order);
    }

    public function test_edit_reflects_updated_values_on_returned_model(): void
    {
        $group    = $this->makeGroup();
        $category = Category::factory()->for($group)->create(['name' => 'Old Name']);
        $action   = app(EditCategory::class);

        $result = $action->edit($category, ['name' => 'New Name', 'handle' => 'new-name']);

        // CategoryRepository::applyData returns $category->refresh() which is $this,
        // so result and category are the same instance — assert the values were updated.
        $this->assertEquals('New Name', $result->name);
        $this->assertEquals('New Name', $category->name);
    }
}
