<?php

namespace Tests\Unit\Actions\Media;

use AdAstra\Actions\Media\DeleteMedia;
use AdAstra\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteMediaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_soft_deletes_media_record(): void
    {
        Storage::fake('local');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/photo.jpg']);

        app(DeleteMedia::class)->delete($media);

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    public function test_delete_does_not_remove_physical_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/photo.jpg', 'data');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/photo.jpg']);

        app(DeleteMedia::class)->delete($media);

        Storage::disk('local')->assertExists('uploads/photo.jpg');
    }

    public function test_delete_delegates_to_media_service(): void
    {
        Storage::fake('local');

        $media = Media::factory()->create(['disk' => 'local', 'path' => 'uploads/x.jpg']);
        $deleted = false;

        $this->app->bind('media-service', function () use ($media, &$deleted) {
            return new class ($media, $deleted) {
                public function __construct(private $media, private &$deleted)
                {
                }

                public function delete($m): void
                {
                    $this->deleted = true;
                }
            };
        });

        app(DeleteMedia::class)->delete($media);

        $this->assertTrue($deleted);
    }
}
