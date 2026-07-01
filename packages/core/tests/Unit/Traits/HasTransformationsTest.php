<?php

namespace Tests\Unit\Traits;

use AdAstra\Models\Media;
use AdAstra\Models\Media\Transformation;
use AdAstra\Services\Media\NullTransformationDriver;
use AdAstra\Services\Media\TransformationDriverInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for the HasTransformations trait, tested through the Media model.
 */
class HasTransformationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Isolate trait tests from whichever driver is configured in production.
        // NullTransformationDriver marks dispatched transformations as 'failed',
        // so tests that need a complete status must set it directly on the record.
        $this->app->bind(TransformationDriverInterface::class, NullTransformationDriver::class);
    }

    // -------------------------------------------------------------------------
    // getTransformation / transformation
    // -------------------------------------------------------------------------

    public function test_get_transformation_returns_null_when_not_found(): void
    {
        $media = Media::factory()->create();

        $this->assertNull($media->getTransformation('thumb'));
    }

    public function test_get_transformation_returns_record_regardless_of_status(): void
    {
        $media = Media::factory()->create();
        Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'test/_t/img_thumb.jpg',
            'status' => 'pending',
        ]);

        $this->assertNotNull($media->getTransformation('thumb'));
    }

    public function test_transformation_returns_null_when_not_complete(): void
    {
        $media = Media::factory()->create();
        Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'test/_t/img_thumb.jpg',
            'status' => 'pending',
        ]);

        $this->assertNull($media->transformation('thumb'));
    }

    public function test_transformation_returns_record_when_complete(): void
    {
        $media = Media::factory()->create();
        Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'test/_t/img_thumb.jpg',
            'status' => 'complete',
        ]);

        $this->assertNotNull($media->transformation('thumb'));
    }

    // -------------------------------------------------------------------------
    // hasTransformation
    // -------------------------------------------------------------------------

    public function test_has_transformation_returns_false_when_missing(): void
    {
        $media = Media::factory()->create();

        $this->assertFalse($media->hasTransformation('thumb'));
    }

    public function test_has_transformation_returns_false_when_pending(): void
    {
        $media = Media::factory()->create();
        Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'test/_t/img_thumb.jpg',
            'status' => 'pending',
        ]);

        $this->assertFalse($media->hasTransformation('thumb'));
    }

    public function test_has_transformation_returns_true_when_complete(): void
    {
        $media = Media::factory()->create();
        Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'test/_t/img_thumb.jpg',
            'status' => 'complete',
        ]);

        $this->assertTrue($media->hasTransformation('thumb'));
    }

    // -------------------------------------------------------------------------
    // transform
    // -------------------------------------------------------------------------

    public function test_transform_creates_transformation_record(): void
    {
        $media = Media::factory()->create();

        $media->transform('thumb');

        $this->assertDatabaseHas('media_transformations', [
            'media_id' => $media->id,
            'key' => 'thumb',
        ]);
    }

    public function test_transform_returns_existing_complete_transformation_without_redispatch(): void
    {
        $media = Media::factory()->create();
        $existing = Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'test/_t/img_thumb.jpg',
            'status' => 'complete',
        ]);

        $result = $media->transform('thumb');

        $this->assertEquals($existing->id, $result->id);
        // Only one record should exist.
        $this->assertCount(1, Transformation::where('media_id', $media->id)->get());
    }

    public function test_transform_resets_failed_record_with_current_params(): void
    {
        $media = Media::factory()->create([
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
            'file_name' => 'photo.jpg',
        ]);

        // Simulate a previously failed attempt with stale params.
        Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'uploads/_t/photo_thumb_old.webp',  // stale
            'params' => ['format' => 'webp'],               // stale
            'status' => 'failed',
        ]);

        $result = $media->transform('thumb', ['format' => 'jpg']);

        $result->refresh();
        // NullTransformationDriver marks synchronously as failed, but path and params
        // must have been updated to the current call's values before dispatch.
        $this->assertSame(['format' => 'jpg'], $result->params);
        $this->assertStringEndsWith('.jpg', $result->path);
        $this->assertStringNotContainsString('_old', $result->path, 'Stale path must be replaced.');
        // Must not create a second record.
        $this->assertCount(1, Transformation::where('media_id', $media->id)->get());
    }

    public function test_transform_returns_pending_record_without_creating_duplicate(): void
    {
        $media = Media::factory()->create([
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
            'file_name' => 'photo.jpg',
        ]);

        Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'uploads/_t/photo_thumb.jpg',
            'status' => 'pending',
        ]);

        $media->transform('thumb');

        $this->assertCount(1, Transformation::where('media_id', $media->id)->get());
    }

    // -------------------------------------------------------------------------
    // clearTransformation
    // -------------------------------------------------------------------------

    public function test_clear_transformation_deletes_record(): void
    {
        Storage::fake('local');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/img.jpg']);
        Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'uploads/_t/img_thumb.jpg',
            'status' => 'complete',
        ]);

        $media->clearTransformation('thumb');

        $this->assertDatabaseMissing('media_transformations', ['media_id' => $media->id, 'key' => 'thumb']);
    }

    public function test_clear_transformation_is_noop_when_not_found(): void
    {
        $media = Media::factory()->create();

        // Must not throw.
        $media->clearTransformation('nonexistent');

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // clearTransformations
    // -------------------------------------------------------------------------

    public function test_clear_transformations_removes_all_records(): void
    {
        Storage::fake('local');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/img.jpg']);

        foreach (['thumb', 'medium', 'large'] as $key) {
            Transformation::create([
                'media_id' => $media->id,
                'key' => $key,
                'disk' => 'local',
                'path' => "uploads/_t/img_{$key}.jpg",
                'status' => 'complete',
            ]);
        }

        $media->clearTransformations();

        $this->assertCount(0, Transformation::where('media_id', $media->id)->get());
    }

    // -------------------------------------------------------------------------
    // derivedPath — tested indirectly through transform(), which is its only
    // caller. derivedPath() is protected and not part of the public API.
    // -------------------------------------------------------------------------

    public function test_transform_stores_derived_path_in_t_subdirectory(): void
    {
        $media = Media::factory()->create([
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
            'file_name' => 'photo.jpg',
        ]);

        $transformation = $media->transform('thumb');

        $this->assertStringContainsString('/_t/', $transformation->path);
        $this->assertStringContainsString('_thumb', $transformation->path);
    }

    public function test_transform_respects_format_param_in_derived_path(): void
    {
        $media = Media::factory()->create([
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
            'file_name' => 'photo.jpg',
        ]);

        $transformation = $media->transform('thumb', ['format' => 'webp']);

        $this->assertStringEndsWith('.webp', $transformation->path);
    }
}
