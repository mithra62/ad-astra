<?php

namespace Tests\Unit\Services;

use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Services\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function service(): MediaStorageService
    {
        return app('media-service');
    }

    private function makeLibrary(array $overrides = []): Library
    {
        return Library::create(array_merge([
            'name'    => 'Test Library',
            'handle'  => 'test-lib',
            'adapter' => 'local',
            'max_size' => 10,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // upload()
    // -------------------------------------------------------------------------

    public function test_upload_creates_media_record(): void
    {
        Storage::fake('local');

        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('photo.jpg');

        $media = $this->service()->upload($library, $file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_upload_stores_file_on_disk(): void
    {
        Storage::fake('local');

        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('shot.png');

        $media = $this->service()->upload($library, $file);

        Storage::disk('local')->assertExists($media->path);
    }

    public function test_upload_passes_extra_attributes(): void
    {
        Storage::fake('local');

        $library = $this->makeLibrary();
        $file    = UploadedFile::fake()->image('hero.jpg');

        $media = $this->service()->upload($library, $file, ['name' => 'Hero Shot']);

        $this->assertEquals('Hero Shot', $media->name);
    }

    // -------------------------------------------------------------------------
    // delete() — soft delete
    // -------------------------------------------------------------------------

    public function test_delete_soft_deletes_record(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/soft.jpg', 'data');

        $library = $this->makeLibrary();
        $media   = Media::factory()->create(['library_id' => $library->id, 'disk' => 'local', 'path' => 'uploads/soft.jpg']);

        $this->service()->delete($media);

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    public function test_delete_preserves_physical_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/soft.jpg', 'data');

        $library = $this->makeLibrary();
        $media   = Media::factory()->create(['library_id' => $library->id, 'disk' => 'local', 'path' => 'uploads/soft.jpg']);

        $this->service()->delete($media);

        Storage::disk('local')->assertExists('uploads/soft.jpg');
    }

    public function test_delete_works_when_library_id_is_null(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/orphan.jpg', 'data');

        $media = Media::factory()->create(['library_id' => null, 'disk' => 'local', 'path' => 'uploads/orphan.jpg']);

        $this->service()->delete($media);

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    // -------------------------------------------------------------------------
    // purge() — hard delete
    // -------------------------------------------------------------------------

    public function test_purge_force_deletes_record(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/hard.jpg', 'data');

        $library = $this->makeLibrary();
        $media   = Media::factory()->create(['library_id' => $library->id, 'disk' => 'local', 'path' => 'uploads/hard.jpg']);

        $this->service()->purge($media);

        $this->assertNull(Media::withTrashed()->find($media->id));
    }

    public function test_purge_removes_physical_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/hard.jpg', 'data');

        $library = $this->makeLibrary();
        $media   = Media::factory()->create(['library_id' => $library->id, 'disk' => 'local', 'path' => 'uploads/hard.jpg']);

        $this->service()->purge($media);

        Storage::disk('local')->assertMissing('uploads/hard.jpg');
    }

    public function test_purge_works_when_library_id_is_null(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/orphan.jpg', 'data');

        $media = Media::factory()->create(['library_id' => null, 'disk' => 'local', 'path' => 'uploads/orphan.jpg']);

        $this->service()->purge($media);

        $this->assertNull(Media::withTrashed()->find($media->id));
        Storage::disk('local')->assertMissing('uploads/orphan.jpg');
    }

    // -------------------------------------------------------------------------
    // url()
    // -------------------------------------------------------------------------

    public function test_url_returns_string(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/photo.jpg', 'data');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/photo.jpg']);

        $url = $this->service()->url($media);

        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }

    // -------------------------------------------------------------------------
    // disk()
    // -------------------------------------------------------------------------

    public function test_disk_returns_filesystem_instance(): void
    {
        Storage::fake('local');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/x.jpg']);

        $disk = $this->service()->disk($media);

        $this->assertInstanceOf(\Illuminate\Contracts\Filesystem\Filesystem::class, $disk);
    }
}
