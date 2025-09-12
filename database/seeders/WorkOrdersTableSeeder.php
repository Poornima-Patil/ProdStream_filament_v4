<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkOrder;
use App\Models\Bom;
use App\Models\Operator;
use App\Models\Machine;
use App\Models\Shift;
use App\Models\PartNumber;
use Carbon\Carbon;
use App\Models\User;

class WorkOrdersTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);
        // Get Factory Admin user for this factory (Spatie hasRole)
        $factoryAdminUser = User::where('factory_id', $factoryId)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'Factory Admin');
            })
            ->first();

        if (!$factoryAdminUser) {
            $this->command->error("No Factory Admin found for factory_id $factoryId");
            return;
        }
        // Pick all BOMs whose WOs have not been created
        $boms = Bom::where('factory_id', $factoryId)
            ->whereHas('purchaseOrder')
            ->whereDoesntHave('workOrders')
            ->orderBy('created_at')
            ->get();

        $shifts = Shift::where('factory_id', $factoryId)->orderBy('start_time')->get();
        if ($shifts->isEmpty()) {
            $this->command->error('No shifts found.');
            return;
        }

        $woCreated = 0;
        static $woSerialCounters = [];
        $woCalcToggle = 1; // 1 for calc 1, 2 for calc 2

        foreach ($boms as $bom) {
            $purchaseOrder = $bom->purchaseOrder;
            if (! $purchaseOrder) continue;

            $partNumber = PartNumber::find($purchaseOrder->part_number_id);
            if (! $partNumber) continue;
            $cycleTime = $partNumber->cycle_time;

            $machines = Machine::where('factory_id', $factoryId)
                ->where('machine_group_id', $bom->machine_group_id)
                ->where('status', 1)
                ->get();

            $operators = Operator::where('factory_id', $factoryId)
                ->where('operator_proficiency_id', $bom->operator_proficiency_id)
                ->get();

            if ($machines->isEmpty() || $operators->isEmpty()) continue;

            // Calculate working days for this BOM (from BOM creation + 1 day to PO target date, skipping Sundays)
            // Ensure consistent timezone handling
            $appTimezone = config('app.timezone');
            $bomCreated = Carbon::parse($bom->created_at)->setTimezone($appTimezone)->addDay()->startOfDay();
            $poTargetDate = $purchaseOrder->delivery_target_date
                ? Carbon::parse($purchaseOrder->delivery_target_date)->setTimezone($appTimezone)->endOfDay()
                : $bomCreated->copy()->addWeek()->endOfDay();

            $bomDays = [];
            $current = $bomCreated->copy();
            while ($current->lte($poTargetDate)) {
                if ($current->dayOfWeek !== Carbon::SUNDAY) {
                    $bomDays[] = $current->copy();
                }
                $current->addDay();
            }

            $poQtyRemaining = $purchaseOrder->QTY;
            $daysCount = count($bomDays);

            foreach ($bomDays as $day) {
                if ($poQtyRemaining <= 0) break;

                $machineUsedTimeByDayShift = [];
                $operatorUsedTimeByDayShift = [];

                foreach ($shifts as $shift) {
                    if ($poQtyRemaining <= 0) break;

                    // Filter operators for this shift and proficiency
                    $availableOperators = $operators->filter(function ($op) use ($shift) {
                        return isset($op->shift_id) && $op->shift_id == $shift->id;
                    });

                    if ($availableOperators->isEmpty()) {
                        $this->command->info("No eligible operators for BOM {$bom->id} in shift {$shift->id} on {$day->toDateString()}.");
                        continue;
                    }

                    // Ensure shift times are in the correct timezone
                    $shiftStart = $day->copy()->setTimezone($appTimezone)->setTimeFromTimeString($shift->start_time);
                    if ($shift->end_time <= $shift->start_time) {
                        $shiftEnd = $day->copy()->addDay()->setTimezone($appTimezone)->setTimeFromTimeString($shift->end_time);
                    } else {
                        $shiftEnd = $day->copy()->setTimezone($appTimezone)->setTimeFromTimeString($shift->end_time);
                    }

                    foreach ($machines as $machine) {
                        foreach ($availableOperators as $operator) {
                            $machineKey = $day->format('Y-m-d') . '_' . $shift->id . '_' . $machine->id;
                            $operatorKey = $day->format('Y-m-d') . '_' . $shift->id . '_' . $operator->id;
                            $machineTime = $machineUsedTimeByDayShift[$machineKey] ?? 0;
                            $operatorTime = $operatorUsedTimeByDayShift[$operatorKey] ?? 0;
                            $startTime = $shiftStart->copy()->addSeconds(max($machineTime, $operatorTime));

                            if ($startTime->gte($shiftEnd)) continue;

                            // Strict overlap check for machine/operator
                            $woEnd = $startTime->copy()->addSeconds($poQtyRemaining * $cycleTime);
                            $machineConflict = WorkOrder::where('machine_id', $machine->id)
                                ->where('factory_id', $factoryId)
                                ->where(function ($q) use ($startTime, $woEnd) {
                                    $q->where(function ($query) use ($startTime, $woEnd) {
                                        $query->where('start_time', '<', $woEnd)
                                            ->where('end_time', '>', $startTime);
                                    });
                                })->exists();

                            $operatorConflict = WorkOrder::where('operator_id', $operator->id)
                                ->where('factory_id', $factoryId)
                                ->where(function ($q) use ($startTime, $woEnd) {
                                    $q->where(function ($query) use ($startTime, $woEnd) {
                                        $query->where('start_time', '<', $woEnd)
                                            ->where('end_time', '>', $startTime);
                                    });
                                })->exists();

                            if ($machineConflict || $operatorConflict) continue;

                            // Toggle between calc 1 and calc 2 for each WO
                            if ($woCalcToggle == 1) {
                                // Calc 1: Based on PO_target_delivery_date (try to fit as much as possible in shift)
                                $maxPossibleQty = floor($startTime->diffInSeconds($shiftEnd) / $cycleTime);
                                $qty = min($maxPossibleQty, $poQtyRemaining);
                            } else {
                                // Calc 2: Equally distribute qty across working days except Sundays
                                $qtyPerDay = (int) floor($purchaseOrder->QTY / $daysCount);
                                $extraQty = $purchaseOrder->QTY % $daysCount;
                                $qty = $qtyPerDay + ($extraQty > 0 ? 1 : 0);
                                $qty = min($qty, $poQtyRemaining, floor($startTime->diffInSeconds($shiftEnd) / $cycleTime));
                                if ($extraQty > 0) $extraQty--;
                            }

                            if ($qty <= 0) continue;

                            $woEnd = $startTime->copy()->addSeconds($qty * $cycleTime);

                            $monthYear = $day->format('mY');
                            if (!isset($woSerialCounters[$monthYear])) $woSerialCounters[$monthYear] = 1;
                            $woSerial = str_pad($woSerialCounters[$monthYear], 4, '0', STR_PAD_LEFT);
                            $woDate = $startTime->format('mdy');
                            $woUniqueId = "W{$woSerial}_{$woDate}_{$bom->unique_id}";
                            // Ensure work order creation time is in correct timezone and before start time
                            $WoCreatedAt = Carbon::parse($bom->created_at)->setTimezone($appTimezone)->addDay();
                            
                            // Make sure created_at is before start_time to avoid negative durations
                            if ($WoCreatedAt->greaterThan($startTime)) {
                                $WoCreatedAt = $startTime->copy()->subHours(rand(1, 12)); // 1-12 hours before start
                            }
                            
                            WorkOrder::create([
                                'bom_id' => $bom->id,
                                'qty' => $qty,
                                'machine_id' => $machine->id,
                                'operator_id' => $operator->id,
                                'start_time' => $startTime->toDateTimeString(),
                                'end_time' => $woEnd->toDateTimeString(),
                                'status' => 'Assigned',
                                'ok_qtys' => 0,
                                'scrapped_qtys' => 0,
                                'unique_id' => $woUniqueId,
                                'factory_id' => $factoryId,
                                'created_at' => $WoCreatedAt->toDateTimeString(),
                                'updated_at' => $WoCreatedAt->toDateTimeString(),
                            ]);

                            $this->command->info("Created WO for BOM {$bom->id} with qty $qty, machine {$machine->id}, operator {$operator->id}, shift {$shift->id}, start $startTime, end $woEnd, using calc {$woCalcToggle}");

                            $machineUsedTimeByDayShift[$machineKey] = $machineTime + ($qty * $cycleTime);
                            $operatorUsedTimeByDayShift[$operatorKey] = $operatorTime + ($qty * $cycleTime);

                            $woSerialCounters[$monthYear]++;
                            $woCreated++;
                            $poQtyRemaining -= $qty;

                            // Alternate calculation for next WO
                            $woCalcToggle = ($woCalcToggle == 1) ? 2 : 1;

                            if ($poQtyRemaining <= 0) break 3;
                        }
                    }
                }
            }
        }

        $this->command->info("Seeded $woCreated work orders for all BOMs with alternating qty calculation.");
    }
}
