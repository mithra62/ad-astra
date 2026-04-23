<?php
namespace Tests\Unit\Actions\Category\Group;

use App\Actions\Category\Group\EditCategoryGroup;
use App\Models\Category\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditCategoryGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_updates_category_group()
    {
        $group = Group::create([
            'name' => 'Old Name',
            'handle' => 'old-slug',
        ]);

        $action = new EditCategoryGroup();
        $input = [
            'name' => 'New Name',
        ];

        $result = $action->edit($group, $input);

        $this->assertTrue($result);
        $this->assertEquals('New Name', $group->fresh()->name);
    }
}
