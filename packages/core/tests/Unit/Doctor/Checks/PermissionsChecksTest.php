<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\Permissions\RequiredPermissionsCheck;
use AdAstra\Doctor\Checks\Permissions\RequiredRolesCheck;
use AdAstra\Doctor\Checks\Permissions\SuperAdminAssignedCheck;
use AdAstra\Doctor\DoctorStatus;
use AdAstra\Models\User;
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

    public function test_super_admin_assigned_passes_when_a_user_holds_the_role(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        User::factory()->create()->assignRole('super admin');

        $results = iterator_to_array((new SuperAdminAssignedCheck())->run(), false);

        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_super_admin_assigned_fails_when_role_has_no_holders(): void
    {
        // Role existence is not enough — this is the bricked-admin state.
        $this->seed(RolesPermissionsSeeder::class);

        $results = iterator_to_array((new SuperAdminAssignedCheck())->run(), false);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
    }

    public function test_super_admin_assigned_skips_when_role_is_missing(): void
    {
        $results = iterator_to_array((new SuperAdminAssignedCheck())->run(), false);

        $this->assertSame(DoctorStatus::Skip, $results[0]->status);
    }
}
