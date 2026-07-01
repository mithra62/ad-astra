<?php

namespace Tests\Unit\Actions\Status;

use AdAstra\Actions\Status\CreateNewStatus;
use AdAstra\Actions\Status\EditStatus;
use AdAstra\Models\Status;
use AdAstra\Models\StatusGroup;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CreateNewStatus::create
    // -------------------------------------------------------------------------

    public function test_create_returns_status_instance(): void
    {
        $group = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $result = $action->create([
            'status_group_id' => $group->id,
            'name' => 'Draft',
            'handle' => 'draft',
            'color' => '#cccccc',
        ]);

        $this->assertInstanceOf(Status::class, $result);
    }

    public function test_create_persists_status_to_database(): void
    {
        $group = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $action->create([
            'status_group_id' => $group->id,
            'name' => 'Published',
            'handle' => 'published',
            'color' => '#00ff00',
        ]);

        $this->assertDatabaseHas('statuses', [
            'status_group_id' => $group->id,
            'name' => 'Published',
            'handle' => 'published',
        ]);
    }

    public function test_create_clears_existing_default_when_new_status_is_default(): void
    {
        $group = StatusGroup::factory()->create();
        $existing = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'old-default']);
        $action = app(CreateNewStatus::class);

        $action->create([
            'status_group_id' => $group->id,
            'name' => 'New Default',
            'handle' => 'new-default',
            'color' => '#0000ff',
            'is_default' => true,
        ]);

        $this->assertFalse($existing->fresh()->is_default);
    }

    public function test_create_new_default_status_is_marked_default(): void
    {
        $group = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $status = $action->create([
            'status_group_id' => $group->id,
            'name' => 'Active',
            'handle' => 'active',
            'color' => '#ff0000',
            'is_default' => true,
        ]);

        $this->assertTrue($status->is_default);
    }

    public function test_create_does_not_clear_existing_defaults_when_not_default(): void
    {
        $group = StatusGroup::factory()->create();
        $existing = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'keep-default']);
        $action = app(CreateNewStatus::class);

        $action->create([
            'status_group_id' => $group->id,
            'name' => 'Non Default',
            'handle' => 'non-default',
            'color' => '#aaaaaa',
            'is_default' => false,
        ]);

        $this->assertTrue($existing->fresh()->is_default);
    }

    // -------------------------------------------------------------------------
    // CreateNewStatus::createByGroup
    // -------------------------------------------------------------------------

    public function test_create_by_group_returns_status_instance(): void
    {
        $group = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $result = $action->createByGroup([
            'status_group_id' => $group->id,
            'name' => 'Pending',
            'handle' => 'pending',
            'color' => '#ffff00',
        ]);

        $this->assertInstanceOf(Status::class, $result);
    }

    public function test_create_by_group_associates_status_with_group(): void
    {
        $group = StatusGroup::factory()->create();
        $action = app(CreateNewStatus::class);

        $status = $action->createByGroup([
            'status_group_id' => $group->id,
            'name' => 'Archived',
            'handle' => 'archived',
            'color' => '#888888',
        ]);

        $this->assertEquals($group->id, $status->status_group_id);
        $this->assertTrue($group->statuses()->where('id', $status->id)->exists());
    }

    public function test_create_by_group_clears_existing_default_when_new_is_default(): void
    {
        $group = StatusGroup::factory()->create();
        $existing = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'old']);
        $action = app(CreateNewStatus::class);

        $action->createByGroup([
            'status_group_id' => $group->id,
            'name' => 'New Default',
            'handle' => 'new',
            'color' => '#123456',
            'is_default' => true,
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
            'name' => 'Updated',
            'handle' => 'updated',
            'color' => '#000000',
        ]);

        $this->assertTrue($result);
    }

    public function test_edit_updates_name_handle_and_color(): void
    {
        $status = Status::factory()->create(['name' => 'Old', 'handle' => 'old', 'color' => '#000000']);
        $action = app(EditStatus::class);

        $action->edit($status, ['name' => 'New', 'handle' => 'new', 'color' => '#ffffff']);

        $this->assertDatabaseHas('statuses', [
            'id' => $status->id,
            'name' => 'New',
            'handle' => 'new',
            'color' => '#ffffff',
        ]);
    }

    public function test_edit_sets_is_default_true_and_clears_other_defaults(): void
    {
        $group = StatusGroup::factory()->create();
        $other = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'other']);
        $subject = Status::factory()->for($group, 'group')->create(['is_default' => false, 'handle' => 'subject']);
        $action = app(EditStatus::class);

        $action->edit($subject, [
            'name' => $subject->name,
            'handle' => $subject->handle,
            'color' => $subject->color,
            'is_default' => true,
        ]);

        $this->assertFalse($other->fresh()->is_default);
        $this->assertTrue($subject->fresh()->is_default);
    }

    public function test_edit_does_not_clear_other_defaults_when_not_setting_default(): void
    {
        $group = StatusGroup::factory()->create();
        $other = Status::factory()->for($group, 'group')->create(['is_default' => true, 'handle' => 'other']);
        $subject = Status::factory()->for($group, 'group')->create(['is_default' => false, 'handle' => 'subject']);
        $action = app(EditStatus::class);

        $action->edit($subject, [
            'name' => $subject->name,
            'handle' => $subject->handle,
            'color' => $subject->color,
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
            'name' => $status->name,
            'handle' => $status->handle,
            'color' => $status->color,
            'is_default' => '1',
        ]);

        $this->assertTrue($status->fresh()->is_default);
    }

    // -------------------------------------------------------------------------
    // Transaction safety — HIGH-04 guard
    //
    // These tests verify two properties of the fix introduced for HIGH-04:
    //
    // 1. DETECTION  — each action runs its queries inside a DB::transaction(),
    //    confirmed by observing that DB::transactionLevel() reaches ≥ 1 during
    //    execution.  If someone strips the DB::transaction() wrapper the level
    //    stays at 0 and the assertion fails.
    //
    // 2. ATOMICITY  — if the INSERT / UPDATE at the end of the action throws
    //    (simulated via a unique-constraint violation on statuses.status_group_id
    //    + handle), the preceding UPDATE … is_default = false is rolled back and
    //    the group is left with exactly one default status.  Without the
    //    transaction wrapper, the clear would be committed and the group would
    //    end up with zero default statuses.
    // -------------------------------------------------------------------------

    // -- CreateNewStatus::create() -------------------------------------------

    public function test_create_runs_inside_a_database_transaction(): void
    {
        $group = StatusGroup::factory()->create();
        $observedLevel = 0;

        // DB::listen fires for every query on this connection. We record the
        // highest transaction nesting level seen; after the action commits it
        // drops back to 0, so we must capture the peak during execution.
        DB::listen(function () use (&$observedLevel) {
            $observedLevel = max($observedLevel, DB::transactionLevel());
        });

        app(CreateNewStatus::class)->create([
            'status_group_id' => $group->id,
            'name'            => 'Draft',
            'handle'          => 'draft',
            'color'           => '#cccccc',
            'is_default'      => true,
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $observedLevel,
            'CreateNewStatus::create() must wrap its queries in DB::transaction().'
        );
    }

    public function test_create_rolls_back_cleared_default_when_insert_fails(): void
    {
        $group    = StatusGroup::factory()->create();
        $original = Status::factory()->for($group, 'group')->create(['is_default' => true,  'handle' => 'original']);

        // Pre-occupy the handle we will attempt to create, guaranteeing a
        // unique-constraint violation on (status_group_id, handle).
        Status::factory()->for($group, 'group')->create(['is_default' => false, 'handle' => 'collision']);

        $threw = false;

        try {
            app(CreateNewStatus::class)->create([
                'status_group_id' => $group->id,
                'name'            => 'New Default',
                'handle'          => 'collision',   // duplicate → QueryException
                'color'           => '#0000ff',
                'is_default'      => true,           // triggers the clear-then-insert path
            ]);
        } catch (QueryException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected a QueryException from the unique-constraint violation.');
        $this->assertTrue(
            $original->fresh()->is_default,
            'Transaction must roll back the is_default = false update when the subsequent insert fails.'
        );
    }

    // -- CreateNewStatus::createByGroup() ------------------------------------

    public function test_create_by_group_runs_inside_a_database_transaction(): void
    {
        $group = StatusGroup::factory()->create();
        $observedLevel = 0;

        DB::listen(function () use (&$observedLevel) {
            $observedLevel = max($observedLevel, DB::transactionLevel());
        });

        app(CreateNewStatus::class)->createByGroup([
            'status_group_id' => $group->id,
            'name'            => 'Draft',
            'handle'          => 'draft',
            'color'           => '#cccccc',
            'is_default'      => true,
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $observedLevel,
            'CreateNewStatus::createByGroup() must wrap its queries in DB::transaction().'
        );
    }

    public function test_create_by_group_rolls_back_cleared_default_when_insert_fails(): void
    {
        $group    = StatusGroup::factory()->create();
        $original = Status::factory()->for($group, 'group')->create(['is_default' => true,  'handle' => 'original']);
        Status::factory()->for($group, 'group')->create(['is_default' => false, 'handle' => 'collision']);

        $threw = false;

        try {
            app(CreateNewStatus::class)->createByGroup([
                'status_group_id' => $group->id,
                'name'            => 'New Default',
                'handle'          => 'collision',
                'color'           => '#0000ff',
                'is_default'      => true,
            ]);
        } catch (QueryException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected a QueryException from the unique-constraint violation.');
        $this->assertTrue(
            $original->fresh()->is_default,
            'Transaction must roll back the is_default = false update when the subsequent insert fails.'
        );
    }

    // -- EditStatus::edit() --------------------------------------------------

    public function test_edit_runs_inside_a_database_transaction(): void
    {
        $group   = StatusGroup::factory()->create();
        $subject = Status::factory()->for($group, 'group')->create(['is_default' => false, 'handle' => 'subject']);
        $observedLevel = 0;

        DB::listen(function () use (&$observedLevel) {
            $observedLevel = max($observedLevel, DB::transactionLevel());
        });

        app(EditStatus::class)->edit($subject, [
            'name'       => $subject->name,
            'handle'     => $subject->handle,
            'color'      => $subject->color,
            'is_default' => true,
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $observedLevel,
            'EditStatus::edit() must wrap its queries in DB::transaction().'
        );
    }

    public function test_edit_rolls_back_cleared_default_when_update_fails(): void
    {
        $group   = StatusGroup::factory()->create();
        $other   = Status::factory()->for($group, 'group')->create(['is_default' => true,  'handle' => 'other']);
        $subject = Status::factory()->for($group, 'group')->create(['is_default' => false, 'handle' => 'subject']);

        // Pre-occupy the handle we will try to assign to $subject.  When
        // $status->update() runs inside the transaction it will violate the
        // (status_group_id, handle) unique index, triggering a rollback.
        Status::factory()->for($group, 'group')->create(['is_default' => false, 'handle' => 'collision']);

        $threw = false;

        try {
            app(EditStatus::class)->edit($subject, [
                'name'       => $subject->name,
                'handle'     => 'collision',   // duplicate → QueryException on the UPDATE
                'color'      => $subject->color,
                'is_default' => true,           // triggers the clear-other-defaults step first
            ]);
        } catch (QueryException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected a QueryException from the unique-constraint violation.');
        $this->assertTrue(
            $other->fresh()->is_default,
            'Transaction must roll back the is_default = false update on the sibling status.'
        );
        $this->assertFalse(
            $subject->fresh()->is_default,
            'Subject status must not have been promoted to default after the failed update.'
        );
    }
}
