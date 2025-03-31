<?php

namespace App\Policies;

use App\Models\CustomerInformation;
use App\Models\User;

class CustomerInformationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('View Customer Information');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomerInformation $customerInformation): bool
    {
        return $user->hasPermissionTo('View Customer Information');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Create Customer Information');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomerInformation $customerInformation): bool
    {
        return $user->hasPermissionTo('Edit Customer Information');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomerInformation $customerInformation): bool
    {
        return $user->hasPermissionTo('Delete Customer Information');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('Delete Customer Information');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomerInformation $customerInformation): bool
    {
        return $user->hasPermissionTo('Delete Customer Information');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomerInformation $customerInformation): bool
    {
        return $user->hasPermissionTo('Delete Customer Information');
    }
}
