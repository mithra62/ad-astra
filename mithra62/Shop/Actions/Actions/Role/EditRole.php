<?php
namespace mithra62\Shop\Actions\Actions\Role;

use mithra62\Shop\Models\Role as RoleModel;

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
