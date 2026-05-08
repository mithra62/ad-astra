<?php

namespace Tests\Unit\Traits;

use App\Models\Media;
use App\Models\Media\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for the HasMediaItems trait, tested through the Library model which
 * is the only concrete host (library->addMediaFromUpload, removeMedia, etc.).
 */
class HasMediaItemsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLibrary(array $overrides = []): Library
    {
        return Library::create(array_merge([
            'name'          => 'Test Library',
            'handle'        => 'test-lib',
            'adapter'       => 'local',
            'allowed_types' => null,
            'max_size'      => 10,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // validateUpload
    // -------------------------------------------------------------------------

    public function test_validate_upload_returns_empty_array_for_valid_file(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(100); // 100 KB

        $errors = $library->validateUpload($file);

        $this->assertEmpty($errors);
    }

    public function test_validate_upload_returns_error_when_file_exceeds_max_size(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary(['max_size' => 1]); // 1 MB limit
        $file    = UploadedFile::fake()->image('big.jpg')->size(2 * 1024); // 2 MB

        $errors = $library->validateUpload($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('1 MB', $errors[0]);
    }

    public function test_validate_upload_returns_error_when_mime_type_not_allowed(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary(['allowed_types' => ['image/jpeg', 'image/png']]);
        $file    = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $errors = $library->validateUpload($file);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('application/pdf', $errors[0]);
    }

    public function test_validate_upload_passes_when_allowed_types_is_null(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary(['allowed_types' => null]);
        $file    = UploadedFile::fake()->create('anything.pdf', 100, 'application/pdf');

        $errors = $library->validateUpload($file);

        $this->assertEmpty($errors);
    }

    public function test_validate_upload_passes_when_max_size_is_zero(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary(['max_size' => 0]);
        $file    = UploadedFile::fake()->image('large.jpg')->size(999 * 1024);

        $errors = $library->validateUpload($file);

        $this->assertEmpty($errors);
    }

    // -------------------------------------------------------------------------
    // addMediaFromUpload
    // -------------------------------------------------------------------------

    public function test_add_media_from_upload_creates_media_record(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $media = $library->addMediaFromUpload($file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertNotNull($media->id);
        $this->assertEquals($library->id, $media->library_id);
    }

    public function test_add_media_from_upload_stores_file_on_disk(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('shot.png');

        $media = $library->addMediaFromUpload($file);

        Storage::disk('local')->assertExists($media->path);
    }

    public function test_add_media_from_upload_records_original_name(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('my-photo.jpg');

        $media = $library->addMediaFromUpload($file);

        $this->assertEquals('my-photo.jpg', $media->original_name);
    }

    public function test_add_media_from_upload_records_mime_type(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('shot.png')->mimeType('image/png');

        $media = $library->addMediaFromUpload($file);

        $this->assertNotEmpty($media->mime_type);
    }

    public function test_add_media_from_upload_assigns_incrementing_sort_order(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();

        $first  = $library->addMediaFromUpload(UploadedFile::fake()->image('a.jpg'));
        $second = $library->addMediaFromUpload(UploadedFile::fake()->image('b.jpg'));

        $this->assertGreaterThan($first->sort_order, $second->sort_order);
    }

    public function test_add_media_from_upload_throws_when_mime_not_allowed(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary(['allowed_types' => ['image/jpeg']]);
        $file    = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->expectException(\InvalidArgumentException::class);

        $library->addMediaFromUpload($file);
    }

    public function test_add_media_from_upload_accepts_extra_attributes(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('hero.jpg');

        $media = $library->addMediaFromUpload($file, ['alt_text' => 'Hero image', 'title' => 'Hero']);

        $this->assertEquals('Hero image', $media->alt_text);
        $this->assertEquals('Hero', $media->title);
    }

    // -------------------------------------------------------------------------
    // removeMedia (soft delete)
    // -------------------------------------------------------------------------

    public function test_remove_media_soft_deletes_the_record(): void
    {
        Storage::fake('local');
        $library = $this->makeLibrary();
        $media   = Media::factory()->create(['library_id' => $library->id]);

        $library->removeMedia($media);

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    public function test_remove_media_does_not_delete_physical_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/keep.jpg', 'data');

        $library = $this->makeLibrary();
        $media   = Media::factory()->create([
            'library_id' => $library->id,
            'disk'       => 'local',
            'path'       => 'uploads/keep.jpg',
        ]);

        $library->removeMedia($media);

        Storage::disk('local')->assertExists('uploads/keep.jpg');
    }

    // -------------------------------------------------------------------------
    // purgeMedia (hard delete)
    // -------------------------------------------------------------------------

    public function test_purge_media_force_deletes_the_record(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/purge.jpg', 'data');

        $library = $this->makeLibrary();
        $media   = Media::factory()->create([
            'library_id' => $library->id,
            'disk'       => 'local',
            'path'       => 'uploads/purge.jpg',
        ]);

        $library->purgeMedia($media);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        $this->assertNull(Media::withTrashed()->find($media->id));
    }

    public function test_purge_media_deletes_physical_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/gone.jpg', 'content');

        $library = $this->makeLibrary();
        $media   = Media::factory()->create([
            'library_id' => $library->id,
            'disk'       => 'local',
            'path'       => 'uploads/gone.jpg',
        ]);

        $library->purgeMedia($media);

        Storage::disk('local')->assertMissing('uploads/gone.jpg');
    }
}
