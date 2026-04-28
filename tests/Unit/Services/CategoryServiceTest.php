<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Category\Group as CategoryGroup;
use App\Models\FieldLayout;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryService $service;

    public function test_create_returns_category_instance(): void
    {
        $group = $this->makeGroup();
        $result = $this->service->create($group, [
            'name' => 'Vegetables',
            'handle' => 'vegetables',
        ]);

        $this->assertInstanceOf(Category::class, $result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeGroup(array $attrs = []): CategoryGroup
    {
        return CategoryGroup::factory()->create($attrs);
    }

    public function test_create_persists_category_to_database(): void
    {
        $group = $this->makeGroup();
        $this->service->create($group, [
            'name' => 'Fruits',
            'handle' => 'fruits',
        ]);

        $this->assertDatabaseHas('categories', [
            'group_id' => $group->id,
            'name' => 'Fruits',
            'handle' => 'fruits',
        ]);
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function test_create_accepts_group_as_model(): void
    {
        $group = $this->makeGroup();
        $result = $this->service->create($group, [
            'name' => 'By Model',
            'handle' => 'by-model',
        ]);

        $this->assertEquals($group->id, $result->group_id);
    }

    public function test_create_accepts_group_as_integer_id(): void
    {
        $group = $this->makeGroup();
        $result = $this->service->create($group->id, [
            'name' => 'By Int',
            'handle' => 'by-int',
        ]);

        $this->assertEquals($group->id, $result->group_id);
    }

    public function test_create_stores_sort_order(): void
    {
        $group = $this->makeGroup();
        $result = $this->service->create($group, [
            'name' => 'Sorted',
            'handle' => 'sorted',
            'sort_order' => 7,
        ]);

        $this->assertEquals(7, $result->sort_order);
    }

    public function test_create_stores_parent_id(): void
    {
        $group = $this->makeGroup();
        $parent = $this->service->create($group, ['name' => 'Parent', 'handle' => 'parent']);

        $child = $this->service->create($group, [
            'name' => 'Child',
            'handle' => 'child',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
    }

    public function test_create_processes_fields_key_when_present(): void
    {
        $group = $this->makeGroup();
        $result = $this->service->create($group, [
            'name' => 'With Fields',
            'handle' => 'with-fields',
            'fields' => [],
        ]);

        $this->assertInstanceOf(Category::class, $result);
    }

    public function test_create_skips_fields_when_key_is_absent(): void
    {
        $group = $this->makeGroup();
        $result = $this->service->create($group, [
            'name' => 'No Fields',
            'handle' => 'no-fields',
        ]);

        $this->assertInstanceOf(Category::class, $result);
    }

    public function test_create_strips_fields_key_from_category_attributes(): void
    {
        $group = $this->makeGroup();
        // Passing fields to Category::create would throw — reaching here means it was stripped.
        $result = $this->service->create($group, [
            'name' => 'Strip Fields',
            'handle' => 'strip-fields',
            'fields' => [],
        ]);

        $this->assertDatabaseHas('categories', ['name' => 'Strip Fields']);
    }

    public function test_create_returns_refreshed_model_with_id(): void
    {
        $group = $this->makeGroup();
        $result = $this->service->create($group, ['name' => 'Fresh', 'handle' => 'fresh']);

        $this->assertNotNull($result->id);
    }

    public function test_update_returns_category_instance(): void
    {
        $category = $this->makeCategory();
        $result = $this->service->update($category, ['name' => 'Updated']);

        $this->assertInstanceOf(Category::class, $result);
    }

    private function makeCategory(array $attrs = []): Category
    {
        return Category::factory()->create($attrs);
    }

    // -------------------------------------------------------------------------
    // update()
    // -------------------------------------------------------------------------

    public function test_update_persists_changed_attributes(): void
    {
        $category = $this->makeCategory(['name' => 'Before', 'handle' => 'before']);

        $this->service->update($category, ['name' => 'After', 'handle' => 'after']);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'After',
        ]);
    }

    public function test_update_skips_model_save_when_only_fields_key_provided(): void
    {
        $category = $this->makeCategory(['name' => 'Unchanged']);

        $this->service->update($category, ['fields' => []]);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Unchanged']);
    }

    public function test_update_processes_fields_key_when_present(): void
    {
        $category = $this->makeCategory();
        $result = $this->service->update($category, ['fields' => []]);

        $this->assertInstanceOf(Category::class, $result);
    }

    public function test_update_skips_fields_when_key_is_absent(): void
    {
        $category = $this->makeCategory();
        $result = $this->service->update($category, ['name' => 'No Fields Key']);

        $this->assertInstanceOf(Category::class, $result);
    }

    public function test_update_reflects_new_values_on_returned_model(): void
    {
        $category = $this->makeCategory(['name' => 'Old']);

        $result = $this->service->update($category, ['name' => 'New', 'handle' => 'new']);

        $this->assertEquals('New', $result->name);
    }

    public function test_delete_removes_category_from_database(): void
    {
        $category = $this->makeCategory();

        $this->service->delete($category);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function test_delete_returns_true_on_success(): void
    {
        $category = $this->makeCategory();

        $this->assertTrue($this->service->delete($category));
    }

    public function test_move_updates_parent_id(): void
    {
        $group = $this->makeGroup();
        $parent = $this->makeCategory(['group_id' => $group->id]);
        $child = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->move($child, $parent->id);

        $this->assertEquals($parent->id, $result->fresh()->parent_id);
    }

    // -------------------------------------------------------------------------
    // move()
    // -------------------------------------------------------------------------

    public function test_move_updates_sort_order(): void
    {
        $group = $this->makeGroup();
        $parent = $this->makeCategory(['group_id' => $group->id]);
        $category = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->move($category, $parent->id, 5);

        $this->assertEquals(5, $result->fresh()->sort_order);
    }

    public function test_move_to_null_promotes_to_root(): void
    {
        $group = $this->makeGroup();
        $parent = $this->makeCategory(['group_id' => $group->id]);
        $child = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $parent->id]);

        $result = $this->service->move($child, null);

        $this->assertNull($result->fresh()->parent_id);
    }

    public function test_move_returns_category_instance(): void
    {
        $group = $this->makeGroup();
        $category = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->move($category, null);

        $this->assertInstanceOf(Category::class, $result);
    }

    public function test_move_defaults_sort_order_to_zero(): void
    {
        $group = $this->makeGroup();
        $category = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->move($category, null);

        $this->assertEquals(0, $result->fresh()->sort_order);
    }

    public function test_move_throws_when_moving_category_under_itself(): void
    {
        $category = $this->makeCategory();

        $this->expectException(InvalidArgumentException::class);

        $this->service->move($category, $category->id);
    }

    public function test_move_throws_when_moving_category_under_direct_child(): void
    {
        $group = $this->makeGroup();
        $parent = $this->makeCategory(['group_id' => $group->id]);
        $child = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $parent->id]);

        $this->expectException(InvalidArgumentException::class);

        // Moving parent under child would create: child → parent → child
        $this->service->move($parent, $child->id);
    }

    public function test_move_throws_when_moving_category_under_grandchild(): void
    {
        $group = $this->makeGroup();
        $grandparent = $this->makeCategory(['group_id' => $group->id]);
        $parent = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $grandparent->id]);
        $grandchild = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $parent->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->move($grandparent, $grandchild->id);
    }

    public function test_move_does_not_throw_when_moving_to_unrelated_category(): void
    {
        $group = $this->makeGroup();
        $catA = $this->makeCategory(['group_id' => $group->id]);
        $catB = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->move($catA, $catB->id);

        $this->assertEquals($catB->id, $result->fresh()->parent_id);
    }

    public function test_field_array_returns_array(): void
    {
        $category = $this->makeCategory();

        $result = $this->service->fieldArray($category);

        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // fieldArray()
    // -------------------------------------------------------------------------

    public function test_field_array_returns_empty_array_when_no_field_values(): void
    {
        $category = $this->makeCategory();

        $result = $this->service->fieldArray($category);

        $this->assertEmpty($result);
    }

    public function test_field_array_loads_field_values_relation(): void
    {
        $category = $this->makeCategory();

        $this->service->fieldArray($category);

        $this->assertTrue($category->relationLoaded('fieldValues'));
    }

    public function test_resolve_layout_returns_null_when_group_has_no_layout(): void
    {
        $group = $this->makeGroup(['field_layout_id' => null]);
        $category = $this->makeCategory(['group_id' => $group->id]);

        $this->assertNull($this->service->resolveLayout($category));
    }

    // -------------------------------------------------------------------------
    // resolveLayout()
    // -------------------------------------------------------------------------

    public function test_resolve_layout_returns_field_layout_when_group_has_one(): void
    {
        $layout = FieldLayout::create(['name' => 'Category Layout']);
        $group = $this->makeGroup(['field_layout_id' => $layout->id]);
        $category = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->resolveLayout($category);

        $this->assertInstanceOf(FieldLayout::class, $result);
        $this->assertEquals($layout->id, $result->id);
    }

    public function test_resolve_field_groups_returns_collection(): void
    {
        $group = $this->makeGroup();
        $category = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->resolveFieldGroups($category);

        $this->assertInstanceOf(SupportCollection::class, $result);
    }

    // -------------------------------------------------------------------------
    // resolveFieldGroups()
    // -------------------------------------------------------------------------

    public function test_resolve_field_groups_returns_empty_when_none_attached(): void
    {
        $group = $this->makeGroup();
        $category = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->resolveFieldGroups($category);

        $this->assertEmpty($result);
    }

    public function test_resolve_fields_returns_collection(): void
    {
        $group = $this->makeGroup();
        $category = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->resolveFields($category);

        $this->assertInstanceOf(SupportCollection::class, $result);
    }

    // -------------------------------------------------------------------------
    // resolveFields()
    // -------------------------------------------------------------------------

    public function test_resolve_fields_returns_empty_when_group_has_no_layout(): void
    {
        $group = $this->makeGroup(['field_layout_id' => null]);
        $category = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->resolveFields($category);

        $this->assertEmpty($result);
    }

    public function test_resolve_fields_returns_collection_when_layout_exists(): void
    {
        $layout = FieldLayout::create(['name' => 'Fields Layout']);
        $group = $this->makeGroup(['field_layout_id' => $layout->id]);
        $category = $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->resolveFields($category);

        $this->assertInstanceOf(SupportCollection::class, $result);
    }

    public function test_tree_returns_collection(): void
    {
        $group = $this->makeGroup();

        $result = $this->service->tree($group);

        $this->assertInstanceOf(Collection::class, $result);
    }

    // -------------------------------------------------------------------------
    // tree()
    // -------------------------------------------------------------------------

    public function test_tree_accepts_group_as_model(): void
    {
        $group = $this->makeGroup();
        $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);

        $result = $this->service->tree($group);

        $this->assertCount(1, $result);
    }

    public function test_tree_accepts_group_as_integer_id(): void
    {
        $group = $this->makeGroup();
        $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);

        $result = $this->service->tree($group->id);

        $this->assertCount(1, $result);
    }

    public function test_tree_returns_only_root_categories_at_top_level(): void
    {
        $group = $this->makeGroup();
        $root = $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);
        $child = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $root->id]);

        $result = $this->service->tree($group);

        // Only the root is at the top level; child is nested inside
        $this->assertCount(1, $result);
        $this->assertEquals($root->id, $result->first()->id);
    }

    public function test_tree_eager_loads_children_recursive(): void
    {
        $group = $this->makeGroup();
        $root = $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);

        $result = $this->service->tree($group);

        $this->assertTrue($result->first()->relationLoaded('childrenRecursive'));
    }

    public function test_tree_excludes_categories_from_other_groups(): void
    {
        $groupA = $this->makeGroup();
        $groupB = $this->makeGroup();
        $this->makeCategory(['group_id' => $groupA->id]);
        $this->makeCategory(['group_id' => $groupB->id]);

        $result = $this->service->tree($groupA);

        $this->assertCount(1, $result);
        $this->assertEquals($groupA->id, $result->first()->group_id);
    }

    public function test_flat_returns_collection(): void
    {
        $group = $this->makeGroup();

        $result = $this->service->flat($group);

        $this->assertInstanceOf(Collection::class, $result);
    }

    // -------------------------------------------------------------------------
    // flat()
    // -------------------------------------------------------------------------

    public function test_flat_accepts_group_as_model(): void
    {
        $group = $this->makeGroup();
        $this->makeCategory(['group_id' => $group->id]);
        $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->flat($group);

        $this->assertCount(2, $result);
    }

    public function test_flat_accepts_group_as_integer_id(): void
    {
        $group = $this->makeGroup();
        $this->makeCategory(['group_id' => $group->id]);

        $result = $this->service->flat($group->id);

        $this->assertCount(1, $result);
    }

    public function test_flat_returns_all_categories_including_nested(): void
    {
        $group = $this->makeGroup();
        $root = $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);
        $child = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $root->id]);

        $result = $this->service->flat($group);

        $this->assertCount(2, $result);
    }

    public function test_flat_excludes_categories_from_other_groups(): void
    {
        $groupA = $this->makeGroup();
        $groupB = $this->makeGroup();
        $this->makeCategory(['group_id' => $groupA->id]);
        $this->makeCategory(['group_id' => $groupA->id]);
        $this->makeCategory(['group_id' => $groupB->id]);

        $result = $this->service->flat($groupA);

        $this->assertCount(2, $result);
        $result->each(fn($c) => $this->assertEquals($groupA->id, $c->group_id));
    }

    public function test_flat_orders_by_sort_order_then_name(): void
    {
        $group = $this->makeGroup();
        $b = $this->makeCategory(['group_id' => $group->id, 'sort_order' => 1, 'name' => 'Beta']);
        $a = $this->makeCategory(['group_id' => $group->id, 'sort_order' => 0, 'name' => 'Alpha']);
        $c = $this->makeCategory(['group_id' => $group->id, 'sort_order' => 2, 'name' => 'Gamma']);

        $result = $this->service->flat($group);
        $ids = $result->pluck('id')->all();

        $this->assertEquals([$a->id, $b->id, $c->id], $ids);
    }

    // -------------------------------------------------------------------------
    // wouldCreateCycle() — MED-01 fix guard
    //
    // These tests target the private cycle-detection walk called by move().
    // Three properties are verified:
    //
    // 1. CORRECTNESS  — deep ancestor chains (beyond grandchild) are detected,
    //    proving the walk iterates fully rather than stopping after two levels.
    //
    // 2. SAFETY       — when stored data already contains a cycle (corrupt
    //    state), the visited-ID set prevents an infinite loop and move()
    //    returns a result rather than hanging.
    //
    // 3. EFFICIENCY   — each ancestor step issues a single-column
    //    SELECT "parent_id" query rather than a full SELECT *, confirming
    //    the N+1 full-model-load pattern from the original code is gone.
    // -------------------------------------------------------------------------

    public function test_move_throws_when_moving_category_under_deep_descendant(): void
    {
        // Build: root → A → B → C → D → E  (5 levels deep)
        $group = $this->makeGroup();
        $root = $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);
        $a    = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $root->id]);
        $b    = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $a->id]);
        $c    = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $b->id]);
        $d    = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $c->id]);
        $e    = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $d->id]);

        // Moving $root under $e would require root to be a child of its own
        // 5th-generation descendant — a cycle the walk must detect.
        $this->expectException(InvalidArgumentException::class);

        $this->service->move($root, $e->id);
    }

    public function test_move_does_not_throw_across_a_long_sibling_chain(): void
    {
        // 5-level chain in a separate branch — moving an unrelated node there
        // must not be misidentified as a cycle.
        $group  = $this->makeGroup();
        $branch = $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);
        $b1     = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $branch->id]);
        $b2     = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $b1->id]);
        $b3     = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $b2->id]);
        $b4     = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $b3->id]);

        $unrelated = $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);

        $result = $this->service->move($unrelated, $b4->id);

        $this->assertEquals($b4->id, $result->fresh()->parent_id);
    }

    public function test_move_completes_safely_when_stored_data_contains_a_cycle(): void
    {
        // Arrange: A and B in a valid parent/child relationship,
        // then manually corrupt the DB so A ↔ B reference each other.
        $group = $this->makeGroup();
        $a     = $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);
        $b     = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $a->id]);

        // Corrupt stored data: give A a parent_id pointing back to B.
        // This bypasses move() so no cycle guard fires during setup.
        Category::where('id', $a->id)->update(['parent_id' => $b->id]);

        // An unrelated category D that we want to place under A.
        $d = $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);

        // The visited-ID set must detect the A ↔ B cycle and stop the walk
        // rather than looping forever. The move itself is not a cycle (D is
        // not an ancestor of A), so it should succeed.
        $result = $this->service->move($d, $a->id);

        $this->assertEquals($a->id, $result->fresh()->parent_id);
    }

    public function test_cycle_walk_issues_narrow_single_column_queries(): void
    {
        // Build a 4-level chain: root → a → b → c
        // Moving $root under $c requires walking c → b → a → (finds root → cycle).
        // That is 3 ancestor-walk queries; each must select only "parent_id".
        $group = $this->makeGroup();
        $root  = $this->makeCategory(['group_id' => $group->id, 'parent_id' => null]);
        $a     = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $root->id]);
        $b     = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $a->id]);
        $c     = $this->makeCategory(['group_id' => $group->id, 'parent_id' => $b->id]);

        DB::enableQueryLog();

        try {
            $this->service->move($root, $c->id); // throws — cycle detected
        } catch (InvalidArgumentException $e) {
            // Expected; we only care about the queries that ran.
        }

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        // Isolate the three ancestor-walk queries by their distinctive shape:
        // they select a single column from categories by primary key.
        $walkQueries = array_values(array_filter(
            $log,
            fn($q) => str_contains(strtolower($q['query']), 'parent_id')
                   && str_contains(strtolower($q['query']), 'categories'),
        ));

        // Exactly 3 steps: c → b → a → (parent_id === root → return true).
        $this->assertCount(3, $walkQueries,
            'Expected exactly 3 ancestor-walk queries for a 3-level chain before the cycle is found.'
        );

        // Each query must read "parent_id" specifically — not "select *".
        foreach ($walkQueries as $q) {
            $sql = strtolower($q['query']);
            $this->assertStringNotContainsString('select *', $sql,
                'Ancestor walk must not use SELECT * — it should read only the parent_id column.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CategoryService::class);
    }
}
