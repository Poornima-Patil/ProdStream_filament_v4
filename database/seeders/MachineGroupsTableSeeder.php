<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MachineGroupsTableSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $factoryId = env('SEED_FACTORY_ID', 1);

        $groups = [
            [
                'group_name' => 'CNC Machines',
                'description' => 'Computer-controlled cutting machines used for precise manufacturing.',
            ],
            [
                'group_name' => 'Injection Molding Units',
                'description' => 'Machines for shaping plastic or metal by injecting material into a mold.',
            ],
            [
                'group_name' => 'Assembly Robots',
                'description' => 'Automated robotic arms used for assembling parts and products.',
            ],
            [
                'group_name' => 'Laser Cutting Systems',
                'description' => 'Machines that use high-powered lasers to cut materials with precision.',
            ],
        ];

        foreach ($groups as $group) {
            DB::table('machine_groups')->insert([
                'group_name' => $group['group_name'],
                'description' => $group['description'],
                'factory_id' => $factoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
