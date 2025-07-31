<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Machine;
use App\Models\Department;
use App\Models\MachineGroup;

class MachinesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Set factory ID (default to 1 or override via .env)
        $factoryId = env('SEED_FACTORY_ID', 1);

        // Fetch department and machine group IDs for the factory
        $departmentIds = Department::where('factory_id', $factoryId)->pluck('id')->toArray();
        $machineGroupIds = MachineGroup::where('factory_id', $factoryId)->pluck('id')->toArray();

        if (empty($departmentIds) || empty($machineGroupIds)) {
            $this->command->error("Departments or Machine Groups not found for factory ID: $factoryId");
            return;
        }

        // Create 50 machines
        for ($i = 1; $i <= 50; $i++) {
            Machine::create([
                'name' => 'Machine-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'assetId' => 'AST-' . strtoupper(Str::random(8)),
                'factory_id' => $factoryId,
                'department_id' => $departmentIds[array_rand($departmentIds)],
                'machine_group_id' => $machineGroupIds[array_rand($machineGroupIds)],
                //'status' => rand(0, 1),
                'status'=> 1            ]);
        }

        $this->command->info('✅ 50 machines created and assigned to departments and machine groups.');
    }
}
