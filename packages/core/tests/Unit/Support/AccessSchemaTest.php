<?php

namespace Tests\Unit\Support;

use AdAstra\Support\AccessSchema;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_every_required_role(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $seeded = Role::pluck('name')->all();

        foreach (AccessSchema::requiredRoles() as $role) {
            $this->assertContains($role, $seeded, "Required role [{$role}] was not seeded.");
        }
    }

    public function test_seeder_creates_every_required_permission(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $seeded = Permission::pluck('name')->all();

        foreach (AccessSchema::requiredPermissions() as $permission) {
            $this->assertContains($permission, $seeded, "Required permission [{$permission}] was not seeded.");
        }
    }

    public function test_required_permissions_flattens_all_domains(): void
    {
        $flat = AccessSchema::requiredPermissions();

        $expected = 0;
        foreach (AccessSchema::permissions() as $perms) {
            $expected += count($perms);
        }

        $this->assertCount($expected, $flat);
        $this->assertSame($flat, array_unique($flat), 'Permission names must be unique across domains.');
    }
}
