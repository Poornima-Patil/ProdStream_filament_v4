<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerInformationTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);
        $customerCount = env('SEED_CUSTOMER_COUNT', 5); // Default to 5 if not set
        $startDate = Carbon::parse(env('SEED_WORK_START_DATE', now()->startOfMonth()));
        $endDate = $startDate->copy()->addDays(14); // 15 days including start

        // Find the last customer_id for this factory
        $lastCustomer = DB::table('customer_information')
            ->where('factory_id', $factoryId)
            ->orderByDesc('customer_id')
            ->first();
        $customerId = $lastCustomer ? $lastCustomer->customer_id + 10 : 1010;

        $added = 0;
        for ($customerIndex = 1; $customerIndex <= $customerCount; $customerIndex++) {
            $name = fake()->company;
            $address = fake()->address;

            // Check for duplicate by name and factory_id
            $exists = DB::table('customer_information')
                ->where('factory_id', $factoryId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                continue; // Skip duplicate
            }

            $randomDate = Carbon::createFromTimestamp(rand($startDate->timestamp, $endDate->timestamp));

            DB::table('customer_information')->insert([
                'customer_id' => $customerId,
                'factory_id' => $factoryId,
                'name' => $name,
                'address' => $address,
                'created_at' => $randomDate,
                'updated_at' => $randomDate,
            ]);
            $added++;
            $customerId += 10;
        }

        $this->command->info("Added $added new customers for factory_id $factoryId (no duplicates).");
    }
}
