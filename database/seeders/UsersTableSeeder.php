<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $faker = $this->faker;
        $factoryId   = env('SEED_FACTORY_ID', 1);
        $newOperators = env('NEW_OPERATORS_COUNT', 0);

        // Fetch all non-management departments
        $departments = Department::where('factory_id', $factoryId)
            ->where('name', '!=', 'Management')
            ->get();

        if ($departments->isEmpty()) {
            $this->command->error("No departments found for factory_id: $factoryId");
            return;
        }

        // --- Step 1: Ensure managers exist (1 per department) ---
        foreach ($departments as $index => $department) {
            $managerEmpId = 'MGR' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

            if (!User::where('emp_id', $managerEmpId)->exists()) {
                $manager = User::create([
                    'first_name'      => 'Manager',
                    'last_name'       => 'Dept' . $department->id,
                    'emp_id'          => $managerEmpId,
                    'email'           => 'manager' . ($index + 1) . '@beta.com',
                    'password'        => Hash::make('password'),
                    'factory_id'      => $factoryId,
                    'department_id'   => $department->id,
                    'email_verified_at' => now(),
                ]);
                $manager->assignRole('manager');
                $this->command->info("Created Manager {$managerEmpId}");
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
        if ($newOperators > 0) {
            $managers = User::where('factory_id', $factoryId)->role('manager')->get();

            if ($managers->isEmpty()) {
                $this->command->error("No managers found to assign new operators.");
                return;
            }

            $managerIndex = 0;

            for ($i = 0; $i < $newOperators; $i++) {
                $empId = 'OPR' . str_pad($nextIndex + $i, 3, '0', STR_PAD_LEFT);
                $email = 'operator' . ($nextIndex + $i) . '@beta.com';

                if (!User::where('emp_id', $empId)->exists()) {
                    $manager = $managers[$managerIndex % $managers->count()];

                    $operator = User::create([
                        'first_name'      => $faker->firstName,
                        'last_name'       => $faker->lastName,
                        'emp_id'          => $empId,
                        'email'           => $email,
                        'password'        => Hash::make('password'),
                        'factory_id'      => $factoryId,
                        'department_id'   => $manager->department_id,
                        'email_verified_at' => now(),
                    ]);
                    $operator->assignRole('operator');
                    $this->command->info("Added Operator {$empId} under Manager {$manager->emp_id}");
                }

                $managerIndex++;
            }
        }

        $this->command->info("Seeder completed for factory_id: {$factoryId}");
    }
}
