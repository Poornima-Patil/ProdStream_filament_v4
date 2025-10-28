<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $faker = app(Generator::class);
        $factoryId = env('SEED_FACTORY_ID', 1);
        $newOperators = env('NEW_OPERATORS_COUNT', 0);

        $this->command->info('=== UsersTableSeeder Debug Info ===');
        $this->command->info("Factory ID: {$factoryId}");
        $this->command->info("New Operators Count: {$newOperators}");

        // Fetch all non-management departments
        $this->command->info("Looking for departments in factory_id: {$factoryId}");
        $allDepartments = Department::where('factory_id', $factoryId)->get();
        $this->command->info("Total departments in factory {$factoryId}: ".$allDepartments->count());

        foreach ($allDepartments as $dept) {
            $this->command->info("  - Department: {$dept->name} (ID: {$dept->id})");
        }

        $departments = Department::where('factory_id', $factoryId)
            ->where('name', '!=', 'Management')
            ->get();

        $this->command->info('Non-Management departments found: '.$departments->count());

        if ($departments->isEmpty()) {
            $this->command->error("No departments found for factory_id: $factoryId");

            return;
        }

        // --- Step 1: Ensure managers exist (1 per department) ---
        $this->command->info('=== Step 1: Creating Managers ===');
        foreach ($departments as $index => $department) {
            $managerEmpId = 'MGR'.str_pad($index + 1, 3, '0', STR_PAD_LEFT);
            $this->command->info("Processing department: {$department->name} -> Manager ID: {$managerEmpId}");

            $existingManager = User::where('emp_id', $managerEmpId)->where('factory_id', $factoryId)->first();
            if ($existingManager) {
                $this->command->info("Manager {$managerEmpId} already exists (User ID: {$existingManager->id})");
            } else {
                $this->command->info("Creating new manager {$managerEmpId}...");
                try {
                    $manager = User::create([
                        'first_name' => 'Manager',
                        'last_name' => 'Dept'.$department->id,
                        'emp_id' => $managerEmpId,
                        'email' => 'manager'.($index + 1).'.f'.$factoryId.'@beta.com',
                        'password' => Hash::make('password'),
                        'factory_id' => $factoryId,
                        'department_id' => $department->id,
                        'email_verified_at' => now(),
                    ]);

                    $this->command->info("User created with ID: {$manager->id}. Assigning role...");
                    $manager->assignRole('Manager');

                    // Add manager to factory relationship (pivot table)
                    $manager->factories()->syncWithoutDetaching([$factoryId]);

                    $this->command->info("Created Manager {$managerEmpId} successfully");
                } catch (\Exception $e) {
                    $this->command->error("Failed to create manager {$managerEmpId}: ".$e->getMessage());
                }
            }
        }

        // --- Step 2: Find last operator index ---
        $lastOperator = User::where('factory_id', $factoryId)
            ->where('emp_id', 'like', 'OPR%')
            ->orderBy('emp_id', 'desc')
            ->first();

        $nextIndex = $lastOperator
            ? intval(str_replace('OPR', '', $lastOperator->emp_id)) + 1
            : 1;

        // --- Step 3: Add new operators if requested ---
        $this->command->info('=== Step 3: Creating Operators (if needed) ===');
        if ($newOperators > 0) {
            $this->command->info('Looking for managers to assign operators...');

            // Check all users in factory first
            $allFactoryUsers = User::where('factory_id', $factoryId)->get();
            $this->command->info("Total users in factory {$factoryId}: ".$allFactoryUsers->count());

            // Check users with MGR emp_id pattern
            $mgrUsers = User::where('factory_id', $factoryId)->where('emp_id', 'like', 'MGR%')->get();
            $this->command->info('Users with MGR emp_id pattern: '.$mgrUsers->count());

            // Check users with manager role
            $managers = User::where('factory_id', $factoryId)->role('Manager')->get();
            $this->command->info("Users with 'Manager' role: ".$managers->count());

            if ($managers->isEmpty()) {
                $this->command->error('No managers found to assign new operators.');
                $this->command->info("Checking if 'manager' role exists...");

                try {
                    $managerRole = \Spatie\Permission\Models\Role::where('name', 'Manager')->first();
                    if ($managerRole) {
                        $this->command->info("Manager role exists (ID: {$managerRole->id})");
                    } else {
                        $this->command->error('Manager role does NOT exist in database!');
                    }
                } catch (\Exception $e) {
                    $this->command->error('Error checking manager role: '.$e->getMessage());
                }

                return;
            }

            $managerIndex = 0;

            for ($i = 0; $i < $newOperators; $i++) {
                $empId = 'OPR'.str_pad($nextIndex + $i, 3, '0', STR_PAD_LEFT);
                $email = 'operator'.($nextIndex + $i).'.f'.$factoryId.'@beta.com';

                if (! User::where('emp_id', $empId)->where('factory_id', $factoryId)->exists()) {
                    $manager = $managers[$managerIndex % $managers->count()];

                    $operator = User::create([
                        'first_name' => $faker->firstName,
                        'last_name' => $faker->lastName,
                        'emp_id' => $empId,
                        'email' => $email,
                        'password' => Hash::make('password'),
                        'factory_id' => $factoryId,
                        'department_id' => $manager->department_id,
                        'email_verified_at' => now(),
                    ]);
                    $operator->assignRole('Operator');

                    // Add operator to factory relationship (pivot table)
                    $operator->factories()->syncWithoutDetaching([$factoryId]);

                    $this->command->info("Added Operator {$empId} under Manager {$manager->emp_id}");
                }

                $managerIndex++;
            }
        }

        $this->command->info("Seeder completed for factory_id: {$factoryId}");
    }
}
