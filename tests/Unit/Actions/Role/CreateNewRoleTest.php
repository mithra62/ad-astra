<?php
namespace Tests\Unit\Actions\Role;

use App\Actions\Role\CreateNewRole;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNewRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_creates_new_role()
    {
        $action = new CreateNewRole();
        $input = [
            'name' => 'Test Role',
            'guard_name' => 'web',
        ];

        $role = $action->create($input);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('Test Role', $role->name);
        $this->assertDatabaseHas('roles', ['name' => 'Test Role']);
    }

    public function test_create_assigns_permissions_if_provided()
    {
        $permission = Permission::create(['name' => 'test permission']);
        $action = new CreateNewRole();
        $input = [
            'name' => 'Role With Permission',
            'guard_name' => 'web',
            'permissions' => ['test permission'],
        ];

        $role = $action->create($input);

        $this->assertTrue($role->hasPermissionTo('test permission'));
    }
}
