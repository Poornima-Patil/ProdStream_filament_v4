<?php

namespace App\Policies;

use App\Models\HoldReason;
use App\Models\User;

class HoldReasonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'View HoldReason');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HoldReason $holdReason): bool
    {
        return $user->hasPermissionTo(permission: 'View HoldReason');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Create HoldReason');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HoldReason $holdReason): bool
    {
        return $user->hasPermissionTo(permission: 'Edit HoldReason');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HoldReason $holdReason): bool
    {
        return $user->hasPermissionTo(permission: 'Delete HoldReason');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Delete HoldReason');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, HoldReason $holdReason): bool
    {
        return $user->hasPermissionTo(permission: 'Delete HoldReason');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, HoldReason $holdReason): bool
    {
        return false;
    }
}
