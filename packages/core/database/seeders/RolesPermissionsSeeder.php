<?php

namespace Database\Seeders;

use AdAstra\Support\AccessSchema;
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

        // Permission and role definitions live in AccessSchema — the shared
        // source of truth this seeder and the doctor checks both consume.
        $all = [];
        foreach (AccessSchema::permissions() as $domain => $perms) {
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
