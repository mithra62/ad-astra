<?php

namespace Tests\Unit\Actions\Media;

use AdAstra\Actions\Media\EditMedia;
use AdAstra\Field\Types\Text;
use AdAstra\Models\Category;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditMediaActionTest extends TestCase
{
    use RefreshDatabase;

    private EditMedia $action;

    public function test_edit_updates_name(): void
    {
        $media = Media::factory()->create(['name' => 'Old Name']);

        $result = $this->action->edit($media, ['name' => 'New Name']);

        $this->assertEquals('New Name', $result->name);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'name' => 'New Name']);
    }

    // -------------------------------------------------------------------------
    // Core attributes
    // -------------------------------------------------------------------------

    public function test_edit_updates_sort_order(): void
    {
        $media = Media::factory()->create(['sort_order' => 0]);

        $result = $this->action->edit($media, ['sort_order' => 5]);

        $this->assertEquals(5, $result->sort_order);
    }

    public function test_edit_returns_refreshed_media_instance(): void
    {
        $media = Media::factory()->create(['name' => 'Before']);

        $result = $this->action->edit($media, ['name' => 'After']);

        $this->assertInstanceOf(Media::class, $result);
        $this->assertEquals('After', $result->name);
    }

    public function test_edit_syncs_categories(): void
    {
        $media = Media::factory()->create();
        $category = Category::factory()->create();

        $this->action->edit($media, ['categories' => [$category->id]]);

        $this->assertDatabaseHas('categorizables', [
            'categorizable_type' => $media->getMorphClass(),
            'categorizable_id' => $media->id,
            'category_id' => $category->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Categories
    // -------------------------------------------------------------------------

    public function test_edit_detaches_categories_when_passed_empty_array(): void
    {
        $media = Media::factory()->create();
        $category = Category::factory()->create();
        $media->categories()->attach($category->id);

        $this->action->edit($media, ['categories' => []]);

        $this->assertCount(0, $media->fresh()->categories);
    }

    public function test_edit_persists_field_values_for_library_layout(): void
    {
        // Build: library → field layout → tab → element → text field
        $library = Library::factory()->create();
        $layout = FieldLayout::create(['name' => 'Media Test Layout', 'handle' => 'media-test-layout']);
        $library->update(['field_layout_id' => $layout->id]);

        $textType = FieldType::firstOrCreate(
            ['object' => Text::class],
            ['name' => 'Text', 'settings' => []]
        );
        $field = Field::factory()->create([
            'handle' => 'caption',
            'field_type_id' => $textType->id,
        ]);

        $tab = Tab::create(['field_layout_id' => $layout->id, 'name' => 'Main', 'handle' => 'main', 'sort_order' => 0]);
        $element = TabElement::create(['field_layout_tab_id' => $tab->id, 'field_id' => $field->id, 'sort_order' => 0]);

        $media = Media::factory()->create(['library_id' => $library->id]);

        $this->action->edit($media, ['fields' => ['caption' => 'A nice caption']]);

        $this->assertDatabaseHas('field_values', [
            'fieldable_type' => $media->getMorphClass(),
            'fieldable_id' => $media->id,
            'field_id' => $field->id,
            'value_text' => 'A nice caption',
        ]);
    }

    // -------------------------------------------------------------------------
    // Dynamic field values (full stack through library field layout)
    // -------------------------------------------------------------------------

    public function test_edit_leaves_field_values_untouched_when_fields_key_absent(): void
    {
        $media = Media::factory()->create();

        // Must not throw even when no library or layout exists.
        $result = $this->action->edit($media, ['name' => 'Renamed']);

        $this->assertEquals('Renamed', $result->name);
        $this->assertDatabaseEmpty('field_values');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new EditMedia();
    }
}
