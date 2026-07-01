<?php

namespace Tests\Unit\Actions\Status\Group;

use AdAstra\Actions\Status\Group\CreateNewStatusGroup;
use AdAstra\Actions\Status\Group\EditStatusGroup;
use AdAstra\Models\StatusGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusGroupActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CreateNewStatusGroup
    // -------------------------------------------------------------------------

    public function test_create_returns_status_group_instance(): void
    {
        $action = app(CreateNewStatusGroup::class);

        $result = $action->create(['name' => 'Blog Statuses', 'handle' => 'blog-statuses']);

        $this->assertInstanceOf(StatusGroup::class, $result);
    }

    public function test_create_persists_status_group_to_database(): void
    {
        $action = app(CreateNewStatusGroup::class);

        $action->create(['name' => 'Content States', 'handle' => 'content-states', 'sort_order' => 1]);

        $this->assertDatabaseHas('status_groups', [
            'name' => 'Content States',
            'handle' => 'content-states',
            'sort_order' => 1,
        ]);
    }

    public function test_create_stores_name_and_handle(): void
    {
        $action = app(CreateNewStatusGroup::class);

        $group = $action->create(['name' => 'Entry States', 'handle' => 'entry-states']);

        $this->assertEquals('Entry States', $group->name);
        $this->assertEquals('entry-states', $group->handle);
    }

    public function test_create_stores_sort_order(): void
    {
        $action = app(CreateNewStatusGroup::class);

        $group = $action->create(['name' => 'Ordered', 'handle' => 'ordered', 'sort_order' => 5]);

        $this->assertEquals(5, $group->sort_order);
    }

    // -------------------------------------------------------------------------
    // EditStatusGroup
    // -------------------------------------------------------------------------

    public function test_edit_returns_true_on_success(): void
    {
        $group = StatusGroup::factory()->create();
        $action = app(EditStatusGroup::class);

        $result = $action->edit($group, ['name' => 'Updated', 'handle' => 'updated']);

        $this->assertTrue($result);
    }

    public function test_edit_updates_name_and_handle(): void
    {
        $group = StatusGroup::factory()->create(['name' => 'Old', 'handle' => 'old']);
        $action = app(EditStatusGroup::class);

        $action->edit($group, ['name' => 'New Name', 'handle' => 'new-handle']);

        $this->assertDatabaseHas('status_groups', [
            'id' => $group->id,
            'name' => 'New Name',
            'handle' => 'new-handle',
        ]);
    }

    public function test_edit_updates_sort_order(): void
    {
        $group = StatusGroup::factory()->create(['sort_order' => 1]);
        $action = app(EditStatusGroup::class);

        $action->edit($group, [
            'name' => $group->name,
            'handle' => $group->handle,
            'sort_order' => 7,
        ]);

        $this->assertEquals(7, $group->fresh()->sort_order);
    }

    public function test_edit_persists_changes(): void
    {
        $group = StatusGroup::factory()->create(['name' => 'Before']);
        $action = app(EditStatusGroup::class);

        $action->edit($group, ['name' => 'After', 'handle' => 'after']);

        $this->assertEquals('After', $group->fresh()->name);
    }
}
