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
        $factoryId = env('SEED_FACTORY_ID', 1);

        // Fetch all non-management departments
        $departments = Department::where('factory_id', $factoryId)
            ->where('name', '!=', 'Management')
            ->get();

        $departmentCount = $departments->count();

        if ($departmentCount === 0) {
            $this->command->error("No departments found for factory_id: $factoryId");
            return;
        }

        // Define total number of operators to create
        $totalOperators = 12;

        // Calculate distribution of operators across departments
        $operatorsPerDepartment = intdiv($totalOperators, $departmentCount);
        $remaining = $totalOperators % $departmentCount;

        $operatorIndex = 1;

        foreach ($departments as $index => $department) {
            $operatorsToCreate = $operatorsPerDepartment + ($index < $remaining ? 1 : 0);

            // Create operators for this department
            for ($i = 0; $i < $operatorsToCreate; $i++) {
                $user = User::create([
                    'first_name' => fake()->firstName,
                    'last_name' => fake()->lastName,
                    'emp_id' => 'OPR' . str_pad($operatorIndex, 3, '0', STR_PAD_LEFT),
                    'email' => 'operator' . $operatorIndex . '@beta.com',
                    'password' => Hash::make('password'),
                    'factory_id' => $factoryId,
                    'department_id' => $department->id,
                    'email_verified_at' => now(),
                ]);
                $user->assignRole('operator');
                $operatorIndex++;
            }

            // Create 1 manager for this department
            $manager = User::create([
                'first_name' => 'Manager',
                'last_name' => 'Dept' . $department->id,
                'emp_id' => 'MGR' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'email' => 'manager' . ($index + 1) . '@beta.com',
                'password' => Hash::make('password'),
                'factory_id' => $factoryId,
                'department_id' => $department->id,
                'email_verified_at' => now(),
            ]);
            $manager->assignRole('manager');
        }

        $this->command->info("Created 12 operators and {$departmentCount} managers across departments.");
    }
}
