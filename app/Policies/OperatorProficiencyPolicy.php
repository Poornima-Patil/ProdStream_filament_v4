<?php

namespace App\Policies;

use App\Models\OperatorProficiency;
use App\Models\User;

class OperatorProficiencyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'View OperatorProficiency');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OperatorProficiency $operatorProficiency): bool
    {
        return $user->hasPermissionTo(permission: 'View OperatorProficiency');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Create OperatorProficiency');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OperatorProficiency $operatorProficiency): bool
    {
        return $user->hasPermissionTo(permission: 'Edit OperatorProficiency');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OperatorProficiency $operatorProficiency): bool
    {
        return $user->hasPermissionTo(permission: 'Delete OperatorProficiency');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Delete OperatorProficiency');
    }
    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, OperatorProficiency $operatorProficiency): bool
    {
        return $user->hasPermissionTo(permission: 'Delete OperatorProficiency');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, OperatorProficiency $operatorProficiency): bool
    {
        return false;
    }
}
