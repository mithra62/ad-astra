<?php
namespace Tests\Unit\Actions\Category;

use App\Actions\Category\EditCategory;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_updates_category()
    {
        $group = \App\Models\Category\Group::create(['name' => 'Test Group', 'slug' => 'test-group']);
        $category = Category::create([
            'name' => 'Old Name',
            'slug' => 'old-slug',
            'group_id' => $group->id,
        ]);

        $action = new EditCategory();
        $input = [
            'name' => 'New Name',
        ];

        $result = $action->edit($category, $input);

        $this->assertTrue($result);
        $this->assertEquals('New Name', $category->fresh()->name);
    }
}
