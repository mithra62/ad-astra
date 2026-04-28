<?php

namespace Tests\Unit\Actions\Category\Group;

use App\Actions\Category\Group\CreateNewCategoryGroup;
use App\Actions\Category\Group\EditCategoryGroup;
use App\Models\Category\Group;
use App\Models\Field\Group as FieldGroup;
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

    public function test_create_also_creates_field_layout_for_group(): void
    {
        $action = app(CreateNewCategoryGroup::class);

        $group = $action->create(['name' => 'Topics', 'handle' => 'topics', 'sort_order' => 0]);

        $this->assertNotNull($group->field_layout_id);
        $this->assertDatabaseHas('field_layouts', [
            'id' => $group->field_layout_id,
            'name' => 'Topics Layout cat',
        ]);
    }

    public function test_create_links_auto_created_layout_to_group(): void
    {
        $action = app(CreateNewCategoryGroup::class);

        $group = $action->create(['name' => 'Genres', 'handle' => 'genres', 'sort_order' => 0]);

        $this->assertNotNull($group->fieldLayout);
        $this->assertEquals($group->field_layout_id, $group->fieldLayout->id);
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

    public function test_edit_syncs_field_groups(): void
    {
        $group = Group::factory()->create();
        $fieldGroup1 = FieldGroup::factory()->create();
        $fieldGroup2 = FieldGroup::factory()->create();
        $action = app(EditCategoryGroup::class);

        // Attach first field group
        $action->edit($group, ['name' => $group->name, 'handle' => $group->handle, 'field_groups' => [$fieldGroup1->id]]);
        $this->assertTrue($group->fieldGroups()->where('group_id', $fieldGroup1->id)->exists());

        // Sync to second field group only
        $action->edit($group, ['name' => $group->name, 'handle' => $group->handle, 'field_groups' => [$fieldGroup2->id]]);
        $this->assertFalse($group->fresh()->fieldGroups()->where('group_id', $fieldGroup1->id)->exists());
        $this->assertTrue($group->fresh()->fieldGroups()->where('group_id', $fieldGroup2->id)->exists());
    }

    public function test_edit_detaches_all_field_groups_when_none_provided(): void
    {
        $group = Group::factory()->create();
        $fieldGroup = FieldGroup::factory()->create();
        $group->fieldGroups()->attach($fieldGroup->id);
        $action = app(EditCategoryGroup::class);

        $action->edit($group, ['name' => $group->name, 'handle' => $group->handle]);

        $this->assertCount(0, $group->fresh()->fieldGroups);
    }
}
