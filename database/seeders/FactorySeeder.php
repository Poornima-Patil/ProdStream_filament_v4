<?php

namespace Database\Seeders;

use App\Models\Factory;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FactorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $factories = [
            [
                'name' => 'Alpha',
                'slug' => 'alpha',
                'template_path' => '/templates/alpha'
            ],
            [
                'name' => 'Beta',
                'slug' => 'beta',
                'template_path' => '/templates/beta'
            ],
            [
                'name' => 'Gamma',
                'slug' => 'gamma',
                'template_path' => '/templates/gamma'
            ]
        ];

        foreach ($factories as $factoryData) {
            $this->command->info("Creating factory: {$factoryData['name']}");

            // Create factory
            $factory = Factory::firstOrCreate(
                ['slug' => $factoryData['slug']],
                $factoryData
            );

            // Create permissions for this factory
            $this->createPermissionsForFactory($factory);

            // Create roles for this factory
            $this->createRolesForFactory($factory);

            // Create management department
            $managementDept = $this->createManagementDepartment($factory);

            // Create Factory Admin user
            $this->createFactoryAdminUser($factory, $managementDept);

            $this->command->info("✅ Factory '{$factory->name}' setup completed");
        }

        $this->command->info("🎉 All factories created successfully!");
    }

    /**
     * Create permissions for a specific factory
     */
    private function createPermissionsForFactory(Factory $factory): void
    {
        // Use exact permissions from PermissionsTableSeeder_new.php
        $permissions = [
            'View Bom',
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
            'Delete Factory',

            'Create HoldReason',
            'View HoldReason',
            'Edit HoldReason',
            'Delete HoldReason',

            'Create MachineGroup',
            'Edit MachineGroup',
            'View MachineGroup',
            'Delete MachineGroup',

            'View Customer Information',
            'Create Customer Information',
            'Edit Customer Information',
            'Delete Customer Information',

            'View WorkOrderGroup',
            'Create WorkOrderGroup',
            'Edit WorkOrderGroup',
            'Delete WorkOrderGroup',
        ];

        foreach ($permissions as $permission) {
            // Extract group name by removing the first word (same logic as original seeder)
            $words = explode(' ', $permission, 2);
            $groupName = $words[1] ?? $permission;

            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
                'factory_id' => $factory->id,
            ], [
                'group' => $groupName
            ]);
        }

        $this->command->info("  ✅ Created permissions for {$factory->name}");
    }

    /**
     * Create roles for a specific factory
     */
    private function createRolesForFactory(Factory $factory): void
    {
        $roles = [
            [
                'name' => 'Factory Admin',
                'permissions' => 'ALL' // Special marker for all permissions
            ],
            [
                'name' => 'Manager',
                'permissions' => [
                    'View User', 'Create User', 'Edit User',
                    'View WorkOrder', 'Create WorkOrder', 'Edit WorkOrder',
                    'View Bom', 'Create Bom', 'Edit Bom',
                    'View PartNumber', 'Create PartNumber', 'Edit PartNumber',
                    'View PurchaseOrder', 'Create PurchaseOrder', 'Edit PurchaseOrder',
                    'View Operator', 'Create Operator', 'Edit Operator',
                    'View Machine', 'Edit Machine',
                    'View Department',
                    'View Shift',
                    'View ScrappedReason', 'Create ScrappedReason', 'Edit ScrappedReason',
                    'View HoldReason', 'Create HoldReason', 'Edit HoldReason',
                    'View MachineGroup', 'Create MachineGroup', 'Edit MachineGroup',
                    'View Customer Information', 'Create Customer Information', 'Edit Customer Information',
                    'View WorkOrderGroup', 'Create WorkOrderGroup', 'Edit WorkOrderGroup',
                ]
            ],
            [
                'name' => 'Operator',
                'permissions' => [
                    'View WorkOrder', 'Edit WorkOrder',
                    'View Bom',
                    'View PartNumber',
                    'View Machine',
                    'View Operator',
                    'View Department',
                    'View Shift',
                    'View ScrappedReason',
                    'View HoldReason',
                    'View MachineGroup',
                    'View Customer Information',
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate([
                'name' => $roleData['name'],
                'guard_name' => 'web',
                'factory_id' => $factory->id,
            ]);

            // Assign permissions to role
            if ($roleData['permissions'] === 'ALL') {
                // Factory Admin gets ALL permissions for this factory
                $permissions = Permission::where('factory_id', $factory->id)->get();
            } else {
                // Other roles get specific permissions
                $permissions = Permission::where('factory_id', $factory->id)
                    ->whereIn('name', $roleData['permissions'])
                    ->get();
            }

            $role->syncPermissions($permissions);
        }

        $this->command->info("  ✅ Created roles for {$factory->name}");
    }

    /**
     * Create management department for factory
     */
    private function createManagementDepartment(Factory $factory): Department
    {
        return Department::firstOrCreate([
            'factory_id' => $factory->id,
            'name' => 'Management',
        ], [
            'description' => 'Management department for ' . $factory->name
        ]);
    }

    /**
     * Create Factory Admin user for the factory
     */
    private function createFactoryAdminUser(Factory $factory, Department $managementDept): void
    {
        $adminEmail = 'admin@factory' . $factory->id . '.com';

        $user = User::firstOrCreate([
            'email' => $adminEmail,
        ], [
            'first_name' => 'Factory',
            'last_name' => 'Admin',
            'emp_id' => 'FADM' . str_pad($factory->id, 3, '0', STR_PAD_LEFT),
            'password' => Hash::make('password'),
            'factory_id' => $factory->id,
            'department_id' => $managementDept->id,
            'email_verified_at' => now(),
        ]);

        // Assign Factory Admin role
        $factoryAdminRole = Role::where('name', 'Factory Admin')
            ->where('factory_id', $factory->id)
            ->first();

        if ($factoryAdminRole) {
            $user->assignRole($factoryAdminRole);
        }

        // Add user to factory relationship
        $user->factories()->syncWithoutDetaching([$factory->id]);

        $this->command->info("  ✅ Created Factory Admin user: {$adminEmail}");
    }
}