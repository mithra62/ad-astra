<?php

namespace Tests\Unit\Services\Media;

use AdAstra\Jobs\ProcessTransformation;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Transformation;
use AdAstra\Services\Media\GDTransformationDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class GDTransformationDriverTest extends TestCase
{
    use RefreshDatabase;

    private GDTransformationDriver $driver;

    public function test_apply_sync_writes_file_to_storage(): void
    {
        $media = $this->makeMediaWithImage(800, 600);
        $t = $this->makePendingTransformation($media, ['width' => 200, 'height' => 200]);

        $this->driver->applySync($t);

        Storage::disk('local')->assertExists($t->path);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Creates a JPEG file on the fake disk and returns a Media model pointing to it.
     */
    private function makeMediaWithImage(int $w = 800, int $h = 600, string $path = 'uploads/photo.jpg'): Media
    {
        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, 100, 149, 237));

        $tmp = tempnam(sys_get_temp_dir(), 'gd_test_');
        imagejpeg($img, $tmp, 90);
        imagedestroy($img);

        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return Media::factory()->create([
            'disk' => 'local',
            'path' => $path,
            'file_name' => basename($path),
            'mime_type' => 'image/jpeg',
        ]);
    }

    private function makePendingTransformation(Media $media, array $params = []): Transformation
    {
        return Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb',
            'disk' => 'local',
            'path' => 'uploads/_t/photo_thumb.jpg',
            'params' => $params,
            'status' => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // applySync — cover mode (default)
    // -------------------------------------------------------------------------

    public function test_apply_sync_marks_transformation_complete(): void
    {
        $media = $this->makeMediaWithImage(800, 600);
        $t = $this->makePendingTransformation($media, ['width' => 200, 'height' => 200]);

        $this->driver->applySync($t);
        $t->refresh();

        $this->assertTrue($t->isComplete());
    }

    public function test_apply_sync_cover_produces_exact_target_dimensions(): void
    {
        $media = $this->makeMediaWithImage(800, 600);
        $t = $this->makePendingTransformation($media, ['width' => 200, 'height' => 200, 'mode' => 'cover']);

        $this->driver->applySync($t);
        $t->refresh();

        $this->assertSame(200, $t->width);
        $this->assertSame(200, $t->height);
    }

    public function test_apply_sync_contain_fits_within_bounds(): void
    {
        $media = $this->makeMediaWithImage(800, 400); // 2:1 ratio
        $t = $this->makePendingTransformation($media, ['width' => 200, 'height' => 200, 'mode' => 'contain']);

        $this->driver->applySync($t);
        $t->refresh();

        // Width should be 200, height proportionally 100 (2:1 ratio preserved).
        $this->assertSame(200, $t->width);
        $this->assertSame(100, $t->height);
    }

    // -------------------------------------------------------------------------
    // applySync — contain mode
    // -------------------------------------------------------------------------

    public function test_apply_sync_exact_stretches_to_target_dimensions(): void
    {
        $media = $this->makeMediaWithImage(800, 600);
        $t = $this->makePendingTransformation($media, ['width' => 300, 'height' => 150, 'mode' => 'exact']);

        $this->driver->applySync($t);
        $t->refresh();

        $this->assertSame(300, $t->width);
        $this->assertSame(150, $t->height);
    }

    // -------------------------------------------------------------------------
    // applySync — exact mode
    // -------------------------------------------------------------------------

    public function test_apply_sync_converts_to_png(): void
    {
        $media = $this->makeMediaWithImage();
        $t = Transformation::create([
            'media_id' => $media->id,
            'key' => 'thumb-png',
            'disk' => 'local',
            'path' => 'uploads/_t/photo_thumb.png',
            'params' => ['width' => 100, 'height' => 100, 'format' => 'png'],
            'status' => 'pending',
        ]);

        $this->driver->applySync($t);

        Storage::disk('local')->assertExists('uploads/_t/photo_thumb.png');
        $t->refresh();
        $this->assertTrue($t->isComplete());
    }

    // -------------------------------------------------------------------------
    // applySync — format conversion
    // -------------------------------------------------------------------------

    public function test_apply_sync_with_no_params_copies_source_at_original_dimensions(): void
    {
        $media = $this->makeMediaWithImage(640, 480);
        $t = $this->makePendingTransformation($media, []);

        $this->driver->applySync($t);
        $t->refresh();

        $this->assertSame(640, $t->width);
        $this->assertSame(480, $t->height);
        $this->assertTrue($t->isComplete());
    }

    // -------------------------------------------------------------------------
    // applySync — no params (passthrough)
    // -------------------------------------------------------------------------

    public function test_apply_sync_throws_when_source_file_missing(): void
    {
        $media = Media::factory()->create([
            'disk' => 'local',
            'path' => 'uploads/missing.jpg',
            'file_name' => 'missing.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        // No file placed on the fake disk.
        $t = $this->makePendingTransformation($media);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Source file not found/');

        $this->driver->applySync($t);
    }

    // -------------------------------------------------------------------------
    // applySync — error handling
    // -------------------------------------------------------------------------

    public function test_dispatch_queues_process_transformation_job(): void
    {
        Queue::fake();

        $media = $this->makeMediaWithImage();
        $t = $this->makePendingTransformation($media);

        $this->driver->dispatch($t);

        Queue::assertPushed(
            ProcessTransformation::class,
            fn($job) => $job->transformationId === $t->id
        );
    }

    // -------------------------------------------------------------------------
    // dispatch — queues ProcessTransformation job
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new GDTransformationDriver();
        Storage::fake('local');
    }
}
