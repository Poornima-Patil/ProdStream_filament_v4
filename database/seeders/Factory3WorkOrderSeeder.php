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
        $factoryId = (int) env('SEED_FACTORY_ID', 1);

        $this->command->info("Creating {$count} work orders for Factory {$factoryId}...");

        // Get available resources
        $machines = Machine::where('factory_id', $factoryId)->pluck('id')->toArray();
        $operators = Operator::where('factory_id', $factoryId)->pluck('id')->toArray();
        $boms = Bom::where('factory_id', $factoryId)->pluck('unique_id', 'id')->toArray();

        if (empty($machines) || empty($operators) || empty($boms)) {
            $this->command->error("Factory {$factoryId} needs machines, operators, and BOMs first!");
            return;
        }

        $workOrders = [];
        $batchSize = 100;

        $targetDate = Carbon::today();
        $bomIds = array_keys($boms);
        $coreStatuses = ['Start', 'Setup', 'Assigned', 'Hold', 'Completed'];
        $reservedStartSetupMachines = [];

        foreach ($coreStatuses as $status) {
            if (count($workOrders) >= $count) {
                break;
            }

            $machineId = $this->pickMachineIdForStatus($machines, $status, $reservedStartSetupMachines);
            if ($machineId === null) {
                // Fallback to a non-restricted status if all machines are taken for Start/Setup
                $status = 'Assigned';
                $machineId = $this->pickMachineIdForStatus($machines, $status, $reservedStartSetupMachines);
            }

            $createdAt = $this->randomDateTimeOn($targetDate);
            $startTime = $this->generateStartTime($createdAt);
            $endTimeData = $this->resolveTimesForStatus($status, $startTime);

            $qty = rand(50, 300);
            $okQtys = $this->resolveOkQuantity($status, $qty);
            $scrappedQtys = max(0, min($qty - $okQtys, rand(0, (int) ($qty * 0.1))));

            $bomId = $bomIds[array_rand($bomIds)];
            $bomUniqueId = $boms[$bomId];
            $operatorId = $operators[array_rand($operators)];

            $workOrders[] = [
                'bom_id' => $bomId,
                'factory_id' => $factoryId,
                'machine_id' => $machineId,
                'operator_id' => $operatorId,
                'unique_id' => WorkOrder::generateUniqueId($factoryId, $bomUniqueId, $createdAt),
                'qty' => $qty,
                'ok_qtys' => $okQtys,
                'scrapped_qtys' => $scrappedQtys,
                'status' => $status,
                'start_time' => $startTime,
                'end_time' => $endTimeData['end_time'],
                'created_at' => $createdAt,
                'updated_at' => $endTimeData['updated_at'],
            ];
        }

        $remaining = max(0, $count - count($workOrders));
        $statuses = ['Assigned', 'Hold', 'Completed', 'Start', 'Setup'];
        $statusWeights = [
            'Assigned' => 35,
            'Hold' => 20,
            'Completed' => 30,
            'Start' => 10,
            'Setup' => 5,
        ];

        for ($i = 0; $i < $remaining; $i++) {
            $status = $this->getWeightedRandomStatus($statusWeights);
            $machineId = $this->pickMachineIdForStatus($machines, $status, $reservedStartSetupMachines);
            if ($machineId === null) {
                $status = 'Assigned';
                $machineId = $this->pickMachineIdForStatus($machines, $status, $reservedStartSetupMachines);
            }

            $createdAt = $this->randomDateTimeOn($targetDate);
            $startTime = $this->generateStartTime($createdAt);
            $endTimeData = $this->resolveTimesForStatus($status, $startTime);

            $qty = rand(10, 500);
            $okQtys = $this->resolveOkQuantity($status, $qty);
            $scrappedQtys = max(0, min($qty - $okQtys, rand(0, (int) ($qty * 0.15))));

            $bomId = $bomIds[array_rand($bomIds)];
            $bomUniqueId = $boms[$bomId];
            $operatorId = $operators[array_rand($operators)];

            $workOrders[] = [
                'bom_id' => $bomId,
                'factory_id' => $factoryId,
                'machine_id' => $machineId,
                'operator_id' => $operatorId,
                'unique_id' => WorkOrder::generateUniqueId($factoryId, $bomUniqueId, $createdAt),
                'qty' => $qty,
                'ok_qtys' => $okQtys,
                'scrapped_qtys' => $scrappedQtys,
                'status' => $status,
                'start_time' => $startTime,
                'end_time' => $endTimeData['end_time'],
                'created_at' => $createdAt,
                'updated_at' => $endTimeData['updated_at'] ?? $createdAt->copy()->addHours(rand(1, 12)),
            ];

            // Insert in batches for performance
            if (count($workOrders) >= $batchSize) {
                WorkOrder::insert($workOrders);
                $workOrders = [];
                $this->command->info("Inserted " . (($i + 1)) . " work orders...");
            }
        }

        // Insert remaining
        if (!empty($workOrders)) {
            WorkOrder::insert($workOrders);
        }

        $this->command->info("✅ Successfully created {$count} work orders for Factory {$factoryId}!");

        // Show distribution
        $distribution = WorkOrder::where('factory_id', $factoryId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->command->newLine();
        $this->command->info("Status Distribution:");
        foreach ($distribution as $status => $count) {
            $this->command->info("  {$status}: {$count}");
        }
    }

    private function randomDateTimeOn(Carbon $date, int $startHour = 6, int $endHour = 15): Carbon
    {
        $hour = rand($startHour, $endHour);
        $minute = rand(0, 59);

        return $date->copy()->setTime($hour, $minute, 0);
    }

    private function generateStartTime(Carbon $createdAt): Carbon
    {
        return $createdAt->copy()->addMinutes(rand(30, 90));
    }

    private function pickMachineIdForStatus(array $machines, string $status, array &$reservedStartSetup): ?int
    {
        if (in_array($status, ['Start', 'Setup'], true)) {
            $available = array_values(array_diff($machines, array_keys($reservedStartSetup)));
            if (empty($available)) {
                return null;
            }
            $machineId = $available[array_rand($available)];
            $reservedStartSetup[$machineId] = true;
            return $machineId;
        }

        return $machines[array_rand($machines)];
    }

    private function resolveTimesForStatus(string $status, Carbon $startTime): array
    {
        switch ($status) {
            case 'Completed':
                $endTime = $startTime->copy()->addHours(rand(2, 6));
                return [
                    'end_time' => $endTime,
                    'updated_at' => $endTime,
                ];
            case 'Setup':
                $endTime = $startTime->copy()->addHours(rand(1, 3));
                return [
                    'end_time' => $endTime,
                    'updated_at' => $endTime,
                ];
            case 'Hold':
                return [
                    'end_time' => null,
                    'updated_at' => $startTime->copy()->addHours(rand(1, 4)),
                ];
            case 'Start':
                return [
                    'end_time' => null,
                    'updated_at' => $startTime->copy()->addMinutes(rand(15, 45)),
                ];
            default:
                return [
                    'end_time' => null,
                    'updated_at' => $startTime->copy()->addHours(rand(1, 6)),
                ];
        }
    }

    private function resolveOkQuantity(string $status, int $qty): int
    {
        return match ($status) {
            'Completed' => rand((int) ($qty * 0.8), $qty),
            'Start', 'Setup' => rand((int) ($qty * 0.2), (int) ($qty * 0.5)),
            'Hold' => rand(0, (int) ($qty * 0.4)),
            default => rand((int) ($qty * 0.3), (int) ($qty * 0.7)),
        };
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
