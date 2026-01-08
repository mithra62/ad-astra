<?php
namespace Tests\Unit\Actions\Media\Library;

use App\Actions\Media\Library\DeleteMediaLibrary;
use App\Models\Media\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_removes_media_library()
    {
        $library = Library::create([
            'name' => 'Test Library',
            'slug' => 'test-library',
            'url' => 'http://localhost',
        ]);

        $action = new DeleteMediaLibrary();
        $result = $action->delete($library);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('media_libraries', ['id' => $library->id]);
    }
}
