<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shift;

class ShiftsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1); // Default to 1 if not set

        $shifts = [
            ['name' => '1st Shift (06:00 - 14:00)', 'factory_id' => $factoryId],
            ['name' => '2nd Shift (14:00 - 22:00)', 'factory_id' => $factoryId],
            ['name' => '3rd Shift (22:00 - 06:00)', 'factory_id' => $factoryId],
            ['name' => 'Maintenance Shift (10:00 - 14:00)', 'factory_id' => $factoryId],
        ];

        foreach ($shifts as $shift) {
            Shift::create($shift);
        }
    }
}
