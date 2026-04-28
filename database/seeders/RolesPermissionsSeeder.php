<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'api', 'description' => 'Allows for accessing the REST API']);
        Permission::create(['name' => 'access admin', 'description' => 'Determines if given user can access the administration panel']);

        Permission::create(['name' => 'view user', 'description' => 'Allows for viewing users']);
        Permission::create(['name' => 'create user', 'description' => 'Allows for creating users']);
        Permission::create(['name' => 'edit user', 'description' => 'Allows for editing users']);
        Permission::create(['name' => 'delete user', 'description' => 'Allows for deleting users']);

        Permission::create(['name' => 'view user token', 'description' => 'Allow users to view tokens for users']);
        Permission::create(['name' => 'create user token', 'description' => 'Allow users to create tokens for users']);
        Permission::create(['name' => 'delete user token', 'description' => 'Allow users to delete tokens for users']);
        Permission::create(['name' => 'edit user token', 'description' => 'Allow users to edit tokens for users']);

        Permission::create(['name' => 'delete category group', 'description' => 'Allow users to delete category groups']);
        Permission::create(['name' => 'edit category group', 'description' => 'Allow users to edit category groups']);
        Permission::create(['name' => 'create category group', 'description' => 'Allow users to create category groups']);
        Permission::create(['name' => 'reorder category group', 'description' => 'Allow users to reorder category groups']);

        Permission::create(['name' => 'delete category', 'description' => 'Allow users to delete categories']);
        Permission::create(['name' => 'edit category', 'description' => 'Allow users to edit categories']);
        Permission::create(['name' => 'create category', 'description' => 'Allow users to create categories']);
        Permission::create(['name' => 'reorder category', 'description' => 'Allow users to reorder categories']);

        Permission::create(['name' => 'delete media library', 'description' => 'Allow users to delete media libraries']);
        Permission::create(['name' => 'edit media library', 'description' => 'Allow users to edit media libraries']);
        Permission::create(['name' => 'create media library', 'description' => 'Allow users to create media libraries']);
        Permission::create(['name' => 'reorder media library', 'description' => 'Allow users to reorder media libraries']);

        Permission::create(['name' => 'edit setting', 'description' => 'Allow users to edit system settings']);

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::create(['name' => 'super admin']);

        $role = Role::create(['name' => 'user']);
        $role->givePermissionTo('access admin');

        $role = Role::create(['name' => 'admin']);
        $role->givePermissionTo('access admin');
        $role->givePermissionTo('api');
        $role->givePermissionTo('view user');
        $role->givePermissionTo('create user');
        $role->givePermissionTo('delete user');
        $role->givePermissionTo('edit user');
        $role->givePermissionTo('view user token');
        $role->givePermissionTo('create user token');
        $role->givePermissionTo('delete user token');
        $role->givePermissionTo('edit user token');

        $role->givePermissionTo('delete category group');
        $role->givePermissionTo('edit category group');
        $role->givePermissionTo('create category group');
        $role->givePermissionTo('reorder category group');

        $role->givePermissionTo('delete category');
        $role->givePermissionTo('edit category');
        $role->givePermissionTo('create category');
        $role->givePermissionTo('reorder category');

        $role->givePermissionTo('delete media library');
        $role->givePermissionTo('edit media library');
        $role->givePermissionTo('create media library');
        $role->givePermissionTo('reorder media library');

        $role->givePermissionTo('edit setting');
    }
}
