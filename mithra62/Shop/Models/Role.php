<?php

namespace mithra62\Shop\Models;

use Spatie\Permission\Models\Role as RoleModel;

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
