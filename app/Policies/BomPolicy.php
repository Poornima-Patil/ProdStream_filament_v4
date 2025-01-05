<?php

namespace App\Policies;

use App\Models\Bom;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BomPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
       
        return $user->hasPermissionTo(permission: 'View Bom');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Bom $bom): bool
    {
        return $user->hasPermissionTo(permission: 'View Bom');

    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Create Bom');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Bom $bom): bool
    {
        return $user->hasPermissionTo(permission: 'Edit Bom');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Bom $bom): bool
    {
        return $user->hasPermissionTo(permission: 'Delete Bom');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Bom $bom): bool
    {
        return $user->hasPermissionTo(permission: 'Delete Bom');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Bom $bom): bool
    {
        return false;
    }
}
