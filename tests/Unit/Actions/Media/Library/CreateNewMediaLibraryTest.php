<?php
namespace Tests\Unit\Actions\Media\Library;

use App\Actions\Media\Library\CreateNewMediaLibrary;
use App\Models\Media\Library;
use App\Models\Category\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNewMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_creates_new_media_library()
    {
        $action = new CreateNewMediaLibrary();
        $input = [
            'name' => 'Test Library',
            'slug' => 'test-library',
            'url' => 'http://localhost',
        ];

        $library = $action->create($input);

        $this->assertInstanceOf(Library::class, $library);
        $this->assertEquals('Test Library', $library->name);
    }

    public function test_create_attaches_category_groups_if_provided()
    {
        $group = Group::create(['name' => 'Group 1', 'slug' => 'group-1']);
        $action = new CreateNewMediaLibrary();
        $input = [
            'name' => 'Test Library',
            'slug' => 'test-library',
            'url' => 'http://localhost',
            'category_groups' => [$group->id],
        ];

        $library = $action->create($input);

        $this->assertTrue($library->category_groups->contains($group));
    }
}
