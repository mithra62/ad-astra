<?php

namespace Tests\Unit\Actions\Entry\Group;

use App\Actions\Entry\Group\CreateNewEntryGroup;
use App\Actions\Entry\Group\EditEntryGroup;
use App\Models\Category\Group as CategoryGroup;
use App\Models\EntryGroup;
use App\Models\Field\Group as FieldGroup;
use App\Models\FieldLayout;
use App\Models\StatusGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryGroupActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CreateNewEntryGroup
    // -------------------------------------------------------------------------

    public function test_create_returns_entry_group_instance(): void
    {
        $action = app(CreateNewEntryGroup::class);

        $result = $action->create(['name' => 'Blog', 'handle' => 'blog']);

        $this->assertInstanceOf(EntryGroup::class, $result);
    }

    public function test_create_persists_group_to_database(): void
    {
        $action = app(CreateNewEntryGroup::class);

        $action->create(['name' => 'News', 'handle' => 'news', 'description' => 'News section', 'sort_order' => 2]);

        $this->assertDatabaseHas('entry_groups', [
            'name'        => 'News',
            'handle'      => 'news',
            'description' => 'News section',
            'sort_order'  => 2,
        ]);
    }

    public function test_create_auto_creates_field_layout(): void
    {
        $action = app(CreateNewEntryGroup::class);

        $group = $action->create(['name' => 'Events', 'handle' => 'events']);

        $this->assertNotNull($group->field_layout_id);
        $this->assertDatabaseHas('field_layouts', [
            'id'   => $group->field_layout_id,
            'name' => 'Events Entries',
        ]);
    }

    public function test_create_stores_status_group_id_when_provided(): void
    {
        $statusGroup = StatusGroup::factory()->create();
        $action      = app(CreateNewEntryGroup::class);

        $group = $action->create([
            'name'            => 'Portfolio',
            'handle'          => 'portfolio',
            'status_group_id' => $statusGroup->id,
        ]);

        $this->assertEquals($statusGroup->id, $group->status_group_id);
    }

    public function test_create_syncs_category_groups(): void
    {
        $catGroup = CategoryGroup::factory()->create();
        $action   = app(CreateNewEntryGroup::class);

        $group = $action->create([
            'name'            => 'Articles',
            'handle'          => 'articles',
            'category_groups' => [$catGroup->id],
        ]);

        $this->assertTrue($group->categoryGroups()->where('group_id', $catGroup->id)->exists());
    }

    public function test_create_syncs_field_groups(): void
    {
        $fieldGroup = FieldGroup::factory()->create();
        $action     = app(CreateNewEntryGroup::class);

        $group = $action->create([
            'name'         => 'Products',
            'handle'       => 'products',
            'field_groups' => [$fieldGroup->id],
        ]);

        $this->assertTrue($group->fieldGroups()->where('group_id', $fieldGroup->id)->exists());
    }

    public function test_create_with_no_category_or_field_groups_does_not_error(): void
    {
        $action = app(CreateNewEntryGroup::class);

        $group = $action->create(['name' => 'Simple', 'handle' => 'simple']);

        $this->assertCount(0, $group->categoryGroups);
        $this->assertCount(0, $group->fieldGroups);
    }

    // -------------------------------------------------------------------------
    // EditEntryGroup
    // -------------------------------------------------------------------------

    public function test_edit_returns_entry_group_instance(): void
    {
        $group  = EntryGroup::factory()->create();
        $action = app(EditEntryGroup::class);

        $result = $action->edit($group, [
            'name'   => 'Updated',
            'handle' => 'updated',
        ]);

        $this->assertInstanceOf(EntryGroup::class, $result);
    }

    public function test_edit_updates_name_and_handle(): void
    {
        $group  = EntryGroup::factory()->create(['name' => 'Old', 'handle' => 'old']);
        $action = app(EditEntryGroup::class);

        $action->edit($group, ['name' => 'New Name', 'handle' => 'new-name']);

        $this->assertDatabaseHas('entry_groups', [
            'id'     => $group->id,
            'name'   => 'New Name',
            'handle' => 'new-name',
        ]);
    }

    public function test_edit_updates_description_and_sort_order(): void
    {
        $group  = EntryGroup::factory()->create();
        $action = app(EditEntryGroup::class);

        $result = $action->edit($group, [
            'name'        => $group->name,
            'handle'      => $group->handle,
            'description' => 'New description',
            'sort_order'  => 9,
        ]);

        $this->assertEquals('New description', $result->description);
        $this->assertEquals(9, $result->sort_order);
    }

    public function test_edit_updates_status_group_id(): void
    {
        $statusGroup = StatusGroup::factory()->create();
        $group       = EntryGroup::factory()->create();
        $action      = app(EditEntryGroup::class);

        $result = $action->edit($group, [
            'name'            => $group->name,
            'handle'          => $group->handle,
            'status_group_id' => $statusGroup->id,
        ]);

        $this->assertEquals($statusGroup->id, $result->status_group_id);
    }

    public function test_edit_syncs_category_groups(): void
    {
        $group    = EntryGroup::factory()->create();
        $catGroup = CategoryGroup::factory()->create();
        $action   = app(EditEntryGroup::class);

        $action->edit($group, [
            'name'            => $group->name,
            'handle'          => $group->handle,
            'category_groups' => [$catGroup->id],
        ]);

        $this->assertTrue($group->fresh()->categoryGroups()->where('group_id', $catGroup->id)->exists());
    }

    public function test_edit_syncs_field_groups(): void
    {
        $group      = EntryGroup::factory()->create();
        $fieldGroup = FieldGroup::factory()->create();
        $action     = app(EditEntryGroup::class);

        $action->edit($group, [
            'name'         => $group->name,
            'handle'       => $group->handle,
            'field_groups' => [$fieldGroup->id],
        ]);

        $this->assertTrue($group->fresh()->fieldGroups()->where('group_id', $fieldGroup->id)->exists());
    }

    public function test_edit_returns_fresh_model(): void
    {
        $group  = EntryGroup::factory()->create(['name' => 'Before']);
        $action = app(EditEntryGroup::class);

        $result = $action->edit($group, ['name' => 'After', 'handle' => 'after']);

        $this->assertNotSame($group, $result);
        $this->assertEquals('After', $result->name);
    }
}
