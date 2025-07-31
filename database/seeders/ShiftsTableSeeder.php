<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShiftsTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1); // Default to 1 if not set

        $shifts = [
            [
                'factory_id' => $factoryId,
                'name' => '1st Shift',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00', // 8 hours
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'factory_id' => $factoryId,
                'name' => '2nd Shift',
                'start_time' => '14:00:00',
                'end_time' => '22:00:00', // 8 hours
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'factory_id' => $factoryId,
                'name' => '3rd Shift',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00', // 8 hours (overnight)
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'factory_id' => $factoryId,
                'name' => 'Half Day Shift',
                'start_time' => '10:00:00',
                'end_time' => '14:00:00', // 4 hours
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('shifts')->insert($shifts);
    }
}
