<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseOrdersTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);
        $customers = DB::table('customer_information')->where('factory_id', $factoryId)->get();
        $partNumbers = DB::table('part_numbers')->where('factory_id', $factoryId)->get();

        if ($partNumbers->count() < count($customers) * 2) {
            throw new \Exception('At least '.(count($customers) * 2).' part numbers are required (2 per customer × '.count($customers).' customers).');
        }

        $unitOptions = ['Kgs', 'Numbers'];
        $unitToggle = 0;

        $purchaseOrders = collect();

        foreach ($customers as $customerIndex => $customer) {
            // Check if PO already exists for this customer
            $existingPOCount = DB::table('purchase_orders')
                ->where('factory_id', $factoryId)
                ->where('cust_id', $customer->id)
                ->count();

            if ($existingPOCount > 0) {
                continue; // Skip customers who already have POs
            }

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

        // Sort by created_at ASC
        $purchaseOrders = $purchaseOrders->sortBy('created_at')->values();

        // Assign serials per month and insert
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
                // Find last PO serial for this month in DB
                $lastUniqueId = DB::table('purchase_orders')
                    ->where('unique_id', 'like', "S%_{$monthYear}_%")
                    ->orderByDesc('unique_id')
                    ->value('unique_id');
                if ($lastUniqueId) {
                    // Extract serial from unique_id (e.g. S0005_082025_...)
                    preg_match('/^S(\d{4})_/', $lastUniqueId, $matches);
                    $poIndex = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
                } else {
                    $poIndex = 1;
                }
            }

            $serial = str_pad($poIndex++, 4, '0', STR_PAD_LEFT);
            $uniqueId = 'S'.$serial.'_'.$monthYear.'_'.$customer->customer_id.'_'.$part->partnumber.'_'.$part->revision;

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
