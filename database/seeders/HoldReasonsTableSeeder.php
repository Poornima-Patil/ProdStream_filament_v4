<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HoldReasonsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);
        $now = Carbon::now();
        $count = 10; // Number of auto-generated hold reasons

        // Step 1: Insert 10 auto-generated hold reasons
        for ($i = 1; $i <= $count; $i++) {
            $codeNumber = str_pad((string) $i, 3, '0', STR_PAD_LEFT); // '001', '002', ..., '010'
            $code = "KO{$codeNumber}";
            $description = "Hold Reason #{$codeNumber}";

            DB::table('hold_reasons')->insert([
                'code' => $code,
                'description' => $description,
                'factory_id' => $factoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Step 2: Insert 3 additional descriptive hold reasons
        $additionalReasons = [
            'Machine Breakdown',
            'Machine Inconsistent',
            'Operator Unavailable',
        ];

        foreach ($additionalReasons as $index => $description) {
            $code = 'KO'.str_pad((string) ($count + $index + 1), 3, '0', STR_PAD_LEFT); // KO011, KO012, KO013

            DB::table('hold_reasons')->insert([
                'code' => $code,
                'description' => $description,
                'factory_id' => $factoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
