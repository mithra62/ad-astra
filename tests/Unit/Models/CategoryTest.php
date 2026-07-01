<?php

namespace Tests\Unit\Models;

use AdAstra\Models\Category;
use AdAstra\Models\Category\Group;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new Category;

        $this->assertEquals(['group_id', 'parent_id', 'name', 'handle', 'sort_order'], $model->getFillable());
    }

    public function test_uses_categories_table(): void
    {
        $this->assertEquals('categories', (new Category)->getTable());
    }

    public function test_casts_group_id_to_integer(): void
    {
        $group = Group::factory()->create();
        $cat = Category::factory()->for($group, 'group')->create();

        $this->assertIsInt($cat->group_id);
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $cat = Category::factory()->create(['sort_order' => '5']);

        $this->assertIsInt($cat->sort_order);
        $this->assertEquals(5, $cat->sort_order);
    }

    public function test_group_relationship_is_belongs_to(): void
    {
        $group = Group::factory()->create();
        $cat = Category::factory()->for($group, 'group')->create();

        $this->assertInstanceOf(BelongsTo::class, $cat->group());
        $this->assertEquals($group->id, $cat->group->id);
    }

    public function test_parent_relationship_is_belongs_to_self(): void
    {
        $group = Group::factory()->create();
        $parent = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        $child = Category::factory()->for($group, 'group')->create(['parent_id' => $parent->id]);

        $this->assertInstanceOf(BelongsTo::class, $child->parent());
        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_parent_is_null_for_root_categories(): void
    {
        $root = Category::factory()->create(['parent_id' => null]);

        $this->assertNull($root->parent);
    }

    public function test_children_relationship_is_has_many(): void
    {
        $cat = Category::factory()->create();

        $this->assertInstanceOf(HasMany::class, $cat->children());
    }

    public function test_children_are_ordered_by_sort_order_then_name(): void
    {
        $group = Group::factory()->create();
        $parent = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        Category::factory()->for($group, 'group')->create(['parent_id' => $parent->id, 'name' => 'Zebra', 'sort_order' => 1]);
        Category::factory()->for($group, 'group')->create(['parent_id' => $parent->id, 'name' => 'Alpha', 'sort_order' => 1]);
        Category::factory()->for($group, 'group')->create(['parent_id' => $parent->id, 'name' => 'Middle', 'sort_order' => 0]);

        $children = $parent->children()->get();

        $this->assertEquals('Middle', $children->first()->name);
        $this->assertEquals('Alpha', $children->get(1)->name);
        $this->assertEquals('Zebra', $children->last()->name);
    }

    public function test_has_field_values_morph_many_from_fieldable_trait(): void
    {
        $cat = Category::factory()->create();

        $this->assertInstanceOf(MorphMany::class, $cat->fieldValues());
    }

    public function test_scope_roots_returns_categories_with_null_parent_id(): void
    {
        $group = Group::factory()->create();
        $root = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        $child = Category::factory()->for($group, 'group')->create(['parent_id' => $root->id]);

        $results = Category::query()->roots()->get();

        $this->assertTrue($results->contains($root));
        $this->assertFalse($results->contains($child));
    }

    public function test_scope_in_group_filters_by_group_model(): void
    {
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();
        $cat1 = Category::factory()->for($group1, 'group')->create();
        $cat2 = Category::factory()->for($group2, 'group')->create();

        $results = Category::query()->inGroup($group1)->get();

        $this->assertTrue($results->contains($cat1));
        $this->assertFalse($results->contains($cat2));
    }

    public function test_scope_in_group_filters_by_group_id_integer(): void
    {
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();
        $cat1 = Category::factory()->for($group1, 'group')->create();
        $cat2 = Category::factory()->for($group2, 'group')->create();

        $results = Category::query()->inGroup($group1->id)->get();

        $this->assertTrue($results->contains($cat1));
        $this->assertFalse($results->contains($cat2));
    }

    public function test_children_recursive_returns_has_many(): void
    {
        $cat = Category::factory()->create();

        $this->assertInstanceOf(HasMany::class, $cat->childrenRecursive());
    }

    public function test_children_recursive_returns_no_results_at_max_depth_zero(): void
    {
        $group = Group::factory()->create();
        $parent = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        Category::factory()->for($group, 'group')->create(['parent_id' => $parent->id]);

        $this->assertCount(0, $parent->childrenRecursive(0)->get());
    }
}
