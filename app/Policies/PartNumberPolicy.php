<?php

namespace App\Policies;

use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PartNumberPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'View PartNumber');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PartNumber $partNumber): bool
    {
        return $user->hasPermissionTo(permission: 'View PartNumber');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Create PartNumber');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PartNumber $partNumber): bool
    {
        return $user->hasPermissionTo(permission: 'Edit PartNumber');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PartNumber $partNumber): bool
    {
        return $user->hasPermissionTo(permission: 'Delete PartNumber');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PartNumber $partNumber): bool
    {
        return $user->hasPermissionTo(permission: 'Delete PartNumber');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PartNumber $partNumber): bool
    {
        return false;
    }
}
