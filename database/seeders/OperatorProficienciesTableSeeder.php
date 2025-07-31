<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OperatorProficiency;

class OperatorProficienciesTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);

        $levels = ['Fresher', 'Assist', 'Max'];
        $skills = ['Fabricator', 'Maintenance Technician', 'Quality Inspector'];

        // Create combinations: 2 per skill type
        foreach ($skills as $index => $skill) {
            // For each skill, assign 2 levels
            $assignedLevels = array_slice($levels, 0, 2); // e.g., Fresher, Assist

            if ($index == 2) { // For last skill (Quality Inspector), assign Max
                $assignedLevels = ['Assist', 'Max'];
            }

            foreach ($assignedLevels as $level) {
                OperatorProficiency::firstOrCreate([
                    'proficiency' => "$level $skill",
                    'factory_id' => $factoryId,
                ], [
                    'description' => "Operator with $level experience in $skill.",
                ]);
            }
        }
    }
}
