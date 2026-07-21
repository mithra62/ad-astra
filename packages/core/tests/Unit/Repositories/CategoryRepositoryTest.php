<?php

namespace Tests\Unit\Repositories;

use AdAstra\Models\Category;
use AdAstra\Models\Category\Group;
use AdAstra\Repositories\CategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CategoryRepository $repo;

    public function test_create_persists_category_in_database(): void
    {
        $group = Group::factory()->create();

        $category = $this->repo->create($group, ['name' => 'Fruits']);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertTrue($category->exists);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Fruits']);
    }

    public function test_create_sets_group_id(): void
    {
        $group = Group::factory()->create();

        $category = $this->repo->create($group, ['name' => 'Vegetables']);

        $this->assertEquals($group->id, $category->group_id);
    }

    public function test_create_auto_generates_handle_from_name_when_omitted(): void
    {
        $group = Group::factory()->create();

        $category = $this->repo->create($group, ['name' => 'Fresh Produce']);

        $this->assertEquals('fresh-produce', $category->handle);
    }

    public function test_create_uses_explicit_handle_when_provided(): void
    {
        $group = Group::factory()->create();

        $category = $this->repo->create($group, ['name' => 'Dairy', 'handle' => 'dairy-products']);

        $this->assertEquals('dairy-products', $category->handle);
    }

    public function test_create_sets_sort_order(): void
    {
        $group = Group::factory()->create();

        $category = $this->repo->create($group, ['name' => 'Meats', 'sort_order' => 5]);

        $this->assertEquals(5, $category->sort_order);
    }

    public function test_create_sets_parent_id(): void
    {
        $group = Group::factory()->create();
        $parent = Category::factory()->for($group, 'group')->create(['parent_id' => null]);

        $child = $this->repo->create($group, ['name' => 'Beef', 'parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->parent_id);
    }

    public function test_apply_data_updates_name(): void
    {
        $group = Group::factory()->create();
        $category = Category::factory()->for($group, 'group')->create(['name' => 'Old Name']);

        $updated = $this->repo->applyData($category, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_apply_data_does_not_change_name_when_not_provided(): void
    {
        $group = Group::factory()->create();
        $category = Category::factory()->for($group, 'group')->create(['name' => 'Stable Name']);

        $updated = $this->repo->applyData($category, ['sort_order' => 3]);

        $this->assertEquals('Stable Name', $updated->name);
    }

    public function test_apply_data_updates_sort_order(): void
    {
        $group = Group::factory()->create();
        $category = Category::factory()->for($group, 'group')->create(['sort_order' => 0]);

        $updated = $this->repo->applyData($category, ['sort_order' => 7]);

        $this->assertEquals(7, $updated->sort_order);
    }

    public function test_apply_data_updates_parent_id(): void
    {
        $group = Group::factory()->create();
        $parent = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        $child = Category::factory()->for($group, 'group')->create(['parent_id' => null]);

        $updated = $this->repo->applyData($child, ['parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $updated->parent_id);
    }

    public function test_apply_data_clears_parent_id_when_passed_null(): void
    {
        $group = Group::factory()->create();
        $parent = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        $child = Category::factory()->for($group, 'group')->create(['parent_id' => $parent->id]);

        $updated = $this->repo->applyData($child, ['parent_id' => null]);

        $this->assertNull($updated->parent_id);
    }

    public function test_apply_data_ignores_parent_id_when_key_absent(): void
    {
        $group = Group::factory()->create();
        $parent = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        $child = Category::factory()->for($group, 'group')->create(['parent_id' => $parent->id]);

        $this->repo->applyData($child, ['name' => 'Still Has Parent']);

        $this->assertEquals($parent->id, $child->fresh()->parent_id);
    }

    public function test_delete_removes_category_from_database(): void
    {
        $category = Category::factory()->create();

        $result = $this->repo->delete($category);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_apply_data_throws_when_parent_would_create_direct_cycle(): void
    {
        $group = Group::factory()->create();
        $category = Category::factory()->for($group, 'group')->create(['parent_id' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/circular reference/i');

        $this->repo->applyData($category, ['parent_id' => $category->id]);
    }

    public function test_apply_data_throws_when_parent_would_create_indirect_cycle(): void
    {
        $group = Group::factory()->create();
        $grandparent = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        $parent = Category::factory()->for($group, 'group')->create(['parent_id' => $grandparent->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/circular reference/i');

        // Making grandparent a child of parent would create a cycle
        $this->repo->applyData($grandparent, ['parent_id' => $parent->id]);
    }

    public function test_resolve_layout_fields_returns_empty_collection_when_no_field_layout(): void
    {
        $group = Group::factory()->create(['field_layout_id' => null]);
        $category = Category::factory()->for($group, 'group')->create();

        $fields = $this->repo->resolveLayoutFields($category);

        $this->assertCount(0, $fields);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new CategoryRepository();
    }
}
