<?php

namespace Tests\Unit\Observers;

use AdAstra\Field\Types\FileUpload;
use AdAstra\Field\Types\Media as MediaField;
use AdAstra\Field\Types\Text;
use AdAstra\Models\Entry;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Type as FieldType;
use AdAstra\Models\FieldValue;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for FieldValueObserver — verifies that saving/deleting a FileUpload
 * FieldValue keeps the mediables pivot table in sync.
 */
class FieldValueObserverTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_saving_file_upload_field_value_creates_mediable_rows(): void
    {
        $field = $this->makeFileUploadField();
        $library = $this->makeLibrary();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $entry = $this->makeEntry();
        $type = $entry->getMorphClass(); // 'entry' via morphMap

        FieldValue::create([
            'fieldable_type' => $type,
            'fieldable_id' => $entry->id,
            'field_id' => $field->id,
            'value_json' => json_encode([$media->id]),
        ]);

        $this->assertDatabaseHas('mediables', [
            'media_id' => $media->id,
            'mediable_type' => $type,
            'mediable_id' => $entry->id,
            'field_id' => $field->id,
        ]);
    }

    private function makeFileUploadField(): Field
    {
        $fieldType = FieldType::firstOrCreate(
            ['object' => FileUpload::class],
            ['name' => 'File Upload', 'object' => FileUpload::class]
        );

        return Field::factory()->create(['field_type_id' => $fieldType->id]);
    }

    private function makeLibrary(): Library
    {
        return Library::create(['name' => 'Test Library', 'handle' => 'test-lib', 'adapter' => 'local']);
    }

    private function makeEntry(): Entry
    {
        return Entry::factory()->create();
    }

    // -------------------------------------------------------------------------
    // saved() — syncs mediables
    // -------------------------------------------------------------------------

    public function test_saving_with_updated_ids_removes_stale_rows(): void
    {
        $field = $this->makeFileUploadField();
        $library = $this->makeLibrary();
        $mediaA = Media::factory()->create(['library_id' => $library->id]);
        $mediaB = Media::factory()->create(['library_id' => $library->id]);
        $entry = $this->makeEntry();

        $fieldValue = FieldValue::create([
            'fieldable_type' => $entry->getMorphClass(),
            'fieldable_id' => $entry->id,
            'field_id' => $field->id,
            'value_json' => json_encode([$mediaA->id]),
        ]);

        // Update to B only — A should be removed.
        $fieldValue->update(['value_json' => json_encode([$mediaB->id])]);

        $this->assertDatabaseMissing('mediables', [
            'media_id' => $mediaA->id,
            'field_id' => $field->id,
        ]);
        $this->assertDatabaseHas('mediables', [
            'media_id' => $mediaB->id,
            'field_id' => $field->id,
        ]);
    }

    public function test_saving_with_empty_ids_clears_all_mediable_rows(): void
    {
        $field = $this->makeFileUploadField();
        $library = $this->makeLibrary();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $entry = $this->makeEntry();

        $fieldValue = FieldValue::create([
            'fieldable_type' => $entry->getMorphClass(),
            'fieldable_id' => $entry->id,
            'field_id' => $field->id,
            'value_json' => json_encode([$media->id]),
        ]);

        $fieldValue->update(['value_json' => json_encode([])]);

        $this->assertDatabaseMissing('mediables', [
            'mediable_type' => $entry->getMorphClass(),
            'mediable_id' => $entry->id,
            'field_id' => $field->id,
        ]);
    }

    public function test_saving_non_file_upload_field_does_not_touch_mediables(): void
    {
        $textType = FieldType::firstOrCreate(['object' => Text::class], ['name' => 'Text', 'object' => Text::class]);
        $field = Field::factory()->create(['field_type_id' => $textType->id]);
        $entry = $this->makeEntry();

        FieldValue::create([
            'fieldable_type' => $entry->getMorphClass(),
            'fieldable_id' => $entry->id,
            'field_id' => $field->id,
            'value_text' => 'hello',
        ]);

        $this->assertEquals(0, DB::table('mediables')->count());
    }

    // -------------------------------------------------------------------------
    // saved() — non-FileUpload fields are ignored
    // -------------------------------------------------------------------------

    public function test_deleting_file_upload_field_value_removes_mediable_rows(): void
    {
        $field = $this->makeFileUploadField();
        $library = $this->makeLibrary();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $entry = $this->makeEntry();

        $fieldValue = FieldValue::create([
            'fieldable_type' => $entry->getMorphClass(),
            'fieldable_id' => $entry->id,
            'field_id' => $field->id,
            'value_json' => json_encode([$media->id]),
        ]);

        $this->assertDatabaseHas('mediables', ['media_id' => $media->id]);

        $fieldValue->delete();

        $this->assertDatabaseMissing('mediables', [
            'media_id' => $media->id,
            'field_id' => $field->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // deleted() — clears mediable rows for this field
    // -------------------------------------------------------------------------

    public function test_saving_media_field_value_creates_mediable_rows(): void
    {
        $field = $this->makeMediaField();
        $library = $this->makeLibrary();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $entry = $this->makeEntry();

        FieldValue::create([
            'fieldable_type' => $entry->getMorphClass(),
            'fieldable_id' => $entry->id,
            'field_id' => $field->id,
            'value_json' => json_encode([$media->id]),
        ]);

        $this->assertDatabaseHas('mediables', [
            'media_id' => $media->id,
            'mediable_type' => $entry->getMorphClass(),
            'mediable_id' => $entry->id,
            'field_id' => $field->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Media field type — same observer behavior via SyncsToMediables interface
    // -------------------------------------------------------------------------

    private function makeMediaField(): Field
    {
        $fieldType = FieldType::firstOrCreate(
            ['object' => MediaField::class],
            ['name' => 'Media', 'object' => MediaField::class]
        );

        return Field::factory()->create(['field_type_id' => $fieldType->id]);
    }

    public function test_deleting_media_field_value_removes_mediable_rows(): void
    {
        $field = $this->makeMediaField();
        $library = $this->makeLibrary();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $entry = $this->makeEntry();

        $fieldValue = FieldValue::create([
            'fieldable_type' => $entry->getMorphClass(),
            'fieldable_id' => $entry->id,
            'field_id' => $field->id,
            'value_json' => json_encode([$media->id]),
        ]);

        $this->assertDatabaseHas('mediables', ['media_id' => $media->id]);

        $fieldValue->delete();

        $this->assertDatabaseMissing('mediables', [
            'media_id' => $media->id,
            'field_id' => $field->id,
        ]);
    }

    public function test_media_field_value_preserves_sort_order_across_mixed_libraries(): void
    {
        $field = $this->makeMediaField();
        $libA = Library::create(['name' => 'A', 'handle' => 'a', 'adapter' => 'local']);
        $libB = Library::create(['name' => 'B', 'handle' => 'b', 'adapter' => 'local']);
        $mA = Media::factory()->create(['library_id' => $libA->id]);
        $mB = Media::factory()->create(['library_id' => $libB->id]);
        $entry = $this->makeEntry();
        $ids = [$mB->id, $mA->id]; // intentional order

        FieldValue::create([
            'fieldable_type' => $entry->getMorphClass(),
            'fieldable_id' => $entry->id,
            'field_id' => $field->id,
            'value_json' => json_encode($ids),
        ]);

        foreach ($ids as $sortOrder => $mediaId) {
            $row = DB::table('mediables')
                ->where('media_id', $mediaId)
                ->where('field_id', $field->id)
                ->first();

            $this->assertEquals($sortOrder, $row->sort_order);
        }
    }

    public function test_upsert_preserves_sort_order(): void
    {
        $field = $this->makeFileUploadField();
        $library = $this->makeLibrary();
        $media = Media::factory()->count(3)->create(['library_id' => $library->id]);
        $entry = $this->makeEntry();
        $ids = $media->pluck('id')->all();

        FieldValue::create([
            'fieldable_type' => $entry->getMorphClass(),
            'fieldable_id' => $entry->id,
            'field_id' => $field->id,
            'value_json' => json_encode($ids),
        ]);

        foreach (array_values($ids) as $sortOrder => $mediaId) {
            $row = DB::table('mediables')
                ->where('media_id', $mediaId)
                ->where('field_id', $field->id)
                ->first();

            $this->assertEquals($sortOrder, $row->sort_order, "Wrong sort_order for media_id {$mediaId}");
        }
    }
}
