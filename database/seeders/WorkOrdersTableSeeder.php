<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkOrder;
use App\Models\Bom;
use App\Models\Operator;
use App\Models\Machine;
use App\Models\Shift;
use App\Models\PartNumber;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WorkOrdersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1);

        // Get Factory Admin user for this factory (Spatie hasRole)
        $factoryAdminUser = User::where('factory_id', $factoryId)
            ->whereHas('roles', function($q) {
                $q->where('name', 'Factory Admin');
            })
            ->first();

        if (!$factoryAdminUser) {
            $this->command->error("No Factory Admin found for factory_id $factoryId");
            return;
        }

        // Get all eligible BOMs for the factory (with PO)
        $boms = Bom::where('factory_id', $factoryId)
            ->whereHas('purchaseOrder')
            ->orderBy('created_at')
            ->get();

        if ($boms->isEmpty()) {
            $this->command->error('No BOMs found for the factory.');
            return;
        }

        // Get all shifts for the factory
        $shifts = Shift::where('factory_id', $factoryId)->orderBy('start_time')->get();
        if ($shifts->isEmpty()) {
            $this->command->error('No shifts found.');
            return;
        }

        $woCreated = 0;
        static $woSerialCounters = [];

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
            $bomCreated = Carbon::parse($bom->created_at)->addDay()->startOfDay();
            $poTargetDate = $purchaseOrder->delivery_target_date
                ? Carbon::parse($purchaseOrder->delivery_target_date)->endOfDay()
                : $bomCreated->copy()->addWeek()->endOfDay();

            $bomDays = [];
            $current = $bomCreated->copy();
            while ($current->lte($poTargetDate)) {
                if ($current->dayOfWeek !== Carbon::SUNDAY) {
                    $bomDays[] = $current->copy();
                }
                $current->addDay();
            }

            // Calculate qty per day for this BOM so last WO ends on target date
            $poQtyRemaining = $purchaseOrder->QTY - WorkOrder::where('bom_id', $bom->id)->sum('qty');
            $daysCount = count($bomDays);
            if ($daysCount == 0) continue;
            $qtyPerDay = (int) floor($purchaseOrder->QTY / $daysCount);
            $extraQty = $purchaseOrder->QTY % $daysCount; // Distribute remainder

            foreach ($bomDays as $dayIndex => $day) {
                // Distribute extra qty to the first few days
                $todayQty = $qtyPerDay + ($dayIndex < $extraQty ? 1 : 0);

                $qtyLeftForDay = $todayQty - WorkOrder::where('bom_id', $bom->id)
                    ->whereDate('start_time', $day->toDateString())
                    ->sum('qty');
                if ($qtyLeftForDay <= 0) continue;

                $machineUsedTimeByDayShift = [];
                $operatorUsedTimeByDayShift = [];

                foreach ($shifts as $shift) {
                    if ($qtyLeftForDay <= 0) break;

                    $shiftStart = $day->copy()->setTimeFromTimeString($shift->start_time);
                    if ($shift->end_time <= $shift->start_time) {
                        $shiftEnd = $day->copy()->addDay()->setTimeFromTimeString($shift->end_time);
                    } else {
                        $shiftEnd = $day->copy()->setTimeFromTimeString($shift->end_time);
                    }

                    foreach ($machines as $machine) {
                        foreach ($operators as $operator) {
                            $machineKey = $day->format('Y-m-d') . '_' . $shift->id . '_' . $machine->id;
                            $operatorKey = $day->format('Y-m-d') . '_' . $shift->id . '_' . $operator->id;
                            $machineTime = $machineUsedTimeByDayShift[$machineKey] ?? 0;
                            $operatorTime = $operatorUsedTimeByDayShift[$operatorKey] ?? 0;
                            $startTime = $shiftStart->copy()->addSeconds(max($machineTime, $operatorTime));

                            if ($startTime->gte($shiftEnd)) continue;

                            // Strict overlap check for machine/operator
                            $woEnd = $startTime->copy()->addSeconds($qtyLeftForDay * $cycleTime);
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

                            $remainingSecs = $startTime->diffInSeconds($shiftEnd);
                            $maxQty = floor($remainingSecs / $cycleTime);
                            $qty = min($maxQty, $qtyLeftForDay);

                            if ($qty <= 0) continue;

                            $monthYear = $day->format('mY');
                            if (!isset($woSerialCounters[$monthYear])) $woSerialCounters[$monthYear] = 1;
                            $woSerial = str_pad($woSerialCounters[$monthYear], 4, '0', STR_PAD_LEFT);
                            $woDate = $startTime->format('mdy');
                            $woUniqueId = "W{$woSerial}_{$woDate}_{$bom->unique_id}";

                            $woEnd = $startTime->copy()->addSeconds($qty * $cycleTime);

                            WorkOrder::create([
                                'bom_id' => $bom->id,
                                'qty' => $qty,
                                'machine_id' => $machine->id,
                                'operator_id' => $operator->id,
                                'start_time' => $startTime,
                                'end_time' => $woEnd,
                                'status' => 'Assigned',
                                'ok_qtys' => 0,
                                'scrapped_qtys' => 0,
                                'unique_id' => $woUniqueId,
                                'factory_id' => $factoryId,
                            ]);

                            $machineUsedTimeByDayShift[$machineKey] = $machineTime + ($qty * $cycleTime);
                            $operatorUsedTimeByDayShift[$operatorKey] = $operatorTime + ($qty * $cycleTime);

                            $woSerialCounters[$monthYear]++;
                            $woCreated++;
                            $qtyLeftForDay -= $qty;

                            if ($qtyLeftForDay <= 0) break 2;
                        }
                    }
                }
            }
        }

        $this->command->info("Seeded $woCreated work orders for all BOMs, with last WO for each BOM ending on its PO target date, qty per day split across shifts, and no machine/operator overlap.");
    }
}