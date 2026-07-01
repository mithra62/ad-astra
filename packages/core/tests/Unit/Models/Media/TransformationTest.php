<?php

namespace Tests\Unit\Models\Media;

use AdAstra\Models\Media;
use AdAstra\Models\Media\Transformation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransformationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_media_relationship_is_belongs_to(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Transformation)->media());
    }

    public function test_media_relationship_is_related_to_media_model(): void
    {
        $this->assertInstanceOf(Media::class, (new Transformation)->media()->getRelated());
    }

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    public function test_is_pending_returns_true_when_status_is_pending(): void
    {
        $t = new Transformation(['status' => 'pending']);

        $this->assertTrue($t->isPending());
        $this->assertFalse($t->isComplete());
        $this->assertFalse($t->isFailed());
    }

    public function test_is_complete_returns_true_when_status_is_complete(): void
    {
        $t = new Transformation(['status' => 'complete']);

        $this->assertTrue($t->isComplete());
        $this->assertFalse($t->isPending());
        $this->assertFalse($t->isFailed());
    }

    public function test_is_failed_returns_true_when_status_is_failed(): void
    {
        $t = new Transformation(['status' => 'failed']);

        $this->assertTrue($t->isFailed());
        $this->assertFalse($t->isPending());
        $this->assertFalse($t->isComplete());
    }

    // -------------------------------------------------------------------------
    // markComplete / markFailed
    // -------------------------------------------------------------------------

    public function test_mark_complete_sets_status_and_file_metadata(): void
    {
        $media = Media::factory()->create();
        $t = Transformation::create([
            'media_id' => $media->id,
            'key'      => 'thumb',
            'disk'     => 'local',
            'path'     => 'test/_t/img_thumb.jpg',
            'status'   => 'pending',
        ]);

        $t->markComplete('test/_t/img_thumb.jpg', 8192, 200, 150);

        $t->refresh();
        $this->assertEquals('complete', $t->status);
        $this->assertEquals(8192, $t->size);
        $this->assertEquals(200, $t->width);
        $this->assertEquals(150, $t->height);
        $this->assertNull($t->error);
    }

    public function test_mark_failed_sets_status_and_error_message(): void
    {
        $media = Media::factory()->create();
        $t = Transformation::create([
            'media_id' => $media->id,
            'key'      => 'thumb',
            'disk'     => 'local',
            'path'     => 'test/_t/img_thumb.jpg',
            'status'   => 'pending',
        ]);

        $t->markFailed('Unsupported format');

        $t->refresh();
        $this->assertEquals('failed', $t->status);
        $this->assertEquals('Unsupported format', $t->error);
    }

    // -------------------------------------------------------------------------
    // fileExists / url
    // -------------------------------------------------------------------------

    public function test_file_exists_returns_false_when_file_not_on_disk(): void
    {
        Storage::fake('local');

        $media = Media::factory()->create(['disk' => 'local']);
        $t = Transformation::create([
            'media_id' => $media->id,
            'key'      => 'thumb',
            'disk'     => 'local',
            'path'     => 'no/such/file.jpg',
            'status'   => 'pending',
        ]);

        $this->assertFalse($t->fileExists());
    }

    public function test_file_exists_returns_true_when_file_on_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/_t/thumb.jpg', 'fake-content');

        $media = Media::factory()->create(['disk' => 'local']);
        $t = Transformation::create([
            'media_id' => $media->id,
            'key'      => 'thumb',
            'disk'     => 'local',
            'path'     => 'uploads/_t/thumb.jpg',
            'status'   => 'complete',
        ]);

        $this->assertTrue($t->fileExists());
    }

    // -------------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------------

    public function test_params_cast_to_array(): void
    {
        $media = Media::factory()->create();
        $t = Transformation::create([
            'media_id' => $media->id,
            'key'      => 'thumb',
            'disk'     => 'local',
            'path'     => 'test/_t/img_thumb.jpg',
            'params'   => ['width' => 100, 'height' => 100],
            'status'   => 'pending',
        ]);

        $this->assertIsArray($t->fresh()->params);
        $this->assertEquals(100, $t->fresh()->params['width']);
    }
}
