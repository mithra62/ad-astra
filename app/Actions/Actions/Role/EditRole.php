<?php
namespace App\Actions\Actions\Role;

use App\Models\Role as RoleModel;

class EditRole
{
    public function edit(RoleModel $role, array $input)
    {
        $role->update($input);
        if(!empty($input['permissions']) && is_array($input['permissions'])) {
            if(count($input['permissions']) >= 1) {
                $role->syncPermissions($input['permissions']);
            }
        }
    }
}
