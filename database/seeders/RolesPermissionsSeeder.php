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

        Permission::create(['name' => 'read corn', 'description' => 'Allows for reading Corn based API resources']);
        Permission::create(['name' => 'read soybean', 'description' => 'Allows for reading Soybean based API resources']);
        Permission::create(['name' => 'read submissions', 'description' => 'Allows for reading all Submissions API resources']);
        Permission::create(['name' => 'read corn submissions', 'description' => 'Allows for reading all Corn specific Submissions API resources']);
        Permission::create(['name' => 'read soybean submissions', 'description' => 'Allows for reading Soybean Submissions API resources']);
        Permission::create(['name' => 'api', 'description' => 'Allows for accessing the REST API']);
        Permission::create(['name' => 'access admin', 'description' => 'Determines if given user can access the administration panel']);
        Permission::create(['name' => 'create user', 'description' => 'Allows for creating users within the admin panel']);
        Permission::create(['name' => 'edit user', 'description' => 'Allows for editing users within the admin panel']);
        Permission::create(['name' => 'delete user', 'description' => 'Allows for deleting users within the admin panel']);
        Permission::create(['name' => 'create token', 'description' => 'Allow users to create tokens for users within the admin panel']);
        Permission::create(['name' => 'delete token', 'description' => 'Allow users to delete tokens for users within the admin panel']);
        Permission::create(['name' => 'edit token', 'description' => 'Allow users to edit tokens for users within the admin panel']);

        //location based roles
        Permission::create(['name' => 'read all locations', 'description' => 'Allow reading API resources that are located in any state']);
        Permission::create(['name' => 'read new york', 'description' => 'Allow reading API resources that are located in New York']);
        Permission::create(['name' => 'read iowa', 'description' => 'Allow reading API resources that are located in Iowa']);
        Permission::create(['name' => 'read illinois', 'description' => 'Allow reading API resources that are located in Illinois']);
        Permission::create(['name' => 'read minnesota', 'description' => 'Allow reading API resources that are located in Minnesota']);
        Permission::create(['name' => 'read missouri', 'description' => 'Allow reading API resources that are located in Missouri']);
        Permission::create(['name' => 'read montana', 'description' => 'Allow reading API resources that are located in Montana']);
        Permission::create(['name' => 'read north dakota', 'description' => 'Allow reading API resources that are located in North Dakota']);
        Permission::create(['name' => 'read pennsylvania', 'description' => 'Allow reading API resources that are located in Pennsylvania']);
        Permission::create(['name' => 'read wisconsin', 'description' => 'Allow reading API resources that are located in Wisconsin']);
        Permission::create(['name' => 'read ontario', 'description' => 'Allow reading API resources that are located in Ontario']);

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::create(['name' => 'super admin']);

        $role = Role::create(['name' => 'user']);
        $role->givePermissionTo('access admin');

        $role = Role::create(['name' => 'admin']);
        $role->givePermissionTo('read all locations');
        $role->givePermissionTo('access admin');
        $role->givePermissionTo('read corn');
        $role->givePermissionTo('read corn submissions');
        $role->givePermissionTo('read soybean');
        $role->givePermissionTo('read soybean submissions');
        $role->givePermissionTo('api');
        $role->givePermissionTo('create user');
        $role->givePermissionTo('delete user');
        $role->givePermissionTo('edit user');
        $role->givePermissionTo('delete user');
        $role->givePermissionTo('create token');
        $role->givePermissionTo('delete token');
        $role->givePermissionTo('edit token');

        $role = Role::create(['name' => 'corn']);
        $role->givePermissionTo('api');
        $role->givePermissionTo('read corn');
        $role->givePermissionTo('read corn submissions');

        $role = Role::create(['name' => 'soybean']);
        $role->givePermissionTo('api');
        $role->givePermissionTo('read soybean');

        $role = Role::create(['name' => 'submission']);
        $role->givePermissionTo('api');
        $role->givePermissionTo('read submissions');

        //location based roles
        $role = Role::create(['name' => 'new york']);
        $role->givePermissionTo('read new york');

        $role = Role::create(['name' => 'iowa']);
        $role->givePermissionTo('read iowa');

        $role = Role::create(['name' => 'illinois']);
        $role->givePermissionTo('read illinois');

        $role = Role::create(['name' => 'minnesota']);
        $role->givePermissionTo('read minnesota');

        $role = Role::create(['name' => 'missouri']);
        $role->givePermissionTo('read missouri');

        $role = Role::create(['name' => 'montana']);
        $role->givePermissionTo('read montana');

        $role = Role::create(['name' => 'north dakota']);
        $role->givePermissionTo('read north dakota');

        $role = Role::create(['name' => 'pennsylvania']);
        $role->givePermissionTo('read pennsylvania');

        $role = Role::create(['name' => 'wisconsin']);
        $role->givePermissionTo('read wisconsin');

        $role = Role::create(['name' => 'ontario']);
        $role->givePermissionTo('read ontario');
    }
}
