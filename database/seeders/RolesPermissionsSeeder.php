<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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

        Permission::create(['name' => 'delete categories', 'description' => 'Allow users to delete categories']);
        Permission::create(['name' => 'edit categories', 'description' => 'Allow users to edit categories']);
        Permission::create(['name' => 'create categories', 'description' => 'Allow users to create categories']);
        Permission::create(['name' => 'reorder categories', 'description' => 'Allow users to reorder categories']);

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::create(['name' => 'super admin']);

        $role = Role::create(['name' => 'user']);
        $role->givePermissionTo('access admin');

        $role = Role::create(['name' => 'admin']);
        $role->givePermissionTo('access admin');
        $role->givePermissionTo('api');
        $role->givePermissionTo('create user');
        $role->givePermissionTo('delete user');
        $role->givePermissionTo('edit user');
        $role->givePermissionTo('delete user');
        $role->givePermissionTo('create token');
        $role->givePermissionTo('delete token');
        $role->givePermissionTo('edit token');
    }
}
