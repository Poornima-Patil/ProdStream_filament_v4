<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BomsTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);

        $purchaseOrders = DB::table('purchase_orders')->get();
        $machineGroups = DB::table('machine_groups')->pluck('id')->toArray();
        $operatorProficiencies = DB::table('operator_proficiencies')->pluck('id')->toArray();

        $machineGroupCount = count($machineGroups);
        $operatorProficiencyCount = count($operatorProficiencies);
        $statusOptions = [0, 1, 2]; // Inactive, Active, Complete

        $bomIndex = 1;

    foreach ($purchaseOrders as $index => $po) {
    $machineGroupId = $machineGroups[$index % $machineGroupCount];
    $operatorProficiencyId = $operatorProficiencies[$index % $operatorProficiencyCount];

    $leadTime = Carbon::parse($po->delivery_target_date)->subDays(2);
    $bomCreatedAt = Carbon::parse($po->created_at)->addDay();

    DB::table('boms')->insert([
        'unique_id' => 'BOM-' . str_pad($bomIndex++, 4, '0', STR_PAD_LEFT),
        'purchase_order_id' => $po->id,
        'machine_group_id' => $machineGroupId,
        'operator_proficiency_id' => $operatorProficiencyId,
        'lead_time' => $leadTime,
        'status' => 1, // ✅ Always Active
        'factory_id' => $factoryId,
        'created_at' => $bomCreatedAt,
        'updated_at' => $bomCreatedAt,
    ]);
}


    


    }
}
