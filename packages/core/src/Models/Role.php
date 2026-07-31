<?php

namespace AdAstra\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Registered via config/permission.php (models.role), so Spatie hydrates this
 * class everywhere — including $user->roles.
 *
 * Spatie documents Role::create() as `@return RoleContract|Role`, which is
 * imprecise: the body is `static::query()->create(...)`, so it returns the
 * called class. Narrowed here so callers get a concrete model.
 *
 * @method static static create(array<string, mixed> $attributes = [])
 */
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
