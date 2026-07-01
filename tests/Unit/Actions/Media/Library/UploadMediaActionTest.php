<?php

namespace Tests\Unit\Actions\Media\Library;

use AdAstra\Actions\Media\Library\UploadMedia;
use AdAstra\Http\Requests\FormRequest;
use AdAstra\Models\Media;
use AdAstra\Models\Media\Library;
use AdAstra\Services\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class UploadMediaActionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Delegation and return type
    // -------------------------------------------------------------------------

    public function test_upload_delegates_to_media_service(): void
    {
        $library = Library::factory()->create();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $file = UploadedFile::fake()->image('photo.jpg');

        $service = $this->bindMediaService();
        $service->shouldReceive('upload')
            ->once()
            ->with($library, $file, [])
            ->andReturn($media);

        $result = app(UploadMedia::class)->upload($this->mockRequest(['file' => $file]), $library);

        $this->assertSame($media, $result);
    }

    public function test_upload_returns_media_instance(): void
    {
        $library = Library::factory()->create();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $file = UploadedFile::fake()->image('photo.jpg');

        $service = $this->bindMediaService();
        $service->shouldReceive('upload')->once()->andReturn($media);

        $result = app(UploadMedia::class)->upload($this->mockRequest(['file' => $file]), $library);

        $this->assertInstanceOf(Media::class, $result);
    }

    // -------------------------------------------------------------------------
    // Name attribute forwarding
    // -------------------------------------------------------------------------

    public function test_upload_passes_name_attribute_to_service_when_provided(): void
    {
        $library = Library::factory()->create();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $file = UploadedFile::fake()->image('photo.jpg');

        $service = $this->bindMediaService();
        $service->shouldReceive('upload')
            ->once()
            ->with($library, $file, ['name' => 'My Photo'])
            ->andReturn($media);

        app(UploadMedia::class)->upload($this->mockRequest(['file' => $file, 'name' => 'My Photo']), $library);
    }

    public function test_upload_omits_name_attribute_when_not_provided(): void
    {
        $library = Library::factory()->create();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $file = UploadedFile::fake()->image('photo.jpg');

        $service = $this->bindMediaService();
        $service->shouldReceive('upload')
            ->once()
            ->with($library, $file, [])
            ->andReturn($media);

        app(UploadMedia::class)->upload($this->mockRequest(['file' => $file]), $library);
    }

    // -------------------------------------------------------------------------
    // Category syncing
    // -------------------------------------------------------------------------

    public function test_upload_skips_category_sync_when_categories_absent(): void
    {
        $library = Library::factory()->create();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $file = UploadedFile::fake()->image('photo.jpg');

        $service = $this->bindMediaService();
        $service->shouldReceive('upload')->once()->andReturn($media);

        $result = app(UploadMedia::class)->upload($this->mockRequest(['file' => $file]), $library);

        $this->assertInstanceOf(Media::class, $result);
    }

    public function test_upload_skips_category_sync_when_categories_is_empty_array(): void
    {
        $library = Library::factory()->create();
        $media = Media::factory()->create(['library_id' => $library->id]);
        $file = UploadedFile::fake()->image('photo.jpg');

        $service = $this->bindMediaService();
        $service->shouldReceive('upload')->once()->andReturn($media);

        $result = app(UploadMedia::class)->upload($this->mockRequest(['file' => $file, 'categories' => []]), $library);

        $this->assertInstanceOf(Media::class, $result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function bindMediaService(): Mockery\MockInterface
    {
        $mock = Mockery::mock(MediaStorageService::class);
        $this->app->instance('media-service', $mock);

        return $mock;
    }

    private function mockRequest(array $data): FormRequest
    {
        $file = $data['file'] ?? null;

        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('file')->with('file')->andReturn($file);
        $request->shouldReceive('input')->with('name')->andReturn($data['name'] ?? null);
        $request->shouldReceive('input')->with('categories')->andReturn($data['categories'] ?? null);

        return $request;
    }
}
