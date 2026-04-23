<?php
namespace Tests\Unit\Actions\Category\Group;

use App\Actions\Category\Group\CreateNewCategoryGroup;
use App\Models\Category\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNewCategoryGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_creates_new_category_group()
    {
        $action = new CreateNewCategoryGroup();
        $input = [
            'name' => 'Test Group',
            'handle' => 'test-group',
        ];

        $group = $action->create($input);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals('Test Group', $group->name);
        $this->assertDatabaseHas('category_groups', ['name' => 'Test Group']);
    }
}
