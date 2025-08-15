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

        for ($customerIndex = 1; $customerIndex <= $customerCount; $customerIndex++) {
            $randomDate = Carbon::createFromTimestamp(rand($startDate->timestamp, $endDate->timestamp));

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
