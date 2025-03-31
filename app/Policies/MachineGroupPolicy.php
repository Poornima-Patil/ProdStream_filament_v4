<?php

namespace App\Policies;

use App\Models\MachineGroup;
use App\Models\User;

class MachineGroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'View MachineGroup');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MachineGroup $machineGroup): bool
    {
        return $user->hasPermissionTo(permission: 'View MachineGroup');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Create MachineGroup');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MachineGroup $machineGroup): bool
    {
        return $user->hasPermissionTo(permission: 'Edit MachineGroup');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MachineGroup $machineGroup): bool
    {
        return $user->hasPermissionTo(permission: 'Delete MachineGroup');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'Delete MachineGroup');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MachineGroup $machineGroup): bool
    {
        return $user->hasPermissionTo(permission: 'Delete MachineGroup');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MachineGroup $machineGroup): bool
    {
        return false;
    }
}
