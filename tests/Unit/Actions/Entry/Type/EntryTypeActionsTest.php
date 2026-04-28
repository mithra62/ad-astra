<?php

namespace Tests\Unit\Actions\Entry\Type;

use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\FieldLayout;
use App\Services\EntryTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $result = app(EntryTypeService::class)->create($group->id, [
            'name'   => 'Blog Post',
            'handle' => 'blog-post',
            'class'  => 'App\\EntryTypes\\BlogPostEntryType',
        ]);

        $this->assertInstanceOf(EntryType::class, $result);
    }

    public function test_create_persists_entry_type_to_database(): void
    {
        $group = EntryGroup::factory()->create();

        app(EntryTypeService::class)->create($group->id, [
            'name'   => 'Page',
            'handle' => 'page',
            'class'  => 'App\\EntryTypes\\PageEntryType',
        ]);

        $this->assertDatabaseHas('entry_types', [
            'entry_group_id' => $group->id,
            'name'           => 'Page',
            'handle'         => 'page',
            'class'          => 'App\\EntryTypes\\PageEntryType',
        ]);
    }

    public function test_create_assigns_correct_entry_group(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create($group->id, [
            'name'   => 'News',
            'handle' => 'news',
            'class'  => 'App\\EntryTypes\\NewsArticleEntryType',
        ]);

        $this->assertEquals($group->id, $result->entry_group_id);
    }

    public function test_create_accepts_entry_group_model_directly(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create($group, [
            'name'   => 'Video',
            'handle' => 'video',
            'class'  => 'App\\EntryTypes\\VideoEntryType',
        ]);

        $this->assertEquals($group->id, $result->entry_group_id);
    }

    public function test_create_stores_sort_order(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create($group->id, [
            'name'       => 'Event',
            'handle'     => 'event',
            'class'      => 'App\\EntryTypes\\EventEntryType',
            'sort_order' => 4,
        ]);

        $this->assertEquals(4, $result->sort_order);
    }

    public function test_create_defaults_sort_order_to_zero(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create($group->id, [
            'name'   => 'Job',
            'handle' => 'job',
            'class'  => 'App\\EntryTypes\\JobListingEntryType',
        ]);

        $this->assertEquals(0, $result->sort_order);
    }

    public function test_create_stores_field_layout_id_when_provided(): void
    {
        $group  = EntryGroup::factory()->create();
        $layout = FieldLayout::factory()->create();

        $result = app(EntryTypeService::class)->create($group->id, [
            'name'            => 'Product',
            'handle'          => 'product',
            'class'           => 'App\\EntryTypes\\ProductEntryType',
            'field_layout_id' => $layout->id,
        ]);

        $this->assertEquals($layout->id, $result->field_layout_id);
    }

    public function test_create_allows_null_field_layout_id(): void
    {
        $group = EntryGroup::factory()->create();

        $result = app(EntryTypeService::class)->create($group->id, [
            'name'   => 'Podcast',
            'handle' => 'podcast',
            'class'  => 'App\\EntryTypes\\PodcastEpisodeEntryType',
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
            'name'   => 'Updated',
            'handle' => 'updated',
            'class'  => $type->class,
        ]);

        $this->assertInstanceOf(EntryType::class, $result);
    }

    public function test_edit_updates_name_handle_and_class(): void
    {
        $type = EntryType::factory()->create(['name' => 'Old', 'handle' => 'old']);

        app(EntryTypeService::class)->update($type, [
            'name'   => 'New Name',
            'handle' => 'new-handle',
            'class'  => 'App\\EntryTypes\\PageEntryType',
        ]);

        $this->assertDatabaseHas('entry_types', [
            'id'     => $type->id,
            'name'   => 'New Name',
            'handle' => 'new-handle',
            'class'  => 'App\\EntryTypes\\PageEntryType',
        ]);
    }

    public function test_edit_updates_sort_order(): void
    {
        $type = EntryType::factory()->create(['sort_order' => 1]);

        $result = app(EntryTypeService::class)->update($type, [
            'name'       => $type->name,
            'handle'     => $type->handle,
            'class'      => $type->class,
            'sort_order' => 8,
        ]);

        $this->assertEquals(8, $result->sort_order);
    }

    public function test_edit_updates_field_layout_id(): void
    {
        $layout = FieldLayout::factory()->create();
        $type   = EntryType::factory()->create();

        $result = app(EntryTypeService::class)->update($type, [
            'name'            => $type->name,
            'handle'          => $type->handle,
            'class'           => $type->class,
            'field_layout_id' => $layout->id,
        ]);

        $this->assertEquals($layout->id, $result->field_layout_id);
    }

    public function test_edit_returns_fresh_model_not_original(): void
    {
        $type = EntryType::factory()->create(['name' => 'Before']);

        $result = app(EntryTypeService::class)->update($type, [
            'name'   => 'After',
            'handle' => 'after',
            'class'  => $type->class,
        ]);

        $this->assertNotSame($type, $result);
        $this->assertEquals('After', $result->name);
    }

    public function test_edit_defaults_sort_order_to_zero_when_omitted(): void
    {
        $type = EntryType::factory()->create(['sort_order' => 3]);

        $result = app(EntryTypeService::class)->update($type, [
            'name'   => $type->name,
            'handle' => $type->handle,
            'class'  => $type->class,
        ]);

        $this->assertEquals(0, $result->sort_order);
    }
}
