<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Category\Group;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_has_fillable_attributes(): void
    {
        $category = new Category();
        $fillable = [
            'group_id',
            'parent_id',
            'name',
            'handle',
            'sort_order',
        ];
        $this->assertEquals($fillable, $category->getFillable());
    }

    public function test_category_casts_attributes(): void
    {
        $category = new Category();
        $casts = $category->getCasts();

        $this->assertEquals('integer', $casts['group_id']);
        $this->assertEquals('integer', $casts['parent_id']);
        $this->assertEquals('integer', $casts['sort_order']);
        $this->assertEquals('int', $casts['id']); // Default Eloquent cast
    }

    public function test_category_has_group_relationship(): void
    {
        $category = new Category();
        $this->assertInstanceOf(BelongsTo::class, $category->group());
        $this->assertInstanceOf(Group::class, $category->group()->getRelated());
    }

    public function test_category_has_parent_relationship(): void
    {
        $category = new Category();
        $this->assertInstanceOf(BelongsTo::class, $category->parent());
        $this->assertInstanceOf(Category::class, $category->parent()->getRelated());
    }

    public function test_category_has_children_relationship(): void
    {
        $category = new Category();
        $this->assertInstanceOf(HasMany::class, $category->children());
        $this->assertInstanceOf(Category::class, $category->children()->getRelated());
    }

    public function test_category_has_children_recursive_relationship(): void
    {
        $category = new Category();
        $this->assertInstanceOf(HasMany::class, $category->childrenRecursive());
        $this->assertInstanceOf(Category::class, $category->childrenRecursive()->getRelated());
    }

    public function test_roots_scope_returns_only_root_categories(): void
    {
        $group = Group::factory()->create();
        $root = Category::factory()->create(['group_id' => $group->id, 'parent_id' => null]);
        Category::factory()->create(['group_id' => $group->id, 'parent_id' => $root->id]);

        $roots = Category::roots()->get();

        $this->assertCount(1, $roots);
        $this->assertTrue($roots->contains($root));
    }

    public function test_in_group_scope_filters_by_group_id(): void
    {
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();

        $cat1 = Category::factory()->create(['group_id' => $group1->id]);
        $cat2 = Category::factory()->create(['group_id' => $group2->id]);

        $results = Category::inGroup($group1->id)->get();
        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($cat1));
        $this->assertFalse($results->contains($cat2));
    }

    public function test_in_group_scope_filters_by_group_instance(): void
    {
        $group = Group::factory()->create();
        $cat = Category::factory()->create(['group_id' => $group->id]);

        $results = Category::inGroup($group)->get();
        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($cat));
    }

    public function test_children_are_ordered_by_sort_order_and_name(): void
    {
        $parent = Category::factory()->create();

        $child2 = Category::factory()->create([
            'parent_id' => $parent->id,
            'group_id' => $parent->group_id,
            'sort_order' => 2,
            'name' => 'B'
        ]);
        $child1 = Category::factory()->create([
            'parent_id' => $parent->id,
            'group_id' => $parent->group_id,
            'sort_order' => 1,
            'name' => 'A'
        ]);
        $child3 = Category::factory()->create([
            'parent_id' => $parent->id,
            'group_id' => $parent->group_id,
            'sort_order' => 2,
            'name' => 'A'
        ]);

        $children = $parent->children;

        $this->assertCount(3, $children);
        $this->assertEquals($child1->id, $children[0]->id);
        $this->assertEquals($child3->id, $children[1]->id);
        $this->assertEquals($child2->id, $children[2]->id);
    }

    public function test_children_recursive_loads_nested_children(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id, 'group_id' => $root->group_id]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id, 'group_id' => $root->group_id]);

        $result = Category::with('childrenRecursive')->find($root->id);

        $this->assertTrue($result->relationLoaded('childrenRecursive'));
        $this->assertCount(1, $result->childrenRecursive);
        $this->assertTrue($result->childrenRecursive[0]->relationLoaded('childrenRecursive'));
        $this->assertCount(1, $result->childrenRecursive[0]->childrenRecursive);
        $this->assertEquals($grandchild->id, $result->childrenRecursive[0]->childrenRecursive[0]->id);
    }
}
