<?php
namespace Tests\Unit\Actions\Role;

use App\Actions\Role\EditRole;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EditRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_updates_role_name()
    {
        $role = Role::create(['name' => 'old-name']);
        $action = new EditRole();
        $input = ['name' => 'new-name'];

        $action->edit($role, $input);

        $this->assertEquals('new-name', $role->fresh()->name);
    }

    public function test_edit_syncs_permissions()
    {
        Permission::create(['name' => 'perm1']);
        Permission::create(['name' => 'perm2']);
        Permission::create(['name' => 'perm3']);

        $role = Role::create(['name' => 'test-role']);
        $role->givePermissionTo('perm1');

        $action = new EditRole();
        $input = [
            'name' => 'test-role',
            'permissions' => ['perm2', 'perm3']
        ];

        $action->edit($role, $input);

        $this->assertTrue($role->hasPermissionTo('perm2'));
        $this->assertTrue($role->hasPermissionTo('perm3'));
        $this->assertFalse($role->hasPermissionTo('perm1'));
    }

    public function test_edit_does_not_sync_permissions_if_empty()
    {
        Permission::create(['name' => 'perm1']);

        $role = Role::create(['name' => 'test-role']);
        $role->givePermissionTo('perm1');

        $action = new EditRole();
        $input = [
            'name' => 'test-role',
            'permissions' => []
        ];

        $action->edit($role, $input);

        $this->assertTrue($role->hasPermissionTo('perm1'));
    }

    public function test_edit_does_not_sync_permissions_if_not_array()
    {
        Permission::create(['name' => 'perm1']);

        $role = Role::create(['name' => 'test-role']);
        $role->givePermissionTo('perm1');

        $action = new EditRole();
        $input = [
            'name' => 'test-role',
            'permissions' => 'not-an-array'
        ];

        $action->edit($role, $input);

        $this->assertTrue($role->hasPermissionTo('perm1'));
    }

    public function test_edit_updates_guard_name()
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $action = new EditRole();
        $input = ['guard_name' => 'api'];

        $action->edit($role, $input);

        $this->assertEquals('api', $role->fresh()->guard_name);
    }

    public function test_edit_does_not_touch_permissions_if_key_missing()
    {
        Permission::create(['name' => 'perm1']);
        $role = Role::create(['name' => 'test-role']);
        $role->givePermissionTo('perm1');

        $action = new EditRole();
        $input = ['name' => 'new-name'];

        $action->edit($role, $input);

        $this->assertTrue($role->fresh()->hasPermissionTo('perm1'));
    }

    public function test_edit_does_not_touch_permissions_if_null()
    {
        Permission::create(['name' => 'perm1']);
        $role = Role::create(['name' => 'test-role']);
        $role->givePermissionTo('perm1');

        $action = new EditRole();
        $input = [
            'name' => 'new-name',
            'permissions' => null
        ];

        $action->edit($role, $input);

        $this->assertTrue($role->fresh()->hasPermissionTo('perm1'));
    }
}
