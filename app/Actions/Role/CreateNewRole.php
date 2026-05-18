<?php

namespace App\Actions\Role;

use App\Actions\AbstractAction;
use Spatie\Permission\Models\Role as RoleModel;

class CreateNewRole extends AbstractAction
{
    public function create(array $input): RoleModel
    {
        $role = RoleModel::create($input);
        if (!empty($input['permissions'])) {
            foreach ($input['permissions'] as $permission) {
                $role->givePermissionTo($permission);
            }
        }

        return $role;
    }
}
