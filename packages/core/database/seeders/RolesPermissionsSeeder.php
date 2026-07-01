<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users' => [
                'read users' => 'Allows for listing and reading users via the API',
                'create user' => 'Allows for creating users',
                'edit user' => 'Allows for editing users',
                'delete user' => 'Allows for deleting users',
                'manage user status' => 'Allows for changing user account status (active, inactive, pending, suspended, banned) and managing locks',

                'view user token' => 'Allow users to view tokens for users',
                'create user token' => 'Allow users to create tokens for users',
                'edit user token' => 'Allow users to edit tokens for users',
                'delete user token' => 'Allow users to delete tokens for users',

                'create role' => 'Allow users to create roles',
                'edit role' => 'Allow users to edit roles',
                'delete role' => 'Allow users to delete roles',
            ],
            'system' => [
                'access admin' => 'Determines if given user can access the administration panel',
                'api' => 'Allows for accessing the REST API',
                'edit setting' => 'Allow users to edit system settings',
            ],
            'entries' => [
                'read entry groups' => 'Allow users to list and read entry groups via the API',
                'create entry group' => 'Allow users to create entry groups',
                'edit entry group' => 'Allow users to edit entry groups',
                'delete entry group' => 'Allow users to delete entry groups',

                'create entry type' => 'Allow users to create entry types',
                'edit entry type' => 'Allow users to edit entry types',
                'delete entry type' => 'Allow users to delete entry types',

                'read entries' => 'Allow users to list and read entries via the API',
                'create entry' => 'Allow users to create entries',
                'edit entry' => 'Allow users to edit entries',
                'delete entry' => 'Allow users to delete entries',

                'read status groups' => 'Allow users to list and read status groups via the API',
                'read statuses' => 'Allow users to list and read statuses via the API',
                'create status' => 'Allow users to create statuses',
                'edit status' => 'Allow users to edit statuses',
                'delete status' => 'Allow users to delete statuses',
            ],
            'categories' => [
                'read category groups' => 'Allow users to list and read category groups via the API',
                'create category group' => 'Allow users to create category groups',
                'edit category group' => 'Allow users to edit category groups',
                'delete category group' => 'Allow users to delete category groups',
                'reorder category group' => 'Allow users to reorder category groups',

                'read categories' => 'Allow users to list and read categories via the API',
                'create category' => 'Allow users to create categories',
                'edit category' => 'Allow users to edit categories',
                'delete category' => 'Allow users to delete categories',
                'reorder category' => 'Allow users to reorder categories',
            ],
            'fields' => [
                'create field group' => 'Allow users to create field groups',
                'edit field group' => 'Allow users to edit field groups',
                'delete field group' => 'Allow users to delete field groups',

                'create field' => 'Allow users to create fields',
                'edit field' => 'Allow users to edit fields',
                'delete field' => 'Allow users to delete fields',
            ],
            'layouts' => [
                'create field layout' => 'Allow users to create field layouts',
                'edit field layout' => 'Allow users to edit field layouts',
                'delete field layout' => 'Allow users to delete field layouts',
            ],
            'media' => [
                'create media library' => 'Allow users to create media libraries',
                'edit media library' => 'Allow users to edit media libraries',
                'delete media library' => 'Allow users to delete media libraries',
                'reorder media library' => 'Allow users to reorder media libraries',
                'upload media' => 'Allow users to upload media files',
            ],
        ];

        $all = [];
        foreach ($permissions as $domain => $perms) {
            foreach ($perms as $name => $description) {
                $all[] = $name;
                Permission::firstOrCreate(['name' => $name], ['description' => $description, 'domain' => $domain]);
            }
        }

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super admin', 'highlight' => '#0f172a', 'description' => 'Full platform access with permission bypass behavior.']);

        $role = Role::firstOrCreate(['name' => 'user', 'highlight' => '#64748b', 'description' => 'Baseline authenticated account role with limited admin access.']);
        $role->givePermissionTo(['access admin']);

        $role = Role::firstOrCreate(['name' => 'admin', 'highlight' => '#059669', 'description' => 'Allows base access to the administration panel']);
        $role->givePermissionTo($all);
    }
}
