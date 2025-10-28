<?php

namespace Database\Seeders;

use App\Models\Bom;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BomsTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);

        // Fetch all purchase orders sorted by creation date
        $purchaseOrders = DB::table('purchase_orders')
            ->where('factory_id', $factoryId)
            ->orderBy('created_at')
            ->get();

        // Machine and operator references
        $machineGroups = DB::table('machine_groups')
            ->where('factory_id', $factoryId)
            ->pluck('id')
            ->toArray();

        $operatorProficiencies = DB::table('operator_proficiencies')
            ->where('factory_id', $factoryId)
            ->pluck('id')
            ->toArray();

        $machineGroupCount = count($machineGroups);
        $operatorProficiencyCount = count($operatorProficiencies);

        $serialCounters = [];

        foreach ($purchaseOrders as $index => $po) {
            // ✅ Use Bom model instead of DB::table
            $existingBom = Bom::where('factory_id', $factoryId)
                ->where('purchase_order_id', $po->id)
                ->first();

            if ($existingBom) {
                // If BOM exists but has no files → attach files
                if (
                    $existingBom->getMedia('requirement_pkg')->count() === 0 ||
                    $existingBom->getMedia('process_flowchart')->count() === 0
                ) {
                    $this->attachFiles($existingBom);
                }

                continue; // Skip creating new BOM
            }

            $bomCreatedAt = Carbon::parse($po->created_at)->addDay();
            $monthYear = $bomCreatedAt->format('mY');

            // Reset serial number for new month
            if (! isset($serialCounters[$monthYear])) {
                $lastUniqueId = Bom::where('unique_id', 'like', "O%{$monthYear}%")
                    ->orderByDesc('unique_id')
                    ->value('unique_id');

                if ($lastUniqueId) {
                    preg_match('/^O(\d{4})_/', $lastUniqueId, $matches);
                    $serialCounters[$monthYear] = isset($matches[1])
                        ? intval($matches[1]) + 1
                        : 1;
                } else {
                    $serialCounters[$monthYear] = 1;
                }
            }
            $serial = str_pad($serialCounters[$monthYear]++, 4, '0', STR_PAD_LEFT);

            // Fetch related data from foreign tables
            $customer = DB::table('customer_information')
                ->where('factory_id', $factoryId)
                ->where('id', $po->cust_id)
                ->first();

            $part = DB::table('part_numbers')
                ->where('factory_id', $factoryId)
                ->where('id', $po->part_number_id)
                ->first();

            // Skip if data is missing
            if (! $customer || ! $part) {
                continue;
            }

            // Construct unique_id
            $uniqueId =
                'O'.
                $serial.
                '_'.
                $monthYear.
                '_'.
                $customer->customer_id.
                '_'.
                $part->partnumber.
                '_'.
                $part->revision;

            // Assign machine group and operator proficiency in round-robin
            $machineGroupId = $machineGroups[$index % $machineGroupCount];
            $operatorProficiencyId =
                $operatorProficiencies[$index % $operatorProficiencyCount];

            // Calculate lead time
            $leadTime = Carbon::parse($po->delivery_target_date)->subDays(2);

            // ✅ Use Bom model to create instead of DB::table
            $bom = Bom::create([
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

            // Attach files
            $this->attachFiles($bom);
        }
    }

    /**
     * Attach random files to a BOM
     */
    protected function attachFiles(Bom $bom): void
    {
        $sampleFiles = [
            'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
            'https://www.w3.org/People/mimasa/test/imgformat/img/w3c_home.jpg',
            'https://www.learningcontainer.com/wp-content/uploads/2020/07/Sample-PNG-Image.png',
            'https://file-examples.com/storage/fe9db5fbf07e1d39b2d58b8/2017/02/file_example_XLS_10.xls',
        ];

        // --- requirement_pkg files ---
        if ($bom->getMedia('requirement_pkg')->count() === 0) {
            $randomFiles = (array) array_rand($sampleFiles, rand(2, 3));
            foreach ($randomFiles as $key) {
                $this->downloadAndAttach(
                    $bom,
                    $sampleFiles[$key],
                    'requirement_pkg'
                );
            }
        }

        // --- process_flowchart files ---
        if ($bom->getMedia('process_flowchart')->count() === 0) {
            $randomFiles = (array) array_rand($sampleFiles, rand(2, 3));
            foreach ($randomFiles as $key) {
                $this->downloadAndAttach(
                    $bom,
                    $sampleFiles[$key],
                    'process_flowchart'
                );
            }
        }
    }

    /**
     * Helper to download and attach a file
     */
    protected function downloadAndAttach(
        Bom $bom,
        string $fileUrl,
        string $collection
    ): void {
        $fileContents = Http::get($fileUrl)->body();
        $fileName = basename(parse_url($fileUrl, PHP_URL_PATH));
        $tempPath = 'temp/'.uniqid().'_'.$fileName;

        Storage::disk('public')->put($tempPath, $fileContents);

        $bom->addMedia(Storage::disk('public')->path($tempPath))
            ->preservingOriginal()
            ->toMediaCollection($collection);

        Storage::disk('public')->delete($tempPath);
    }
}
