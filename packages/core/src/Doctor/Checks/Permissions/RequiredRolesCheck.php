<?php

namespace AdAstra\Doctor\Checks\Permissions;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Support\AccessSchema;
use Spatie\Permission\Models\Role;

class RequiredRolesCheck extends AbstractDoctorCheck
{
    protected string $id = 'permissions.required-roles';
    protected string $name = 'Required roles';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        $existing = Role::pluck('name')->all();
        $missing = 0;

        foreach (AccessSchema::requiredRoles() as $role) {
            if (!in_array($role, $existing, true)) {
                $missing++;
                yield $this->fail(
                    "Missing role: \"{$role}\"",
                    fixCommand: 'php artisan db:seed --class=RolesPermissionsSeeder',
                );
            }
        }

        if ($missing === 0) {
            yield $this->pass('All required roles present');
        }
    }
}
