<?php

namespace AdAstra\Actions\Role;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Role as RoleModel;

class EditRole extends AbstractAction
{
    public function edit(RoleModel $role, array $input)
    {
        $role->update($input);
        if (!empty($input['permissions']) && is_array($input['permissions'])) {
            $role->syncPermissions($input['permissions']);
        }
    }
}
