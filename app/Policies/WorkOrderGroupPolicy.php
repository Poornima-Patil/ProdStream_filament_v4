<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderGroup;

class WorkOrderGroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('View WorkOrderGroup');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WorkOrderGroup $workOrderGroup): bool
    {
        return $user->hasPermissionTo('View WorkOrderGroup');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Create WorkOrderGroup');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WorkOrderGroup $workOrderGroup): bool
    {
        return $user->hasPermissionTo('Edit WorkOrderGroup');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WorkOrderGroup $workOrderGroup): bool
    {
        return $user->hasPermissionTo('Delete WorkOrderGroup');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WorkOrderGroup $workOrderGroup): bool
    {
        return $user->hasPermissionTo('Delete WorkOrderGroup');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WorkOrderGroup $workOrderGroup): bool
    {
        return false;
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('Delete WorkOrderGroup');
    }

    /**
     * Determine whether the user can activate work order groups.
     */
    public function activate(User $user, WorkOrderGroup $workOrderGroup): bool
    {
        return $user->hasPermissionTo('Edit WorkOrderGroup');
    }
}
