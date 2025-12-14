<?php

namespace App\Models;

use Spatie\Permission\Models\Role AS RoleModel;

class Role extends RoleModel
{
    /**
     * @var array|int[]
     */
    protected array $locked = [
        1, 2, 3
    ];

    /**
     * @return bool
     */
    public function canDelete(): bool
    {
        return ! in_array($this->id, $this->locked);
    }
}
