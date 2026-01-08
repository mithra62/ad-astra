<?php
namespace Tests\Unit\Actions\Media\Library;

use App\Actions\Media\Library\UploadMedia;
use App\Models\Media\Library;
use App\Http\Requests\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_adds_media_to_library()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('test.jpg');

        $library = Library::create([
            'name' => 'Test Library',
            'slug' => 'test-library',
            'adapter' => 'public',
            'url' => 'http://localhost',
        ]);

        $request = $this->mock(FormRequest::class);
        $request->shouldReceive('file')->with('file', 'public')->andReturn($file);
        $request->shouldReceive('input')->with('name')->andReturn('Test Image');
        $request->shouldReceive('input')->with('categories')->andReturn([]);

        $action = new UploadMedia();
        $media = $action->upload($request, $library);

        $this->assertNotNull($media);
        $this->assertEquals('Test Image', $media->name);
        $this->assertEquals($library->id, $media->library_id);
    }
}
