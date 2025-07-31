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

        // Get 12 operator users
        $operatorUsers = User::role('operator')->take(12)->get();

        if ($operatorUsers->count() < 12) {
            $this->command->error('Not enough operator users (need 12)');
            return;
        }

        $shifts = Shift::take(4)->get();
        if ($shifts->count() < 4) {
            $this->command->error('Not enough shifts (need 4)');
            return;
        }

        $levels = ['Fresher', 'Assist', 'Max'];
        $skills = ['Fabricator', 'Maintenance Technician', 'Quality Inspector'];

        // Build combinations
        $combinations = [];

        foreach ($levels as $level) {
            foreach ($skills as $skill) {
                $proficiencyName = "$level $skill";
                $proficiency = OperatorProficiency::where('proficiency', $proficiencyName)
                    ->where('factory_id', $factoryId)
                    ->first();

                if ($proficiency) {
                    $combinations[$level][] = $proficiency;
                }
            }
        }

        // Assign 4 users to each level
        $userIndex = 0;
        foreach ($levels as $level) {
            $proficiencies = $combinations[$level];
            for ($i = 0; $i < 4; $i++) {
                $user = $operatorUsers[$userIndex++];
                $proficiency = $proficiencies[$i % count($proficiencies)];
                $shift = $shifts[$i % count($shifts)];

                Operator::create([
                    'user_id' => $user->id,
                    'operator_proficiency_id' => $proficiency->id,
                    'shift_id' => $shift->id,
                    'factory_id' => $factoryId,
                ]);
            }
        }
    }
}
