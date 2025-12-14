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
        Permission::create(['name' => 'create user', 'description' => 'Allows for creating users within the admin panel']);
        Permission::create(['name' => 'edit user', 'description' => 'Allows for editing users within the admin panel']);
        Permission::create(['name' => 'delete user', 'description' => 'Allows for deleting users within the admin panel']);
        Permission::create(['name' => 'create token', 'description' => 'Allow users to create tokens for users within the admin panel']);
        Permission::create(['name' => 'delete token', 'description' => 'Allow users to delete tokens for users within the admin panel']);
        Permission::create(['name' => 'edit token', 'description' => 'Allow users to edit tokens for users within the admin panel']);

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
