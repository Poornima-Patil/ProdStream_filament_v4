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
        $customerCount = env('SEED_CUSTOMER_COUNT', 5); // Default to 6 if not set
        $months = env('SEED_DURATION', 1);
        $startDate = Carbon::parse(env('SEED_WORK_START_DATE', now()->startOfMonth()));

        $customersPerMonth = ceil($customerCount / $months);
        $customerIndex = 1;

        for ($month = 0; $month < $months; $month++) {
        $monthStart = $startDate->copy()->addMonths($month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

            // If it's the last month, cut off the last 7 days
            if ($month === $months - 1) {
                $monthEnd->subDays(7);
            }

            for ($i = 0; $i < $customersPerMonth && $customerIndex <= $customerCount; $i++, $customerIndex++) {
                $randomDate = Carbon::createFromTimestamp(rand($monthStart->timestamp, $monthEnd->timestamp));

                DB::table('customer_information')->insert([
                    'customer_id' => $customerIndex * 10 + 1000,
                    'factory_id' => $factoryId,
                    'name' => fake()->company,
                    'address' => fake()->address,
                    'created_at' => $randomDate,
                    'updated_at' => $randomDate,
                ]);
            }
        }
    }
}
