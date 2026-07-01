<?php

namespace Tests\Unit\Jobs;

use AdAstra\Jobs\PurgeDeletedMedia;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Transformation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeDeletedMediaTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function softDeletedMedia(int $daysAgo, string $path = 'uploads/photo.jpg'): Media
    {
        $media = Media::factory()->create([
            'disk' => 'local',
            'path' => $path,
        ]);
        $media->delete();

        // Move deleted_at back in time via raw DB to avoid touching updated_at.
        DB::table('media')
            ->where('id', $media->id)
            ->update(['deleted_at' => now()->subDays($daysAgo)]);

        return $media;
    }

    // -------------------------------------------------------------------------
    // Grace period
    // -------------------------------------------------------------------------

    public function test_media_within_grace_period_is_not_purged(): void
    {
        Storage::fake('local');

        $media = $this->softDeletedMedia(daysAgo: 10); // inside 30-day grace

        (new PurgeDeletedMedia(graceDays: 30))->handle();

        $this->assertNotNull(Media::withTrashed()->find($media->id));
    }

    public function test_media_past_grace_period_is_force_deleted(): void
    {
        Storage::fake('local');

        $media = $this->softDeletedMedia(daysAgo: 31); // past 30-day grace

        (new PurgeDeletedMedia(graceDays: 30))->handle();

        $this->assertNull(Media::withTrashed()->find($media->id));
    }

    public function test_non_deleted_media_is_never_touched(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/keep.jpg', 'data');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/keep.jpg']);

        (new PurgeDeletedMedia(graceDays: 30))->handle();

        $this->assertNotNull(Media::find($media->id));
    }

    // -------------------------------------------------------------------------
    // Physical file removal
    // -------------------------------------------------------------------------

    public function test_purge_deletes_physical_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/old.jpg', 'stale-data');

        $media = $this->softDeletedMedia(daysAgo: 31, path: 'uploads/old.jpg');

        (new PurgeDeletedMedia(graceDays: 30))->handle();

        Storage::disk('local')->assertMissing('uploads/old.jpg');
    }

    public function test_purge_tolerates_missing_physical_file(): void
    {
        Storage::fake('local');

        // File was never written — should not throw.
        $media = $this->softDeletedMedia(daysAgo: 31, path: 'uploads/ghost.jpg');

        (new PurgeDeletedMedia(graceDays: 30))->handle();

        $this->assertNull(Media::withTrashed()->find($media->id));
    }

    // -------------------------------------------------------------------------
    // Transformation cleanup
    // -------------------------------------------------------------------------

    public function test_purge_removes_transformation_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/_t/photo_thumb.jpg', 'thumb-data');
        Storage::disk('local')->put('uploads/photo.jpg', 'main-data');

        $media = $this->softDeletedMedia(daysAgo: 31, path: 'uploads/photo.jpg');

        Transformation::create([
            'media_id' => $media->id,
            'key'      => 'thumb',
            'disk'     => 'local',
            'path'     => 'uploads/_t/photo_thumb.jpg',
            'status'   => 'complete',
        ]);

        (new PurgeDeletedMedia(graceDays: 30))->handle();

        Storage::disk('local')->assertMissing('uploads/_t/photo_thumb.jpg');
    }

    // -------------------------------------------------------------------------
    // Custom grace period
    // -------------------------------------------------------------------------

    public function test_custom_grace_days_is_respected(): void
    {
        Storage::fake('local');

        $media = $this->softDeletedMedia(daysAgo: 5);

        // Grace period is 3 days, so 5 days old should be purged.
        (new PurgeDeletedMedia(graceDays: 3))->handle();

        $this->assertNull(Media::withTrashed()->find($media->id));
    }
}
