<?php

/*namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;*/

/*class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   /* public function run(): void
    {
        $user = DB::table('users')->insert([
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'), // Hashing the password
                'email_verified_at' => now(), // Optional: set to now if you want to mark the email as verified
                'remember_token' => null, // Optional: used for "remember me" functionality
                'created_at' => now(),
                'updated_at' => now(),

            ],
        ]);
    }
}*/

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

        // Fetch 6 departments for operators and managers
        $departments = Department::where('factory_id', $factoryId)
            ->where('name', '!=', 'Management') // Optional: if Management exists
            ->take(6)
            ->get();

        if ($departments->count() < 6) {
            $this->command->error('Not enough departments found for factory_id: ' . $factoryId);
            return;
        }

        // Create 12 operators (2 per department)
        $operatorCount = 0;
        foreach ($departments as $department) {
            for ($i = 1; $i <= 2; $i++) {
                $operatorCount++;
                $user = User::create([
                    'first_name' => fake()->firstName,
                    'last_name' => fake()->lastName,
                    'emp_id' => 'OPR' . str_pad($operatorCount, 3, '0', STR_PAD_LEFT),
                    'email' => 'operator' . $operatorCount . '@beta.com',
                    'password' => Hash::make('password'),
                    'factory_id' => $factoryId,
                    'department_id' => $department->id,
                    'email_verified_at' => now(),
                ]);
                $user->assignRole('operator');
            }
        }

        // Create 6 managers — one per department
        $managerCount = 0;
        foreach ($departments as $department) {
            $managerCount++;
            $user = User::create([
                'first_name' => 'Manager',
                'last_name' => 'Dept' . $department->id,
                'emp_id' => 'MGR' . str_pad($managerCount, 3, '0', STR_PAD_LEFT),
                'email' => 'manager' . $managerCount . '@beta.com',
                'password' => Hash::make('password'),
                'factory_id' => $factoryId,
                'department_id' => $department->id,
                'email_verified_at' => now(),
            ]);
            $user->assignRole('manager');
        }
    }
}
