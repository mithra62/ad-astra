<?php

namespace mithra62\Shop\Policies\Policies;

use App\Models\Remittance\Soybean;
use Illuminate\Auth\Access\HandlesAuthorization;
use mithra62\Shop\Models\User;

class SoybeanRemittancePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('read soybean');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Soybean $soybeanRemittance): bool
    {
        return $user->can('read soybean');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Soybean $soybeanRemittance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Soybean $soybeanRemittance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Soybean $soybeanRemittance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Soybean $soybeanRemittance): bool
    {
        return true;
    }
}
