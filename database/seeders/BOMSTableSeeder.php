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

        // Fetch all purchase orders sorted by creation date
        $purchaseOrders = DB::table('purchase_orders')->where('factory_id', $factoryId)->orderBy('created_at')->get();

        // Machine and operator references
        $machineGroups = DB::table('machine_groups')->where('factory_id', $factoryId)->pluck('id')->toArray();
        $operatorProficiencies = DB::table('operator_proficiencies')->where('factory_id', $factoryId)->pluck('id')->toArray();
        $machineGroupCount = count($machineGroups);
        $operatorProficiencyCount = count($operatorProficiencies);

        $serialCounters = [];

        foreach ($purchaseOrders as $index => $po) {
            $bomCreatedAt = Carbon::parse($po->created_at)->addDay();
            $monthYear = $bomCreatedAt->format('mY');

            // Reset serial number for new month
            if (!isset($serialCounters[$monthYear])) {
                $serialCounters[$monthYear] = 1;
            }
            $serial = str_pad($serialCounters[$monthYear]++, 4, '0', STR_PAD_LEFT);

            // Fetch related data from foreign tables
            $customer = DB::table('customer_information')->where('factory_id', $factoryId)->where('id', $po->cust_id)->first();
            $part = DB::table('part_numbers')->where('factory_id', $factoryId)->where('id', $po->part_number_id)->first();

            // Skip if data is missing
            if (!$customer || !$part) {
                continue;
            }

            // Construct unique_id
            $uniqueId = 'O' . $serial . '_' . $monthYear . '_' . $customer->customer_id . '_' . $part->partnumber . '_' . $part->revision;

            // Assign machine group and operator proficiency in round-robin
            $machineGroupId = $machineGroups[$index % $machineGroupCount];
            $operatorProficiencyId = $operatorProficiencies[$index % $operatorProficiencyCount];

            // Calculate lead time
            $leadTime = Carbon::parse($po->delivery_target_date)->subDays(2);

            // Insert BOM record
            DB::table('boms')->insert([
                'unique_id' => $uniqueId,
                'purchase_order_id' => $po->id,
                'machine_group_id' => $machineGroupId,
                'operator_proficiency_id' => $operatorProficiencyId,
                'lead_time' => $leadTime,
                'status' => 1, // Always Active
                'factory_id' => $factoryId,
                'created_at' => $bomCreatedAt,
                'updated_at' => $bomCreatedAt,
            ]);
        }
    }
}
