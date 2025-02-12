<?php

namespace App\Policies;

use App\Models\ScrappedReason;
use App\Models\User;

class ScrappedReasonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'View ScrappedReason');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ScrappedReason $scrappedReason): bool
    {
        return $user->hasPermissionTo(permission: 'View ScrappedReason');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Create ScrappedReason');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ScrappedReason $scrappedReason): bool
    {
        return $user->hasPermissionTo(permission: 'Edit ScrappedReason');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ScrappedReason $scrappedReason): bool
    {
        return $user->hasPermissionTo(permission: 'Delete ScrappedReason');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Delete ScrappedReason');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ScrappedReason $scrappedReason): bool
    {
        return $user->hasPermissionTo(permission: 'Delete ScrappedReason');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ScrappedReason $scrappedReason): bool
    {
        return false;
    }
}
