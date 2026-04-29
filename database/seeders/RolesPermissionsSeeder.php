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

        $permissions = [
            'api' => 'Allows for accessing the REST API',
            'access admin' => 'Determines if given user can access the administration panel',

            'view user' => 'Allows for viewing users',
            'create user' => 'Allows for creating users',
            'edit user' => 'Allows for editing users',
            'delete user' => 'Allows for deleting users',

            'view user token' => 'Allow users to view tokens for users',
            'create user token' => 'Allow users to create tokens for users',
            'edit user token' => 'Allow users to edit tokens for users',
            'delete user token' => 'Allow users to delete tokens for users',

            'create role' => 'Allow users to create roles',
            'edit role' => 'Allow users to edit roles',
            'delete role' => 'Allow users to delete roles',

            'create category group' => 'Allow users to create category groups',
            'edit category group' => 'Allow users to edit category groups',
            'delete category group' => 'Allow users to delete category groups',
            'reorder category group' => 'Allow users to reorder category groups',

            'create category' => 'Allow users to create categories',
            'edit category' => 'Allow users to edit categories',
            'delete category' => 'Allow users to delete categories',
            'reorder category' => 'Allow users to reorder categories',

            'create entry group' => 'Allow users to create entry groups',
            'edit entry group' => 'Allow users to edit entry groups',
            'delete entry group' => 'Allow users to delete entry groups',

            'create entry type' => 'Allow users to create entry types',
            'edit entry type' => 'Allow users to edit entry types',
            'delete entry type' => 'Allow users to delete entry types',

            'create entry' => 'Allow users to create entries',
            'edit entry' => 'Allow users to edit entries',
            'delete entry' => 'Allow users to delete entries',

            'create field group' => 'Allow users to create field groups',
            'edit field group' => 'Allow users to edit field groups',
            'delete field group' => 'Allow users to delete field groups',

            'create field' => 'Allow users to create fields',
            'edit field' => 'Allow users to edit fields',
            'delete field' => 'Allow users to delete fields',

            'create field layout' => 'Allow users to create field layouts',
            'edit field layout' => 'Allow users to edit field layouts',
            'delete field layout' => 'Allow users to delete field layouts',

            'create media library' => 'Allow users to create media libraries',
            'edit media library' => 'Allow users to edit media libraries',
            'delete media library' => 'Allow users to delete media libraries',
            'reorder media library' => 'Allow users to reorder media libraries',

            'create status' => 'Allow users to create statuses',
            'edit status' => 'Allow users to edit statuses',
            'delete status' => 'Allow users to delete statuses',

            'edit setting' => 'Allow users to edit system settings',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super admin']);

        $role = Role::firstOrCreate(['name' => 'user']);
        $role->givePermissionTo(['access admin']);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(array_keys($permissions));
    }
}
