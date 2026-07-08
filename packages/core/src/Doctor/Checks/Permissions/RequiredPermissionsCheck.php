<?php

namespace AdAstra\Doctor\Checks\Permissions;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Support\AccessSchema;
use Spatie\Permission\Models\Permission;

class RequiredPermissionsCheck extends AbstractDoctorCheck
{
    protected string $id = 'permissions.required-permissions';
    protected string $name = 'Required permissions';

    public function dependsOn(): array
    {
        return ['database.connection', 'database.required-tables'];
    }

    public function run(): iterable
    {
        $existing = Permission::pluck('name')->all();
        $missing = 0;

        foreach (AccessSchema::requiredPermissions() as $permission) {
            if (!in_array($permission, $existing, true)) {
                $missing++;
                yield $this->fail(
                    "Missing permission: \"{$permission}\"",
                    fixCommand: 'php artisan db:seed --class=RolesPermissionsSeeder',
                );
            }
        }

        if ($missing === 0) {
            yield $this->pass('All ' . count(AccessSchema::requiredPermissions()) . ' required permissions present');
        }
    }
}
