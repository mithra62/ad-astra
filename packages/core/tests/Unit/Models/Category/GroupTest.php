<?php

namespace Tests\Unit\Models\Category;

use AdAstra\Models\Category;
use AdAstra\Models\Category\Group;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        $model = new Group();

        $this->assertEquals(['field_layout_id', 'name', 'handle', 'description', 'sort_order'], $model->getFillable());
    }

    public function test_uses_category_groups_table(): void
    {
        $this->assertEquals('category_groups', (new Group())->getTable());
    }

    public function test_casts_sort_order_to_integer(): void
    {
        $group = Group::factory()->create(['sort_order' => '7']);

        $this->assertIsInt($group->sort_order);
        $this->assertEquals(7, $group->sort_order);
    }

    public function test_categories_relationship_is_has_many(): void
    {
        $group = Group::factory()->create();

        $this->assertInstanceOf(HasMany::class, $group->categories());
    }

    public function test_categories_returns_all_categories_in_group(): void
    {
        $group = Group::factory()->create();
        $parent = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        $child = Category::factory()->for($group, 'group')->create(['parent_id' => $parent->id]);

        $this->assertCount(2, $group->categories);
    }

    public function test_root_categories_returns_only_top_level(): void
    {
        $group = Group::factory()->create();
        $root = Category::factory()->for($group, 'group')->create(['parent_id' => null]);
        Category::factory()->for($group, 'group')->create(['parent_id' => $root->id]);

        $roots = $group->rootCategories()->get();

        $this->assertCount(1, $roots);
        $this->assertEquals($root->id, $roots->first()->id);
    }

    public function test_scope_ordered_sorts_by_sort_order_then_name(): void
    {
        Group::factory()->create(['name' => 'Zebra', 'sort_order' => 1]);
        Group::factory()->create(['name' => 'Alpha', 'sort_order' => 1]);
        Group::factory()->create(['name' => 'First', 'sort_order' => 0]);

        $groups = Group::query()->ordered()->get();

        $this->assertEquals('First', $groups->first()->name);
        $this->assertEquals('Alpha', $groups->get(1)->name);
        $this->assertEquals('Zebra', $groups->last()->name);
    }
}
