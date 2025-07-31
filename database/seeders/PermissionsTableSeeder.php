<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions
        $permissions = [
            /*'View Bom',
            'Create Bom',
            'Edit Bom',
            'Delete Bom',

            'View Department',
            'Create Department',
            'Edit Department',
            'Delete Department',

            'View Machine',
            'Create Machine',
            'Edit Machine',
            'Delete Machine',

            'View OperatorProficiency',
            'Create OperatorProficiency',
            'Edit OperatorProficiency',
            'Delete OperatorProficiency',

            'View Operator',
            'Create Operator',
            'Edit Operator',
            'Delete Operator',

            'View PartNumber',
            'Create PartNumber',
            'Edit PartNumber',
            'Delete PartNumber',

            'View Permission',
            'Create Permission',
            'Edit Permission',
            'Delete Permission',

            'View PurchaseOrder',
            'Create PurchaseOrder',
            'Edit PurchaseOrder',
            'Delete PurchaseOrder',

            'View Role',
            'Create Role',
            'Edit Role',
            'Delete Role',

            'View ScrappedReason',
            'Create ScrappedReason',
            'Edit ScrappedReason',
            'Delete ScrappedReason',

            'View Shift',
            'Create Shift',
            'Edit Shift',
            'Delete Shift',

            'View User',
            'Create User',
            'Edit User',
            'Delete User',

            'View WorkOrder',
            'Create WorkOrder',
            'Edit WorkOrder',
            'Delete WorkOrder',

            'Create Factory',
            'View Factory',
            'Edit Factory',
            'Delete Factory',*/

            'View Customer Information',
            'Create Customer Information',
            'Edit Customer Information',
            'Delete Customer Information',
        ];

        // Create role if it doesn't exist
        $role = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'factory_id' => 1,
        ]);

        foreach ($permissions as $permission) {
            // Extract group name by removing the first word
            $words = explode(' ', $permission, 2);
            $groupName = $words[1] ?? $permission;

            // Create permission
            $perm = Permission::firstOrCreate([
                'name' => $permission,
                'group' => $groupName,
                'guard_name' => 'web',
                'factory_id' => 1,
            ]);

            // Assign permission to role
            $perm->assignRole($role);
        }

        // Assign role to user
        $user = User::find(1);
        if ($user) {
            $user->assignRole('Super Admin');
        }
    }
}

