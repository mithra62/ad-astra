<?php

namespace Tests\Unit\Actions\Field\Group;

use AdAstra\Actions\Field\Group\CreateNewFieldGroup;
use AdAstra\Actions\Field\Group\EditFieldGroup;
use AdAstra\Models\Field\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldGroupActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CreateNewFieldGroup
    // -------------------------------------------------------------------------

    public function test_create_returns_group_instance(): void
    {
        $action = app(CreateNewFieldGroup::class);

        $result = $action->create(['name' => 'Core Fields', 'handle' => 'core-fields']);

        $this->assertInstanceOf(Group::class, $result);
    }

    public function test_create_persists_group_to_database(): void
    {
        $action = app(CreateNewFieldGroup::class);

        $action->create([
            'name' => 'SEO Fields',
            'handle' => 'seo-fields',
            'description' => 'Search engine optimisation fields',
        ]);

        $this->assertDatabaseHas('field_groups', [
            'name' => 'SEO Fields',
            'handle' => 'seo-fields',
            'description' => 'Search engine optimisation fields',
        ]);
    }

    public function test_create_stores_name_and_handle(): void
    {
        $action = app(CreateNewFieldGroup::class);

        $group = $action->create(['name' => 'Media Fields', 'handle' => 'media-fields']);

        $this->assertEquals('Media Fields', $group->name);
        $this->assertEquals('media-fields', $group->handle);
    }

    public function test_create_allows_optional_description(): void
    {
        $action = app(CreateNewFieldGroup::class);

        $group = $action->create(['name' => 'No Desc', 'handle' => 'no-desc']);

        $this->assertNull($group->description);
    }

    // -------------------------------------------------------------------------
    // EditFieldGroup
    // -------------------------------------------------------------------------

    public function test_edit_returns_true_on_success(): void
    {
        $group = Group::factory()->create(['name' => 'Old']);
        $action = app(EditFieldGroup::class);

        $result = $action->edit($group, ['name' => 'New', 'handle' => 'new']);

        $this->assertTrue($result);
    }

    public function test_edit_updates_name_and_handle(): void
    {
        $group = Group::factory()->create(['name' => 'Old Name', 'handle' => 'old-handle']);
        $action = app(EditFieldGroup::class);

        $action->edit($group, ['name' => 'Updated Name', 'handle' => 'updated-handle']);

        $this->assertDatabaseHas('field_groups', [
            'id' => $group->id,
            'name' => 'Updated Name',
            'handle' => 'updated-handle',
        ]);
    }

    public function test_edit_updates_description(): void
    {
        $group = Group::factory()->create(['description' => 'Old description']);
        $action = app(EditFieldGroup::class);

        $action->edit($group, [
            'name' => $group->name,
            'handle' => $group->handle,
            'description' => 'New description',
        ]);

        $this->assertDatabaseHas('field_groups', [
            'id' => $group->id,
            'description' => 'New description',
        ]);
    }

    public function test_edit_persists_changes_to_the_model(): void
    {
        $group = Group::factory()->create(['name' => 'Before']);
        $action = app(EditFieldGroup::class);

        $action->edit($group, ['name' => 'After', 'handle' => 'after']);

        $this->assertEquals('After', $group->fresh()->name);
    }
}
