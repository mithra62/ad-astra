<?php
namespace Tests\Unit\Actions\Category;

use App\Actions\Category\CreateNewCategory;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNewCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_creates_new_category()
    {
        $group = \App\Models\Category\Group::create(['name' => 'Test Group', 'handle' => 'test-group']);
        $action = new CreateNewCategory();
        $input = [
            'name' => 'Test Category',
            'handle' => 'test-category',
            'group_id' => $group->id,
        ];

        $category = $action->create($input);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals('Test Category', $category->name);
        $this->assertDatabaseHas('categories', ['name' => 'Test Category']);
    }
}
