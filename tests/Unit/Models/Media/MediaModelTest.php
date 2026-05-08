<?php

namespace Tests\Unit\Models\Media;

use App\Models\Media;
use App\Models\Media\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for Media model behaviour beyond the basic relationship assertions
 * already covered in MediaTest.php.
 */
class MediaModelTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // isImage()
    // -------------------------------------------------------------------------

    public function test_is_image_returns_true_for_jpeg(): void
    {
        $media = new Media(['mime_type' => 'image/jpeg']);

        $this->assertTrue($media->isImage());
    }

    public function test_is_image_returns_true_for_png(): void
    {
        $media = new Media(['mime_type' => 'image/png']);

        $this->assertTrue($media->isImage());
    }

    public function test_is_image_returns_true_for_webp(): void
    {
        $media = new Media(['mime_type' => 'image/webp']);

        $this->assertTrue($media->isImage());
    }

    public function test_is_image_returns_false_for_pdf(): void
    {
        $media = new Media(['mime_type' => 'application/pdf']);

        $this->assertFalse($media->isImage());
    }

    public function test_is_image_returns_false_for_video(): void
    {
        $media = new Media(['mime_type' => 'video/mp4']);

        $this->assertFalse($media->isImage());
    }

    public function test_is_image_returns_false_for_null_mime_type(): void
    {
        $media = new Media(['mime_type' => null]);

        $this->assertFalse($media->isImage());
    }

    // -------------------------------------------------------------------------
    // url() / fileExists()
    // -------------------------------------------------------------------------

    public function test_url_delegates_to_storage_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/photo.jpg', 'fake');

        $media = Media::factory()->create([
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
        ]);

        $this->assertStringContainsString('photo.jpg', $media->url());
    }

    public function test_file_exists_returns_true_when_file_present(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/exists.jpg', 'data');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/exists.jpg']);

        $this->assertTrue($media->fileExists());
    }

    public function test_file_exists_returns_false_when_file_missing(): void
    {
        Storage::fake('local');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/ghost.jpg']);

        $this->assertFalse($media->fileExists());
    }

    // -------------------------------------------------------------------------
    // fieldUsages() / isReferencedByField()
    // -------------------------------------------------------------------------

    public function test_is_referenced_by_field_returns_false_when_no_mediable_rows(): void
    {
        $media = Media::factory()->create();

        $this->assertFalse($media->isReferencedByField());
    }

    public function test_is_referenced_by_field_returns_false_for_direct_attachment_only(): void
    {
        $media = Media::factory()->create();

        // Insert a direct-attachment row (field_id = 0 sentinel).
        DB::table('mediables')->insert([
            'media_id'      => $media->id,
            'mediable_type' => 'App\Models\User',
            'mediable_id'   => 1,
            'field_id'      => 0,   // sentinel — direct attachment
            'sort_order'    => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->assertFalse($media->isReferencedByField());
    }

    public function test_is_referenced_by_field_returns_true_when_field_pivot_row_exists(): void
    {
        $media = Media::factory()->create();

        // Insert a field-driven row (field_id > 0).
        DB::table('mediables')->insert([
            'media_id'      => $media->id,
            'mediable_type' => 'App\Models\Entry',
            'mediable_id'   => 1,
            'field_id'      => 99,  // some real field ID
            'sort_order'    => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->assertTrue($media->isReferencedByField());
    }

    // -------------------------------------------------------------------------
    // SoftDeletes
    // -------------------------------------------------------------------------

    public function test_soft_delete_sets_deleted_at(): void
    {
        $media = Media::factory()->create();

        $media->delete();

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    public function test_soft_deleted_media_excluded_from_default_query(): void
    {
        $media = Media::factory()->create();
        $media->delete();

        $this->assertNull(Media::find($media->id));
    }

    public function test_only_trashed_returns_soft_deleted_media(): void
    {
        $media = Media::factory()->create();
        $media->delete();

        $this->assertNotNull(Media::onlyTrashed()->find($media->id));
    }

    // -------------------------------------------------------------------------
    // Fillable
    // -------------------------------------------------------------------------

    public function test_has_correct_fillable_attributes(): void
    {
        $fillable = (new Media)->getFillable();

        foreach (['library_id', 'name', 'file_name', 'original_name', 'mime_type', 'disk', 'path', 'size', 'sort_order'] as $attr) {
            $this->assertContains($attr, $fillable, "Expected '$attr' to be fillable.");
        }
    }
}
