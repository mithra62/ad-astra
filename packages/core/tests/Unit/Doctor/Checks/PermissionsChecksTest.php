<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Permissions\RequiredPermissionsCheck;
use AdAstra\Doctor\Checks\Permissions\RequiredRolesCheck;
use AdAstra\Doctor\DoctorStatus;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionsChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_pass_after_seeding(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $results = iterator_to_array((new RequiredRolesCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_missing_role_fails_by_name(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        Role::where('name', 'admin')->delete();

        $results = iterator_to_array((new RequiredRolesCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertStringContainsString('admin', $results[0]->message);
        $this->assertStringContainsString('RolesPermissionsSeeder', $results[0]->fixCommand);
    }

    public function test_permissions_pass_after_seeding(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $results = iterator_to_array((new RequiredPermissionsCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_missing_permission_fails_by_name(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        Permission::where('name', 'create entry')->delete();

        $results = iterator_to_array((new RequiredPermissionsCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertStringContainsString('create entry', $results[0]->message);
    }
}
