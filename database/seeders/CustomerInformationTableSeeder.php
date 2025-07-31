<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerInformationTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1); // Read from .env or default to 1

        for ($i = 1; $i <= 15; $i++) {
            DB::table('customer_information')->insert([
                'customer_id' => 'CUST' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'factory_id' => $factoryId,
                'name' => fake()->company,
                'address' => fake()->address,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
