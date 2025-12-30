<?php
namespace App\Actions\Role;

use Spatie\Permission\Models\Role as RoleModel;
use App\Actions\AbstractAction;

class CreateNewRole extends AbstractAction
{
    public function create(array $input): RoleModel
    {
        $role = RoleModel::create($input);
        if (!empty($input['permissions'])) {
            foreach($input['permissions'] AS $permission) {
                $role->givePermissionTo($permission);
            }
        }

        return $role;
    }
}
