<?php

namespace Tests\Unit\Actions\Entry\Type;

use AdAstra\Actions\Entry\Type\CreateNewEntryType;
use AdAstra\Actions\Entry\Type\EditEntryType;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\FieldLayout;
use AdAstra\Services\EntryTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EntryTypeActionsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // create  (via EntryTypeService)
    // -------------------------------------------------------------------------

    public function test_create_returns_entry_type_instance(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create([
            'entry_group_id' => $group->id,
            'name' => 'Blog Post',
            'handle' => 'blog-post',
        ]);

        $this->assertInstanceOf(EntryType::class, $result);
    }

    public function test_create_persists_entry_type_to_database(): void
    {
        $group = EntryGroup::factory()->create();

        app(EntryTypeService::class)->create([
            'entry_group_id' => $group->id,
            'name' => 'Page',
            'handle' => 'page',
        ]);

        $this->assertDatabaseHas('entry_types', [
            'entry_group_id' => $group->id,
            'name' => 'Page',
            'handle' => 'page',
        ]);
    }

    public function test_create_assigns_correct_entry_group(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create([
            'entry_group_id' => $group->id,
            'name' => 'News',
            'handle' => 'news',
        ]);

        $this->assertEquals($group->id, $result->entry_group_id);
    }

    public function test_create_accepts_entry_group_model_directly(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create([
            'entry_group_id' => $group->id,
            'name' => 'Video',
            'handle' => 'video',
        ]);

        $this->assertEquals($group->id, $result->entry_group_id);
    }

    public function test_create_stores_sort_order(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create([
            'entry_group_id' => $group->id,
            'name' => 'Event',
            'handle' => 'event',
            'sort_order' => 4,
        ]);

        $this->assertEquals(4, $result->sort_order);
    }

    public function test_create_defaults_sort_order_to_zero(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create([
            'entry_group_id' => $group->id,
            'name' => 'Job',
            'handle' => 'job',
        ]);

        $this->assertEquals(0, $result->sort_order);
    }

    public function test_create_stores_field_layout_id_when_provided(): void
    {
        $group = EntryGroup::factory()->create();
        $layout = FieldLayout::factory()->create();

        $result = app(EntryTypeService::class)->create([
            'entry_group_id' => $group->id,
            'name' => 'Product',
            'handle' => 'product',
            'field_layout_id' => $layout->id,
        ]);

        $this->assertEquals($layout->id, $result->field_layout_id);
    }

    public function test_create_allows_null_field_layout_id(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create([
            'entry_group_id' => $group->id,
            'name' => 'Podcast',
            'handle' => 'podcast',
        ]);

        $this->assertNull($result->field_layout_id);
    }

    // -------------------------------------------------------------------------
    // update  (via EntryTypeService)
    // -------------------------------------------------------------------------

    public function test_edit_returns_entry_type_instance(): void
    {
        $type = EntryType::factory()->create();

        $result = app(EntryTypeService::class)->update($type, [
            'name' => 'Updated',
            'handle' => 'updated',
        ]);

        $this->assertInstanceOf(EntryType::class, $result);
    }

    public function test_edit_updates_name_and_handle(): void
    {
        $type = EntryType::factory()->create(['name' => 'Old', 'handle' => 'old']);

        app(EntryTypeService::class)->update($type, [
            'name' => 'New Name',
            'handle' => 'new-handle',
        ]);

        $this->assertDatabaseHas('entry_types', [
            'id' => $type->id,
            'name' => 'New Name',
            'handle' => 'new-handle',
        ]);
    }

    public function test_edit_updates_sort_order(): void
    {
        $type = EntryType::factory()->create(['sort_order' => 1]);

        $result = app(EntryTypeService::class)->update($type, [
            'name' => $type->name,
            'handle' => $type->handle,
            'sort_order' => 8,
        ]);

        $this->assertEquals(8, $result->sort_order);
    }

    public function test_edit_updates_field_layout_id(): void
    {
        $layout = FieldLayout::factory()->create();
        $type = EntryType::factory()->create();

        $result = app(EntryTypeService::class)->update($type, [
            'name' => $type->name,
            'handle' => $type->handle,
            'field_layout_id' => $layout->id,
        ]);

        $this->assertEquals($layout->id, $result->field_layout_id);
    }

    public function test_edit_returns_fresh_model_not_original(): void
    {
        $type = EntryType::factory()->create(['name' => 'Before']);

        $result = app(EntryTypeService::class)->update($type, [
            'name' => 'After',
            'handle' => 'after',
        ]);

        $this->assertNotSame($type, $result);
        $this->assertEquals('After', $result->name);
    }

    public function test_edit_defaults_sort_order_to_zero_when_omitted(): void
    {
        $type = EntryType::factory()->create(['sort_order' => 3]);

        $result = app(EntryTypeService::class)->update($type, [
            'name' => $type->name,
            'handle' => $type->handle,
        ]);

        $this->assertEquals(0, $result->sort_order);
    }

    // -------------------------------------------------------------------------
    // CreateNewEntryType action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_create_action_delegates_to_service_create(): void
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->create();
        $service = $this->mock(EntryTypeService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => ($data['entry_group_id'] ?? null) === $group->id
                && ($data['name'] ?? null) === 'Blog Post'
                && ($data['handle'] ?? null) === 'blog-post'))
            ->andReturn($type);

        $result = app(CreateNewEntryType::class)->create($group->id, ['name' => 'Blog Post', 'handle' => 'blog-post']);

        $this->assertSame($type, $result);
    }

    public function test_create_action_casts_string_group_id_to_integer(): void
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->create();
        $service = $this->mock(EntryTypeService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => is_int($data['entry_group_id'] ?? null)))
            ->andReturn($type);

        app(CreateNewEntryType::class)->create((string)$group->id, ['name' => 'Test', 'handle' => 'test']);
    }

    public function test_create_action_passes_correct_integer_value_for_string_group_id(): void
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->create();
        $service = $this->mock(EntryTypeService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => ($data['entry_group_id'] ?? null) === $group->id))
            ->andReturn($type);

        app(CreateNewEntryType::class)->create((string)$group->id, ['name' => 'Test', 'handle' => 'test']);
    }

    public function test_create_action_returns_entry_type_instance(): void
    {
        $group = EntryGroup::factory()->create();
        $type = EntryType::factory()->create();
        $service = $this->mock(EntryTypeService::class);
        $service->shouldReceive('create')->once()->andReturn($type);

        $result = app(CreateNewEntryType::class)->create($group->id, []);

        $this->assertInstanceOf(EntryType::class, $result);
    }

    // -------------------------------------------------------------------------
    // EditEntryType action wrapper — delegation
    // -------------------------------------------------------------------------

    public function test_edit_action_delegates_to_service_update(): void
    {
        $type = EntryType::factory()->create();
        $updated = EntryType::factory()->create();
        $service = $this->mock(EntryTypeService::class);
        $service->shouldReceive('update')
            ->once()
            ->with($type, ['name' => 'New Name', 'handle' => 'new-name'])
            ->andReturn($updated);

        $result = app(EditEntryType::class)->edit($type, ['name' => 'New Name', 'handle' => 'new-name']);

        $this->assertSame($updated, $result);
    }

    public function test_edit_action_returns_entry_type_instance(): void
    {
        $type = EntryType::factory()->create();
        $updated = EntryType::factory()->create();
        $service = $this->mock(EntryTypeService::class);
        $service->shouldReceive('update')->once()->andReturn($updated);

        $result = app(EditEntryType::class)->edit($type, []);

        $this->assertInstanceOf(EntryType::class, $result);
    }
}
