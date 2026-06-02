<?php

namespace Tests\Unit\Actions\Entry\Group;

use App\Actions\Entry\Group\CreateNewEntryGroup;
use App\Actions\Entry\Group\EditEntryGroup;
use App\Models\Category\Group as CategoryGroup;
use App\Models\EntryGroup;
use App\Models\Field\Group as FieldGroup;
use App\Models\FieldLayout;
use App\Models\StatusGroup;
use App\Services\EntryGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryGroupActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // create  (via EntryGroupService)
    // -------------------------------------------------------------------------

    public function test_create_returns_entry_group_instance(): void
    {
        $layout = FieldLayout::factory()->create();
        $result = app(EntryGroupService::class)->create(['name' => 'Blog', 'handle' => 'blog', 'field_layout_id' => $layout->id]);

        $this->assertInstanceOf(EntryGroup::class, $result);
    }

    public function test_create_persists_group_to_database(): void
    {
        $layout = FieldLayout::factory()->create();
        app(EntryGroupService::class)->create([
            'name' => 'News',
            'handle' => 'news',
            'description' => 'News section',
            'sort_order' => 2,
            'field_layout_id' => $layout->id,
        ]);

        $this->assertDatabaseHas('entry_groups', [
            'name' => 'News',
            'handle' => 'news',
            'description' => 'News section',
            'sort_order' => 2,
        ]);
    }

    public function test_create_stores_field_layout_id(): void
    {
        $layout = FieldLayout::factory()->create();
        $group = app(EntryGroupService::class)->create(['name' => 'Events', 'handle' => 'events', 'field_layout_id' => $layout->id]);

        $this->assertEquals($layout->id, $group->field_layout_id);
    }

    public function test_create_stores_status_group_id_when_provided(): void
    {
        $statusGroup = StatusGroup::factory()->create();
        $layout = FieldLayout::factory()->create();

        $group = app(EntryGroupService::class)->create([
            'name' => 'Portfolio',
            'handle' => 'portfolio',
            'status_group_id' => $statusGroup->id,
            'field_layout_id' => $layout->id,
        ]);

        $this->assertEquals($statusGroup->id, $group->status_group_id);
    }

    public function test_create_syncs_category_groups(): void
    {
        $catGroup = CategoryGroup::factory()->create();
        $layout = FieldLayout::factory()->create();

        $group = app(EntryGroupService::class)->create([
            'name' => 'Articles',
            'handle' => 'articles',
            'category_groups' => [$catGroup->id],
            'field_layout_id' => $layout->id,
        ]);

        $this->assertTrue($group->categoryGroups()->where('group_id', $catGroup->id)->exists());
    }

    public function test_create_syncs_field_groups(): void
    {
        $fieldGroup = FieldGroup::factory()->create();
        $layout = FieldLayout::factory()->create();

        $group = app(EntryGroupService::class)->create([
            'name' => 'Products',
            'handle' => 'products',
            'field_groups' => [$fieldGroup->id],
            'field_layout_id' => $layout->id,
        ]);

        $this->assertTrue($group->fieldGroups()->where('group_id', $fieldGroup->id)->exists());
    }

    public function test_create_with_no_category_or_field_groups_does_not_error(): void
    {
        $layout = FieldLayout::factory()->create();
        $group = app(EntryGroupService::class)->create(['name' => 'Simple', 'handle' => 'simple', 'field_layout_id' => $layout->id]);

        $this->assertCount(0, $group->categoryGroups);
        $this->assertCount(0, $group->fieldGroups);
    }

    // -------------------------------------------------------------------------
    // update  (via EntryGroupService)
    // -------------------------------------------------------------------------

    public function test_edit_returns_entry_group_instance(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryGroupService::class)->update($group, [
            'name' => 'Updated',
            'handle' => 'updated',
        ]);

        $this->assertInstanceOf(EntryGroup::class, $result);
    }

    public function test_edit_updates_name_and_handle(): void
    {
        $group = EntryGroup::factory()->create(['name' => 'Old', 'handle' => 'old']);

        app(EntryGroupService::class)->update($group, ['name' => 'New Name', 'handle' => 'new-name']);

        $this->assertDatabaseHas('entry_groups', [
            'id' => $group->id,
            'name' => 'New Name',
            'handle' => 'new-name',
        ]);
    }

    public function test_edit_updates_description_and_sort_order(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryGroupService::class)->update($group, [
            'name' => $group->name,
            'handle' => $group->handle,
            'description' => 'New description',
            'sort_order' => 9,
        ]);

        $this->assertEquals('New description', $result->description);
        $this->assertEquals(9, $result->sort_order);
    }

    public function test_edit_updates_status_group_id(): void
    {
        $statusGroup = StatusGroup::factory()->create();
        $group = EntryGroup::factory()->create();

        $result = app(EntryGroupService::class)->update($group, [
            'name' => $group->name,
            'handle' => $group->handle,
            'status_group_id' => $statusGroup->id,
        ]);

        $this->assertEquals($statusGroup->id, $result->status_group_id);
    }

    public function test_edit_syncs_category_groups(): void
    {
        $group = EntryGroup::factory()->create();
        $catGroup = CategoryGroup::factory()->create();

        app(EntryGroupService::class)->update($group, [
            'name' => $group->name,
            'handle' => $group->handle,
            'category_groups' => [$catGroup->id],
        ]);

        $this->assertTrue($group->fresh()->categoryGroups()->where('group_id', $catGroup->id)->exists());
    }

    public function test_edit_syncs_field_groups(): void
    {
        $group = EntryGroup::factory()->create();
        $fieldGroup = FieldGroup::factory()->create();

        app(EntryGroupService::class)->update($group, [
            'name' => $group->name,
            'handle' => $group->handle,
            'field_groups' => [$fieldGroup->id],
        ]);

        $this->assertTrue($group->fresh()->fieldGroups()->where('group_id', $fieldGroup->id)->exists());
    }

    public function test_edit_returns_fresh_model(): void
    {
        $group = EntryGroup::factory()->create(['name' => 'Before']);

        $result = app(EntryGroupService::class)->update($group, ['name' => 'After', 'handle' => 'after']);

        $this->assertNotSame($group, $result);
        $this->assertEquals('After', $result->name);
    }

    // -------------------------------------------------------------------------
    // CreateNewEntryGroup action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_create_action_delegates_to_service_create(): void
    {
        $group = EntryGroup::factory()->create();
        $service = $this->mock(EntryGroupService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(['name' => 'Blog', 'handle' => 'blog'])
            ->andReturn($group);

        $result = app(CreateNewEntryGroup::class)->create(['name' => 'Blog', 'handle' => 'blog']);

        $this->assertSame($group, $result);
    }

    public function test_create_action_returns_entry_group_instance(): void
    {
        $group = EntryGroup::factory()->create();
        $service = $this->mock(EntryGroupService::class);
        $service->shouldReceive('create')->once()->andReturn($group);

        $result = app(CreateNewEntryGroup::class)->create(['name' => 'Blog', 'handle' => 'blog']);

        $this->assertInstanceOf(EntryGroup::class, $result);
    }

    // -------------------------------------------------------------------------
    // EditEntryGroup action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_edit_action_delegates_to_service_update(): void
    {
        $group = EntryGroup::factory()->create();
        $updated = EntryGroup::factory()->create();
        $service = $this->mock(EntryGroupService::class);
        $service->shouldReceive('update')
            ->once()
            ->with($group, ['name' => 'New', 'handle' => 'new'])
            ->andReturn($updated);

        $result = app(EditEntryGroup::class)->edit($group, ['name' => 'New', 'handle' => 'new']);

        $this->assertSame($updated, $result);
    }

    public function test_edit_action_returns_entry_group_instance(): void
    {
        $group = EntryGroup::factory()->create();
        $updated = EntryGroup::factory()->create();
        $service = $this->mock(EntryGroupService::class);
        $service->shouldReceive('update')->once()->andReturn($updated);

        $result = app(EditEntryGroup::class)->edit($group, []);

        $this->assertInstanceOf(EntryGroup::class, $result);
    }
}
