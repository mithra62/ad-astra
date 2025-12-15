<?php

namespace mithra62\Shop\Policies\Policies;

use App\Models\Remittance\Corn;
use mithra62\Shop\Models\User;

class CornRemittancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('read corn');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Corn $cornRemittance): bool
    {
        return $user->can('read corn');
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
    public function update(User $user, Corn $cornRemittance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Corn $cornRemittance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Corn $cornRemittance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Corn $cornRemittance): bool
    {
        return true;
    }
}
