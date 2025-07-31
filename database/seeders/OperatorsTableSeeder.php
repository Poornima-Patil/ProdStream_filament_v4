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

        // Get all users with 'operator' role in this factory
        $operatorUsers = User::whereHas('roles', function ($query) {
                $query->where('name', 'operator');
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

        foreach ($operatorUsers as $index => $user) {
            $shift = $shifts[$index % $shifts->count()];
            $proficiency = $proficiencies[$index % $proficiencies->count()];

            Operator::create([
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'operator_proficiency_id' => $proficiency->id,
                'factory_id' => $factoryId,
            ]);
        }

        $this->command->info("Seeded {$operatorUsers->count()} operators with shift and proficiency assignments.");
    }
}
