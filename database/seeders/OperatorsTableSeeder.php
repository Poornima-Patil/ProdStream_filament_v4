<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Shift;
use App\Models\OperatorProficiency;
use App\Models\Operator;

class OperatorsTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);

        // Get all users with 'Operator' role in this factory
        $operatorUsers = User::whereHas('roles', function ($query) {
                $query->where('name', 'Operator');
            })
            ->where('factory_id', $factoryId)
            ->get();

        if ($operatorUsers->isEmpty()) {
            $this->command->error('No operator users found in the users table.');
            return;
        }

        // Get all available shifts and proficiencies
        $shifts = Shift::where('factory_id', $factoryId)->get();
        $proficiencies = OperatorProficiency::where('factory_id', $factoryId)->get();

        if ($shifts->isEmpty() || $proficiencies->isEmpty()) {
            $this->command->error('Shifts or Proficiencies not found.');
            return;
        }

        $createdCount = 0;

        foreach ($operatorUsers as $index => $user) {
            // Skip if operator already exists
            $exists = Operator::where('user_id', $user->id)
                ->where('factory_id', $factoryId)
                ->exists();

            if ($exists) {
                continue; // avoid duplication
            }

            $shift = $shifts[$index % $shifts->count()];
            $proficiency = $proficiencies[$index % $proficiencies->count()];

            Operator::create([
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'operator_proficiency_id' => $proficiency->id,
                'factory_id' => $factoryId,
            ]);

            $createdCount++;
            $this->command->info("Created Operator for user_id {$user->id}");
        }

        $this->command->info("Operators seeding completed. New operators created: {$createdCount}");
    }
}
