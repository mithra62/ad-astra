<?php

namespace App\Models;

use Spatie\Permission\Models\Role as RoleModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends RoleModel
{
    use HasFactory;

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
