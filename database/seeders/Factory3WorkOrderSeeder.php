<?php

namespace Database\Seeders;

use App\Models\Bom;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class Factory3WorkOrderSeeder extends Seeder
{
    public function run(int $count = 10000): void
    {
        $factoryId = 1; // Using Factory 1 (Alpha)

        $this->command->info("Creating {$count} work orders for Factory {$factoryId}...");

        // Get available resources
        $machines = Machine::where('factory_id', $factoryId)->pluck('id')->toArray();
        $operators = Operator::where('factory_id', $factoryId)->pluck('id')->toArray();
        $boms = Bom::where('factory_id', $factoryId)->pluck('id')->toArray();

        if (empty($machines) || empty($operators) || empty($boms)) {
            $this->command->error('Factory 1 needs machines, operators, and BOMs first!');

            return;
        }

        $statuses = ['Assigned', 'Start', 'Hold', 'Completed', 'Waiting'];
        $statusWeights = [
            'Assigned' => 20,  // 20%
            'Start' => 15,     // 15%
            'Hold' => 10,      // 10%
            'Completed' => 50, // 50%
            'Waiting' => 5,    // 5%
        ];

        $startDate = Carbon::now()->subMonths(3);
        $endDate = Carbon::now();

        $workOrders = [];
        $batchSize = 100;

        for ($i = 0; $i < $count; $i++) {
            $status = $this->getWeightedRandomStatus($statusWeights);
            $createdAt = Carbon::instance(fake()->dateTimeBetween($startDate, $endDate));

            $startTime = $createdAt->copy()->addHours(rand(1, 24));
            $duration = rand(2, 48); // 2-48 hours
            $endTime = $startTime->copy()->addHours($duration);

            $qty = rand(10, 500);
            $okQtys = $status === 'Completed' ? rand((int) ($qty * 0.7), $qty) : rand(0, (int) ($qty * 0.5));
            $scrappedQtys = rand(0, (int) ($qty * 0.1));

            $workOrders[] = [
                'bom_id' => $boms[array_rand($boms)],
                'factory_id' => $factoryId,
                'machine_id' => $machines[array_rand($machines)],
                'operator_id' => $operators[array_rand($operators)],
                'unique_id' => 'WO-F1-'.str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                'qty' => $qty,
                'ok_qtys' => $okQtys,
                'scrapped_qtys' => $scrappedQtys,
                'status' => $status,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'created_at' => $createdAt,
                'updated_at' => $status === 'Completed' ? $endTime : $createdAt->copy()->addHours(rand(1, 12)),
            ];

            // Insert in batches for performance
            if (count($workOrders) >= $batchSize) {
                WorkOrder::insert($workOrders);
                $workOrders = [];
                $this->command->info('Inserted '.(($i + 1)).' work orders...');
            }
        }

        // Insert remaining
        if (! empty($workOrders)) {
            WorkOrder::insert($workOrders);
        }

        $this->command->info("✅ Successfully created {$count} work orders for Factory 1!");

        // Show distribution
        $distribution = WorkOrder::where('factory_id', $factoryId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->command->newLine();
        $this->command->info('Status Distribution:');
        foreach ($distribution as $status => $count) {
            $this->command->info("  {$status}: {$count}");
        }
    }

    private function getWeightedRandomStatus(array $weights): string
    {
        $rand = rand(1, 100);
        $sum = 0;

        foreach ($weights as $status => $weight) {
            $sum += $weight;
            if ($rand <= $sum) {
                return $status;
            }
        }

        return 'Assigned';
    }
}
