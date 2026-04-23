<?php
namespace Tests\Unit\Actions\Media\Library;

use App\Actions\Media\Library\EditMediaLibrary;
use App\Models\Media\Library;
use App\Models\Category\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_updates_media_library()
    {
        $library = Library::create([
            'name' => 'Old Name',
            'handle' => 'old-slug',
            'url' => 'http://localhost',
        ]);

        $action = new EditMediaLibrary();
        $input = [
            'name' => 'New Name',
        ];

        $result = $action->edit($library, $input);

        $this->assertTrue($result);
        $this->assertEquals('New Name', $library->fresh()->name);
    }

    public function test_edit_syncs_category_groups()
    {
        $group1 = Group::create(['name' => 'Group 1', 'handle' => 'group-1']);
        $group2 = Group::create(['name' => 'Group 2', 'handle' => 'group-2']);

        $library = Library::create([
            'name' => 'Test Library',
            'handle' => 'test-library',
            'url' => 'http://localhost',
        ]);
        $library->category_groups()->attach($group1);

        $action = new EditMediaLibrary();
        $input = [
            'name' => 'Test Library',
            'category_groups' => [$group2->id],
        ];

        $action->edit($library, $input);

        $this->assertTrue($library->category_groups->contains($group2));
        $this->assertFalse($library->category_groups->contains($group1));
    }
}
