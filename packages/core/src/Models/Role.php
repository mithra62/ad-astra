<?php

namespace AdAstra\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as RoleModel;

class Role extends RoleModel
{
    use HasFactory;

    /**
     * @var array|int[]
     */
    protected array $locked = [
        1, 2, 3,
    ];

    /**
     * @return bool
     */
    public function canDelete(): bool
    {
        return !in_array($this->id, $this->locked);
    }
}
