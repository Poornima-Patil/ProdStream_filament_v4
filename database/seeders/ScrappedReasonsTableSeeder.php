<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScrappedReasonsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);
        $now = Carbon::now();
        $count = 10; // Number of generic reasons

        // Step 1: First 10 generic scrapped reasons
        for ($i = 1; $i <= $count; $i++) {
            $codeNumber = str_pad((string)$i, 3, '0', STR_PAD_LEFT); // '001' to '010'
            $code = "SCR{$codeNumber}";
            $description = "Scrapped Reason #{$codeNumber}";

            DB::table('scrapped_reasons')->insert([
                'code' => $code,
                'description' => $description,
                'factory_id' => $factoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Step 2: Additional 3 specific scrapped reasons
        $additionalReasons = [
            'Parts Out of Control Limits',
            'Parts observed to be Damaged',
            'Threads with chatter',
        ];

        foreach ($additionalReasons as $index => $description) {
            $codeNumber = str_pad((string)($count + $index + 1), 3, '0', STR_PAD_LEFT); // '011' to '013'
            $code = "SCR{$codeNumber}";

            DB::table('scrapped_reasons')->insert([
                'code' => $code,
                'description' => $description,
                'factory_id' => $factoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
