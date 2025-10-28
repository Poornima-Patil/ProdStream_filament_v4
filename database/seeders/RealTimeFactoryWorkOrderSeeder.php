<?php

namespace Database\Seeders;

use App\Models\Bom;
use App\Models\HoldReason;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RealTimeFactoryWorkOrderSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = 3;
        $workOrderCount = 20;
        $targetDate = now()->startOfDay();

        $machines = Machine::where('factory_id', $factoryId)->orderBy('id')->get();
        $primaryOperator = Operator::where('factory_id', $factoryId)->orderBy('id')->first();
        $secondaryOperator = Operator::where('factory_id', $factoryId)
            ->where('id', '!=', optional($primaryOperator)->id)
            ->orderBy('id')
            ->first();

        $primaryBom = Bom::where('factory_id', $factoryId)->orderBy('id')->first();
        $secondaryBom = Bom::where('factory_id', $factoryId)
            ->where('id', '!=', optional($primaryBom)->id)
            ->orderBy('id')
            ->first();

        if ($machines->isEmpty() || ! $primaryOperator || ! $primaryBom) {
            $this->command?->warn('RealTimeFactoryWorkOrderSeeder skipped: ensure factory has machines, operators, and BOMs.');

            return;
        }

        $secondaryOperator ??= $primaryOperator;
        $secondaryBom ??= $primaryBom;

        $holdReason = HoldReason::where('factory_id', $factoryId)->first();
        if (! $holdReason) {
            $holdReason = HoldReason::create([
                'code' => 'GEN-HOLD',
                'description' => 'General hold for diagnostic checks',
                'factory_id' => $factoryId,
            ]);
        }

        $userId = $this->resolveUserId($factoryId);

        DB::transaction(function () use (
            $factoryId,
            $targetDate,
            $machines,
            $primaryOperator,
            $secondaryOperator,
            $primaryBom,
            $secondaryBom,
            $holdReason,
            $userId,
            $workOrderCount
        ) {
            $this->seedWorkOrders(
                $factoryId,
                $targetDate,
                $machines,
                $primaryOperator,
                $secondaryOperator,
                $primaryBom,
                $secondaryBom,
                $holdReason,
                $userId,
                $workOrderCount
            );
        });

        $this->command?->info(sprintf(
            'Seeded %d work orders for Factory %d on %s.',
            $workOrderCount,
            $factoryId,
            $targetDate->toDateString()
        ));
    }

    private function seedWorkOrders(
        int $factoryId,
        Carbon $targetDate,
        $machines,
        Operator $primaryOperator,
        Operator $secondaryOperator,
        Bom $primaryBom,
        Bom $secondaryBom,
        HoldReason $holdReason,
        int $userId,
        int $count
    ): void {
        $machineIds = $machines->pluck('id')->all();
        $machineTime = [];
        $machineStartReserved = [];

        foreach ($machineIds as $machineId) {
            $machineTime[$machineId] = $targetDate->copy()->setTime(6, 0);
            $machineStartReserved[$machineId] = false;
        }

        $distribution = [
            'Completed' => 6,
            'Hold' => 5,
            'Setup' => 3,
            'Assigned' => 3,
            'Start' => 3,
        ];

        $totalPlanned = array_sum($distribution);
        if ($totalPlanned !== $count) {
            $distribution['Completed'] += ($count - $totalPlanned);
        }

        $startCapacity = count($machineIds);
        if ($distribution['Start'] > $startCapacity) {
            $excess = $distribution['Start'] - $startCapacity;
            $distribution['Start'] -= $excess;
            $distribution['Completed'] += $excess;
        }

        $finalStatuses = [];
        foreach (['Completed', 'Hold', 'Setup', 'Assigned', 'Start'] as $statusKey) {
            for ($i = 0; $i < $distribution[$statusKey]; $i++) {
                $finalStatuses[] = $statusKey;
                if (count($finalStatuses) === $count) {
                    break 2;
                }
            }
        }

        $finalStatuses = array_slice($finalStatuses, 0, $count);

        foreach ($finalStatuses as $index => $finalStatus) {
            $machineId = $this->selectMachineId($finalStatus, $machineIds, $machineTime, $machineStartReserved);
            $assignAt = $machineTime[$machineId]->copy();
            $setupAt = $assignAt->copy()->addMinutes(rand(15, 25));

            $needsStart = in_array($finalStatus, ['Start', 'Hold', 'Completed'], true);
            $startAt = $needsStart ? $setupAt->copy()->addMinutes(rand(10, 20)) : null;

            $eventsData = $this->buildStatusEvents($finalStatus, $startAt);
            $events = $eventsData['events'];
            $endTimestamp = $eventsData['final_timestamp'];

            $startTimeRecord = $needsStart && ! empty($events)
                ? $events[0]['timestamp']->copy()
                : null;

            $endTimeRecord = $finalStatus === 'Completed' && $endTimestamp
                ? $endTimestamp->copy()
                : null;

            $isPrimaryShift = $index % 2 === 0;
            $bom = $isPrimaryShift ? $primaryBom : $secondaryBom;
            $operator = $isPrimaryShift ? $primaryOperator : $secondaryOperator;

            $qty = rand(75, 140);

            $workOrder = WorkOrder::withoutEvents(function () use (
                $factoryId,
                $bom,
                $machineId,
                $operator,
                $assignAt,
                $startTimeRecord,
                $endTimeRecord,
                $qty
            ) {
                return WorkOrder::create([
                    'factory_id' => $factoryId,
                    'bom_id' => $bom->id,
                    'machine_id' => $machineId,
                    'operator_id' => $operator->id,
                    'unique_id' => WorkOrder::generateUniqueId($factoryId, $bom->unique_id, $assignAt),
                    'qty' => $qty,
                    'status' => 'Assigned',
                    'start_time' => $startTimeRecord,
                    'end_time' => $endTimeRecord,
                    'ok_qtys' => 0,
                    'scrapped_qtys' => 0,
                    'created_at' => $assignAt,
                    'updated_at' => $assignAt,
                ]);
            });

            $okQty = 0;
            $scrapQty = 0;

            $this->logTransition($workOrder, 'Assigned', $assignAt, $userId, [
                'ok_qtys' => $okQty,
                'scrapped_qtys' => $scrapQty,
            ]);

            if ($finalStatus !== 'Assigned') {
                $this->logTransition($workOrder, 'Setup', $setupAt, $userId, [
                    'ok_qtys' => $okQty,
                    'scrapped_qtys' => $scrapQty,
                ]);
            }

            foreach ($events as $event) {
                $okQty += $event['ok_delta'];
                $scrapQty += $event['scrap_delta'];

                $totalProduced = $okQty + $scrapQty;
                if ($totalProduced > $workOrder->qty) {
                    $excess = $totalProduced - $workOrder->qty;
                    if ($scrapQty >= $excess) {
                        $scrapQty -= $excess;
                    } else {
                        $remainingExcess = $excess - $scrapQty;
                        $scrapQty = 0;
                        $okQty = max(0, $okQty - $remainingExcess);
                    }
                }

                $meta = [
                    'ok_qtys' => $okQty,
                    'scrapped_qtys' => $scrapQty,
                    'hold_reason_id' => null,
                ];

                if ($event['status'] === 'Hold') {
                    $meta['hold_reason_id'] = $holdReason->id;
                }

                if ($event['status'] === 'Completed') {
                    $meta['end_time'] = $event['timestamp'];
                }

                $this->logTransition(
                    $workOrder,
                    $event['status'],
                    $event['timestamp'],
                    $userId,
                    $meta
                );
            }

            $machineTime[$machineId] = $this->advanceMachineClock(
                $events,
                $finalStatus,
                $assignAt,
                $setupAt,
                $machineTime[$machineId]
            );

            if ($finalStatus === 'Start') {
                $machineStartReserved[$machineId] = true;
            }
        }
    }

    private function selectMachineId(
        string $finalStatus,
        array $machineIds,
        array &$machineTime,
        array &$machineStartReserved
    ): int {
        if ($finalStatus === 'Start') {
            foreach ($machineIds as $machineId) {
                if (! $machineStartReserved[$machineId]) {
                    $machineStartReserved[$machineId] = true;
                    return $machineId;
                }
            }
        }

        $selectedId = $machineIds[0];
        $selectedTime = $machineTime[$selectedId];

        foreach ($machineIds as $machineId) {
            if ($machineTime[$machineId]->lt($selectedTime)) {
                $selectedId = $machineId;
                $selectedTime = $machineTime[$machineId];
            }
        }

        return $selectedId;
    }

    private function buildStatusEvents(string $finalStatus, ?Carbon $startAt): array
    {
        if ($startAt === null) {
            return ['events' => [], 'final_timestamp' => null];
        }

        $events = [];
        $current = $startAt->copy();
        $events[] = [
            'status' => 'Start',
            'timestamp' => $current->copy(),
            'ok_delta' => 0,
            'scrap_delta' => 0,
        ];

        switch ($finalStatus) {
            case 'Completed':
                $cycles = rand(1, 2);
                for ($i = 0; $i < $cycles; $i++) {
                    $holdAt = $current->copy()->addMinutes(rand(35, 60));
                    $events[] = [
                        'status' => 'Hold',
                        'timestamp' => $holdAt,
                        'ok_delta' => rand(8, 15),
                        'scrap_delta' => rand(0, 3),
                    ];

                    $resumeAt = $holdAt->copy()->addMinutes(rand(15, 30));
                    $events[] = [
                        'status' => 'Start',
                        'timestamp' => $resumeAt,
                        'ok_delta' => rand(5, 10),
                        'scrap_delta' => rand(0, 2),
                    ];

                    $current = $resumeAt;
                }

                $completeAt = $current->copy()->addMinutes(rand(45, 80));
                $events[] = [
                    'status' => 'Completed',
                    'timestamp' => $completeAt,
                    'ok_delta' => rand(20, 35),
                    'scrap_delta' => rand(1, 4),
                ];

                return ['events' => $events, 'final_timestamp' => $completeAt];

            case 'Hold':
                $holdAt = $current->copy()->addMinutes(rand(45, 70));
                $events[] = [
                    'status' => 'Hold',
                    'timestamp' => $holdAt,
                    'ok_delta' => rand(12, 20),
                    'scrap_delta' => rand(0, 3),
                ];

                $resumeAt = $holdAt->copy()->addMinutes(rand(15, 25));
                $events[] = [
                    'status' => 'Start',
                    'timestamp' => $resumeAt,
                    'ok_delta' => rand(8, 14),
                    'scrap_delta' => rand(0, 2),
                ];

                $finalHold = $resumeAt->copy()->addMinutes(rand(20, 35));
                $events[] = [
                    'status' => 'Hold',
                    'timestamp' => $finalHold,
                    'ok_delta' => rand(4, 8),
                    'scrap_delta' => rand(0, 1),
                ];

                return ['events' => $events, 'final_timestamp' => $finalHold];

            case 'Start':
                $holdAt = $current->copy()->addMinutes(rand(50, 70));
                $events[] = [
                    'status' => 'Hold',
                    'timestamp' => $holdAt,
                    'ok_delta' => rand(15, 22),
                    'scrap_delta' => rand(0, 2),
                ];

                $finalStart = $holdAt->copy()->addMinutes(rand(20, 35));
                $events[] = [
                    'status' => 'Start',
                    'timestamp' => $finalStart,
                    'ok_delta' => rand(10, 16),
                    'scrap_delta' => rand(0, 1),
                ];

                return ['events' => $events, 'final_timestamp' => $finalStart];
        }

        return ['events' => $events, 'final_timestamp' => $current];
    }

    private function advanceMachineClock(
        array $events,
        string $finalStatus,
        Carbon $assignAt,
        Carbon $setupAt,
        Carbon $currentClock
    ): Carbon {
        if ($finalStatus === 'Completed' && ! empty($events)) {
            $last = end($events);

            return $last['timestamp']->copy()->addMinutes(rand(20, 40));
        }

        if ($finalStatus === 'Hold' && ! empty($events)) {
            $last = end($events);

            return $last['timestamp']->copy()->addMinutes(rand(30, 45));
        }

        if ($finalStatus === 'Start' && ! empty($events)) {
            $last = end($events);

            return $last['timestamp']->copy()->addMinutes(rand(90, 120));
        }

        if ($finalStatus === 'Setup') {
            return $setupAt->copy()->addMinutes(rand(30, 45));
        }

        if ($finalStatus === 'Assigned') {
            return $assignAt->copy()->addMinutes(rand(25, 40));
        }

        return $currentClock->copy()->addMinutes(rand(20, 35));
    }

    private function logTransition(
        WorkOrder $workOrder,
        string $status,
        Carbon $timestamp,
        int $userId,
        array $meta = []
    ): void {
        $updates = [
            'status' => $status,
            'updated_at' => $timestamp,
        ];

        if (array_key_exists('hold_reason_id', $meta)) {
            $updates['hold_reason_id'] = $meta['hold_reason_id'];
        }

        if (array_key_exists('ok_qtys', $meta)) {
            $updates['ok_qtys'] = $meta['ok_qtys'];
        }

        if (array_key_exists('scrapped_qtys', $meta)) {
            $updates['scrapped_qtys'] = $meta['scrapped_qtys'];
        }

        if (array_key_exists('end_time', $meta) && $meta['end_time']) {
            $updates['end_time'] = $meta['end_time'];
        }

        $workOrder->updateQuietly($updates);

        $okQtys = $updates['ok_qtys'] ?? $workOrder->ok_qtys;
        $scrapQtys = $updates['scrapped_qtys'] ?? $workOrder->scrapped_qtys;
        $remaining = max($workOrder->qty - ($okQtys + $scrapQtys), 0);

        WorkOrderLog::create([
            'work_order_id' => $workOrder->id,
            'status' => $status,
            'changed_at' => $timestamp,
            'user_id' => $userId,
            'ok_qtys' => $okQtys,
            'scrapped_qtys' => $scrapQtys,
            'remaining' => $remaining,
            'scrapped_reason_id' => null,
            'hold_reason_id' => $updates['hold_reason_id'] ?? null,
            'fpy' => $this->calculateFpy($okQtys, $scrapQtys),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function calculateFpy(int $okQty, int $scrapQty): float
    {
        $total = $okQty + $scrapQty;

        return $total > 0 ? round(($okQty / $total) * 100, 2) : 0.0;
    }

    private function resolveUserId(int $factoryId): int
    {
        $user = User::where('factory_id', $factoryId)->first()
            ?? User::role('Factory Admin')->first()
            ?? User::role('Super Admin')->first()
            ?? User::first();

        return $user?->id ?? 1;
    }
}
