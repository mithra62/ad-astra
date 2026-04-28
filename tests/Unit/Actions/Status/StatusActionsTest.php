<?php

namespace Tests\Unit\Actions\Status;

use App\Actions\Status\CreateNewStatus;
use App\Actions\Status\EditStatus;
use App\Models\Status;
use App\Models\StatusGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CreateNewStatus::create
    // -------------------------------------------------------------------------

    public function test_create_returns_status_instance(): void
    {
        $group  = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $result = $action->create([
            'status_group_id' => $group->id,
            'name'            => 'Draft',
            'handle'          => 'draft',
            'color'           => '#cccccc',
        ]);

        $this->assertInstanceOf(Status::class, $result);
    }

    public function test_create_persists_status_to_database(): void
    {
        $group  = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $action->create([
            'status_group_id' => $group->id,
            'name'            => 'Published',
            'handle'          => 'published',
            'color'           => '#00ff00',
        ]);

        $this->assertDatabaseHas('statuses', [
            'status_group_id' => $group->id,
            'name'            => 'Published',
            'handle'          => 'published',
        ]);
    }

    public function test_create_clears_existing_default_when_new_status_is_default(): void
    {
        $group    = StatusGroup::factory()->create();
        $existing = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'old-default']);
        $action   = app(CreateNewStatus::class);

        $action->create([
            'status_group_id' => $group->id,
            'name'            => 'New Default',
            'handle'          => 'new-default',
            'color'           => '#0000ff',
            'is_default'      => true,
        ]);

        $this->assertFalse($existing->fresh()->is_default);
    }

    public function test_create_new_default_status_is_marked_default(): void
    {
        $group  = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $status = $action->create([
            'status_group_id' => $group->id,
            'name'            => 'Active',
            'handle'          => 'active',
            'color'           => '#ff0000',
            'is_default'      => true,
        ]);

        $this->assertTrue($status->is_default);
    }

    public function test_create_does_not_clear_existing_defaults_when_not_default(): void
    {
        $group    = StatusGroup::factory()->create();
        $existing = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'keep-default']);
        $action   = app(CreateNewStatus::class);

        $action->create([
            'status_group_id' => $group->id,
            'name'            => 'Non Default',
            'handle'          => 'non-default',
            'color'           => '#aaaaaa',
            'is_default'      => false,
        ]);

        $this->assertTrue($existing->fresh()->is_default);
    }

    // -------------------------------------------------------------------------
    // CreateNewStatus::createByGroup
    // -------------------------------------------------------------------------

    public function test_create_by_group_returns_status_instance(): void
    {
        $group  = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $result = $action->createByGroup([
            'status_group_id' => $group->id,
            'name'            => 'Pending',
            'handle'          => 'pending',
            'color'           => '#ffff00',
        ]);

        $this->assertInstanceOf(Status::class, $result);
    }

    public function test_create_by_group_associates_status_with_group(): void
    {
        $group  = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $status = $action->createByGroup([
            'status_group_id' => $group->id,
            'name'            => 'Archived',
            'handle'          => 'archived',
            'color'           => '#888888',
        ]);

        $this->assertEquals($group->id, $status->status_group_id);
        $this->assertTrue($group->statuses()->where('id', $status->id)->exists());
    }

    public function test_create_by_group_clears_existing_default_when_new_is_default(): void
    {
        $group    = StatusGroup::factory()->create();
        $existing = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'old']);
        $action   = app(CreateNewStatus::class);

        $action->createByGroup([
            'status_group_id' => $group->id,
            'name'            => 'New Default',
            'handle'          => 'new',
            'color'           => '#123456',
            'is_default'      => true,
        ]);

        $this->assertFalse($existing->fresh()->is_default);
    }

    // -------------------------------------------------------------------------
    // EditStatus::edit
    // -------------------------------------------------------------------------

    public function test_edit_returns_true_on_success(): void
    {
        $status = Status::factory()->create();
        $action = app(EditStatus::class);

        $result = $action->edit($status, [
            'name'   => 'Updated',
            'handle' => 'updated',
            'color'  => '#000000',
        ]);

        $this->assertTrue($result);
    }

    public function test_edit_updates_name_handle_and_color(): void
    {
        $status = Status::factory()->create(['name' => 'Old', 'handle' => 'old', 'color' => '#000000']);
        $action = app(EditStatus::class);

        $action->edit($status, ['name' => 'New', 'handle' => 'new', 'color' => '#ffffff']);

        $this->assertDatabaseHas('statuses', [
            'id'     => $status->id,
            'name'   => 'New',
            'handle' => 'new',
            'color'  => '#ffffff',
        ]);
    }

    public function test_edit_sets_is_default_true_and_clears_other_defaults(): void
    {
        $group   = StatusGroup::factory()->create();
        $other   = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'other']);
        $subject = Status::factory()->for($group, 'group')->create(['is_default' => false, 'handle' => 'subject']);
        $action  = app(EditStatus::class);

        $action->edit($subject, [
            'name'       => $subject->name,
            'handle'     => $subject->handle,
            'color'      => $subject->color,
            'is_default' => true,
        ]);

        $this->assertFalse($other->fresh()->is_default);
        $this->assertTrue($subject->fresh()->is_default);
    }

    public function test_edit_does_not_clear_other_defaults_when_not_setting_default(): void
    {
        $group   = StatusGroup::factory()->create();
        $other   = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'other']);
        $subject = Status::factory()->for($group, 'group')->create(['is_default' => false, 'handle' => 'subject']);
        $action  = app(EditStatus::class);

        $action->edit($subject, [
            'name'       => $subject->name,
            'handle'     => $subject->handle,
            'color'      => $subject->color,
            'is_default' => false,
        ]);

        $this->assertTrue($other->fresh()->is_default);
    }

    public function test_edit_casts_is_default_to_boolean(): void
    {
        $status = Status::factory()->create(['is_default' => false]);
        $action = app(EditStatus::class);

        // Pass a truthy string — action should cast it
        $action->edit($status, [
            'name'       => $status->name,
            'handle'     => $status->handle,
            'color'      => $status->color,
            'is_default' => '1',
        ]);

        $this->assertTrue($status->fresh()->is_default);
    }
}
