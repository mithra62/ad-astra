<?php

namespace Tests\Unit\Jobs;

use AdAstra\Jobs\ProcessMediaLibraryRemoval;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessMediaLibraryRemovalTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_handle_soft_deletes_all_media_for_library(): void
    {
        Storage::fake('local');

        $library = $this->makeLibrary();
        $media1 = Media::factory()->create(['library_id' => $library->id]);
        $media2 = Media::factory()->create(['library_id' => $library->id]);

        (new ProcessMediaLibraryRemoval($library->id))->handle();

        $this->assertSoftDeleted('media', ['id' => $media1->id]);
        $this->assertSoftDeleted('media', ['id' => $media2->id]);
    }

    // -------------------------------------------------------------------------
    // handle()
    // -------------------------------------------------------------------------

    private function makeLibrary(): Library
    {
        return Library::create([
            'name' => 'Test Library',
            'handle' => 'test-lib',
            'adapter' => 'local',
        ]);
    }

    public function test_handle_does_not_touch_media_from_other_libraries(): void
    {
        Storage::fake('local');

        $libA = $this->makeLibrary();
        $libB = Library::create(['name' => 'Other Library', 'handle' => 'other', 'adapter' => 'local']);

        $mediaA = Media::factory()->create(['library_id' => $libA->id]);
        $mediaB = Media::factory()->create(['library_id' => $libB->id]);

        (new ProcessMediaLibraryRemoval($libA->id))->handle();

        $this->assertSoftDeleted('media', ['id' => $mediaA->id]);
        $this->assertNotNull(Media::find($mediaB->id), 'Media from other library should be untouched');
    }

    public function test_handle_skips_already_soft_deleted_media(): void
    {
        Storage::fake('local');

        $library = $this->makeLibrary();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $media->delete();

        $originalDeletedAt = $media->fresh()->deleted_at;

        (new ProcessMediaLibraryRemoval($library->id))->handle();

        // deleted_at should not have changed.
        $this->assertEquals(
            $originalDeletedAt->toDateTimeString(),
            Media::withTrashed()->find($media->id)->deleted_at->toDateTimeString()
        );
    }

    public function test_handle_does_not_physically_delete_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/keep.jpg', 'data');

        $library = $this->makeLibrary();
        Media::factory()->create([
            'library_id' => $library->id,
            'disk' => 'local',
            'path' => 'uploads/keep.jpg',
        ]);

        (new ProcessMediaLibraryRemoval($library->id))->handle();

        Storage::disk('local')->assertExists('uploads/keep.jpg');
    }

    public function test_handle_is_noop_when_library_has_no_media(): void
    {
        $library = $this->makeLibrary();

        // Must not throw.
        (new ProcessMediaLibraryRemoval($library->id))->handle();

        $this->assertEquals(0, Media::withTrashed()->count());
    }
}
