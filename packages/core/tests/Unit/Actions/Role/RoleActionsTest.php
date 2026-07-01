<?php

namespace Tests\Unit\Actions\Role;

use AdAstra\Actions\Role\CreateNewRole;
use AdAstra\Actions\Role\EditRole;
use AdAstra\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_returns_role_instance(): void
    {
        $action = app(CreateNewRole::class);

        $result = $action->create(['name' => 'Editor', 'guard_name' => 'web']);

        $this->assertInstanceOf(SpatieRole::class, $result);
    }

    // -------------------------------------------------------------------------
    // CreateNewRole
    // -------------------------------------------------------------------------

    public function test_create_persists_role_to_database(): void
    {
        $action = app(CreateNewRole::class);

        $action->create(['name' => 'Author', 'guard_name' => 'web']);

        $this->assertDatabaseHas('roles', ['name' => 'Author']);
    }

    public function test_create_without_permissions_creates_role_with_no_permissions(): void
    {
        $action = app(CreateNewRole::class);

        $role = $action->create(['name' => 'Viewer', 'guard_name' => 'web']);

        $this->assertCount(0, $role->permissions);
    }

    public function test_create_grants_permissions_when_provided(): void
    {
        $permission = Permission::create(['name' => 'edit articles', 'guard_name' => 'web']);
        $action = app(CreateNewRole::class);

        $role = $action->create([
            'name' => 'Content Editor',
            'guard_name' => 'web',
            'permissions' => ['edit articles'],
        ]);

        $this->assertTrue($role->hasPermissionTo('edit articles'));
    }

    public function test_create_grants_multiple_permissions(): void
    {
        Permission::create(['name' => 'view posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'create posts', 'guard_name' => 'web']);
        $action = app(CreateNewRole::class);

        $role = $action->create([
            'name' => 'Blogger',
            'guard_name' => 'web',
            'permissions' => ['view posts', 'create posts'],
        ]);

        $this->assertTrue($role->hasPermissionTo('view posts'));
        $this->assertTrue($role->hasPermissionTo('create posts'));
    }

    public function test_edit_updates_role_name(): void
    {
        $role = Role::factory()->create(['name' => 'Old Role']);
        $action = app(EditRole::class);

        $action->edit($role, ['name' => 'New Role', 'guard_name' => 'web']);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'New Role']);
    }

    // -------------------------------------------------------------------------
    // EditRole
    // -------------------------------------------------------------------------

    public function test_edit_syncs_permissions_when_provided(): void
    {
        $perm1 = Permission::create(['name' => 'perm one', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'perm two', 'guard_name' => 'web']);
        $role = Role::factory()->create();
        $role->givePermissionTo($perm1);
        $action = app(EditRole::class);

        $action->edit($role, [
            'name' => $role->name,
            'guard_name' => 'web',
            'permissions' => ['perm two'],
        ]);

        $fresh = $role->fresh();
        $this->assertFalse($fresh->hasPermissionTo('perm one'));
        $this->assertTrue($fresh->hasPermissionTo('perm two'));
    }

    public function test_edit_does_not_sync_permissions_when_none_provided(): void
    {
        $perm = Permission::create(['name' => 'keep this', 'guard_name' => 'web']);
        $role = Role::factory()->create();
        $role->givePermissionTo($perm);
        $action = app(EditRole::class);

        // No 'permissions' key in input — existing permissions should be untouched
        $action->edit($role, ['name' => $role->name, 'guard_name' => 'web']);

        $this->assertTrue($role->fresh()->hasPermissionTo('keep this'));
    }

    public function test_edit_does_not_sync_when_permissions_is_empty_array(): void
    {
        $perm = Permission::create(['name' => 'stays put', 'guard_name' => 'web']);
        $role = Role::factory()->create();
        $role->givePermissionTo($perm);
        $action = app(EditRole::class);

        // An empty array means no permissions to sync — guard requires count >= 1
        $action->edit($role, [
            'name' => $role->name,
            'guard_name' => 'web',
            'permissions' => [],
        ]);

        // The action only syncs when count >= 1, so existing perms should remain
        $this->assertTrue($role->fresh()->hasPermissionTo('stays put'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Clear the Spatie permission cache between tests
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
