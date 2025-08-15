<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PurchaseOrdersTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);
        $customers = DB::table('customer_information')->get();
        $partNumbers = DB::table('part_numbers')->get();

        if ($partNumbers->count() < count($customers) * 2) {
            throw new \Exception("At least " . (count($customers) * 2) . " part numbers are required (2 per customer × " . count($customers) . " customers).");
        }

        $unitOptions = ['Kgs', 'Numbers'];
        $unitToggle = 0;

        $purchaseOrders = collect();

        // First: Create all PO data and collect with correct created_at
        foreach ($customers as $customerIndex => $customer) {
            $assignedParts = $partNumbers->slice($customerIndex * 2, 2);

            foreach ($assignedParts as $part) {
                $qty = 20000;
                $cycleTime = $part->cycle_time;

                $purchaseOrderCreatedAt = Carbon::parse($customer->created_at)->addDays(2);

                $purchaseOrders->push([
                    'customer' => $customer,
                    'part' => $part,
                    'qty' => $qty,
                    'cycle_time' => $cycleTime,
                    'created_at' => $purchaseOrderCreatedAt,
                    'unit' => $unitOptions[$unitToggle % 2],
                ]);

                $unitToggle++;
            }
        }

        // Then: Sort by created_at ASC
        $purchaseOrders = $purchaseOrders->sortBy('created_at')->values();

        // Now: Assign serials per month and insert
        $currentMonthYear = '';
        $poIndex = 1;

        foreach ($purchaseOrders as $poData) {
            $customer = $poData['customer'];
            $part = $poData['part'];
            $qty = $poData['qty'];
            $cycleTime = $poData['cycle_time'];
            $createdAt = $poData['created_at'];
            $unit = $poData['unit'];

            $monthYear = $createdAt->format('mY');

            // Reset serial if month changes
            if ($monthYear !== $currentMonthYear) {
                $currentMonthYear = $monthYear;
                $poIndex = 1;
            }

            $serial = str_pad($poIndex++, 4, '0', STR_PAD_LEFT);
            $uniqueId = 'S' . $serial . '_' . $monthYear . '_' . $customer->customer_id . '_' . $part->partnumber . '_' . $part->revision;

            $deliveryTargetDate = Carbon::parse($customer->created_at)
                ->addSeconds($qty * $cycleTime)
                ->addDays(4)
                ->toDateString();
                $this->command->info("customer info for PO {$uniqueId} is {$customer->id},customer created at {$customer->created_at}");
$this->command->info("delivery target date for PO {$uniqueId} is {$deliveryTargetDate}");
            DB::table('purchase_orders')->insert([
                'unique_id' => $uniqueId,
                'part_number_id' => $part->id,
                'qty' => $qty,
                'Unit Of Measurement' => $unit,
                'factory_id' => $factoryId,
                'price' => rand(100, 1000),
                'cust_id' => $customer->id,
                'delivery_target_date' => $deliveryTargetDate,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                
            ]);
        }
    }
}
