<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1); // Change this if needed

        $departments = [
            ['name' => 'Production',      'description' => 'Handles manufacturing operations'],
            ['name' => 'Quality Assurance', 'description' => 'Ensures product quality standards'],
            ['name' => 'Maintenance',     'description' => 'Maintains machinery and equipment'],
            ['name' => 'Supply Chain',    'description' => 'Manages raw materials and logistics'],
            ['name' => 'Research & Development', 'description' => 'Develops and improves products'],
            ['name' => 'Health & Safety', 'description' => 'Oversees workplace safety and compliance'],
        ];

        foreach ($departments as $department) {
            DB::table('departments')->insert([
                'factory_id' => $factoryId,
                'name' => $department['name'],
                'description' => $department['description'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
