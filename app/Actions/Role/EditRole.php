<?php
namespace App\Actions\Role;

use App\Models\Role as RoleModel;
use App\Actions\AbstractAction;

class EditRole extends AbstractAction
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
