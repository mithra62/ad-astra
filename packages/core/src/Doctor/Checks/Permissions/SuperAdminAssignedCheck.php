<?php

namespace AdAstra\Doctor\Checks\Permissions;

use AdAstra\Doctor\AbstractDoctorCheck;
use Spatie\Permission\Models\Role;

class SuperAdminAssignedCheck extends AbstractDoctorCheck
{
    protected string $id = 'permissions.super-admin-assigned';
    protected string $name = 'Super admin assigned';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        $role = Role::where('name', 'super admin')->first();

        if ($role === null) {
            // required-roles already fails on the missing role itself.
            yield $this->skip('The super admin role does not exist');

            return;
        }

        // Role existence is not enough — with zero holders the admin is
        // effectively bricked, with no recovery path except tinker.
        if ($role->users()->count() === 0) {
            yield $this->fail(
                'No user holds the super admin role — parts of the admin are unreachable',
                fixCommand: "php artisan tinker → \\AdAstra\\Models\\User::find(...)->assignRole('super admin')",
            );

            return;
        }

        yield $this->pass('At least one user holds the super admin role');
    }
}
