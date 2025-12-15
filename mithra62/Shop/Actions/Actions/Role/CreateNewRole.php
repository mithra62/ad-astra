<?php
namespace mithra62\Shop\Actions\Actions\Role;

use Spatie\Permission\Models\Role as RoleModel;

class CreateNewRole
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
