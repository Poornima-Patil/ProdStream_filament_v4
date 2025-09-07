<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bom_id',
        'qty',
        'machine_id',
        'operator_id',
        'start_time',
        'end_time',
        'status',
        'ok_qtys',
        'scrapped_qtys',
        'unique_id',
        'hold_reason_id',
        'material_batch',
        'factory_id',
    ];

    protected $casts = [
        'hold_reason_id' => 'integer',
        'machine_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime', // Ensures it's treated as an integer
    ];

    public function bom()
    {
        Log::info('Accessing BOM for Work Order:', [
            'work_order_id' => $this->id,
            'work_order_unique_id' => $this->unique_id,
            'bom_id' => $this->bom_id,
        ]);

        return $this->belongsTo(Bom::class, 'bom_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class, 'operator_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function scrappedQuantities()
    {
        return $this->hasMany(WorkOrderQuantity::class);
    }

    public function okQuantities()
    {
        return $this->hasMany(WorkOrderQuantity::class);
    }

    public function quantities()
    {
        return $this->hasMany(WorkOrderQuantity::class);
    }

    public function holdReason()
    {
        return $this->belongsTo(HoldReason::class, 'hold_reason_id');
    }

    public function workOrderLogs()
    {
        return $this->hasMany(WorkOrderLog::class);
    }

    public function createWorkOrderLog($newStatus)
    {
        // Get the latest quantity entry for this work order
        $latestQuantity = $this->quantities()->latest()->first();

        // Calculate totals from the latest quantity entry
        $okQtys = $latestQuantity ? $latestQuantity->ok_quantity : 0;
        $scrappedQtys = $latestQuantity ? $latestQuantity->scrapped_quantity : 0;
        $scrappedReasonId = $latestQuantity ? $latestQuantity->reason_id : null;

        // Calculate FPY for 'Hold' status
        $fpy = 0;
        if ($newStatus === 'Hold') {
            $total = $okQtys + $scrappedQtys;
            $fpy = $total > 0 ? ($scrappedQtys / $total) * 100 : 0;
        }

        // Handle seeding context where Auth::id() might be null
        $userId = Auth::id();
        if (! $userId && app()->runningInConsole()) {
            // During seeding, try to find a Factory Admin for this specific factory
            if ($this->factory_id) {
                $factoryAdmin = \App\Models\User::where('factory_id', $this->factory_id)
                    ->whereHas('roles', function ($query) {
                        $query->where('name', 'Factory Admin');
                    })->first();
                $userId = $factoryAdmin?->id;
            }

            // Fallback to any super admin
            if (! $userId) {
                $superAdmin = \App\Models\User::role('Super Admin')->first();
                $userId = $superAdmin?->id;
            }

            // Final fallback to first user or default ID
            if (! $userId) {
                $userId = \App\Models\User::first()?->id ?? 1;
            }
        }

        $log = WorkOrderLog::create([
            'work_order_id' => $this->id,
            'status' => $newStatus,
            'changed_at' => now(),
            'user_id' => $userId,
            'ok_qtys' => $okQtys,
            'scrapped_qtys' => $scrappedQtys,
            'remaining' => $this->qty - ($okQtys + $scrappedQtys),
            'scrapped_reason_id' => $scrappedReasonId,
            'hold_reason_id' => $this->hold_reason_id,
            'fpy' => $fpy, // Add FPY value
        ]);

        return $log;
    }

    protected static function booted()
    {
        static::created(function ($workOrder) {
            $workOrder->createWorkOrderLog('Assigned');
        });

        static::updated(function ($workOrder) {
            // Only create a log if the status changed AND quantities are up-to-date
            if ($workOrder->isDirty('status')) {
                // Only create a log if this is not a "Start" status, or if there are quantities
                if (
                    $workOrder->status !== 'Start' ||
                    $workOrder->quantities()->exists()
                ) {
                    $workOrder->createWorkOrderLog($workOrder->status);
                }
            }
        });

        // Remove or comment out this block to avoid duplicate log creation:
        /*
        static::created(function ($workOrder) {
            $latestLog = $workOrder->workOrderLogs()->latest()->first();
            if (! $latestLog) {
                $latestLog = $workOrder->createWorkOrderLog($workOrder->status);
            }
            $workOrder->quantities()->update(['work_order_log_id' => $latestLog->id]);
        });
        */
    }

    // Define the relationship with InfoMessage
    public function infoMessages()
    {
        return $this->hasMany(InfoMessage::class);
    }

    // Add this new method to handle quantity creation
    public function createQuantity(array $data)
    {
        // Get the latest work order log
        $latestLog = $this->workOrderLogs()->latest()->first();

        if (! $latestLog) {
            // If no log exists, create one
            $latestLog = $this->createWorkOrderLog($this->status);
        }

        // Add the work_order_log_id to the data
        $data['work_order_log_id'] = $latestLog->id;

        // Create the quantity
        return $this->quantities()->create($data);
    }

    public function scopeFiltered($query, $filters)
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // You can add more filters here based on your table setup
        // if (!empty($filters['operator_id'])) {
        //     $query->where('operator_id', $filters['operator_id']);
        // }

        return $query;
    }

    /**
     * Check for scheduling conflicts when planning a new Work Order
     *
     * @param  int  $machineId
     * @param  \Carbon\Carbon  $newStartTime
     * @param  \Carbon\Carbon  $newEndTime
     * @param  int  $factoryId  - Factory ID for multi-tenancy
     * @param  int|null  $excludeWorkOrderId  - Exclude current WO when updating
     * @return array
     */
    public static function checkSchedulingConflicts($machineId, $newStartTime, $newEndTime, $factoryId, $excludeWorkOrderId = null)
    {
        $conflicts = [];

        // Query existing WOs for the same machine within the same factory
        // Include all active statuses that have scheduled times
        $existingWorkOrders = self::where('machine_id', $machineId)
            ->where('factory_id', $factoryId) // Multi-tenancy: filter by factory
            ->whereIn('status', ['Assigned', 'Start', 'Hold']) // Include Hold status as they may resume
            ->whereNotNull('start_time') // Only check WOs with scheduled times
            ->whereNotNull('end_time')
            ->when($excludeWorkOrderId, function ($query) use ($excludeWorkOrderId) {
                return $query->where('id', '!=', $excludeWorkOrderId);
            })
            ->with('workOrderLogs') // Load logs to get actual start time
            ->get();

        foreach ($existingWorkOrders as $existingWO) {
            // For running work orders (status = 'Start'), calculate realistic end time based on actual start
            if ($existingWO->status === 'Start') {
                $adjustedEndTime = self::calculateRealisticEndTime($existingWO);
                $conflictStartTime = $existingWO->start_time; // Use planned start for conflict calculation
                $conflictEndTime = $adjustedEndTime;
            } else {
                // For planned/held work orders, use original planned times
                $conflictStartTime = $existingWO->start_time;
                $conflictEndTime = $existingWO->end_time;
            }

            // Time-based conflict detection: check if new WO time overlaps with existing WO time
            // Only report conflict if there's actual time overlap
            if ($newStartTime < $conflictEndTime && $newEndTime > $conflictStartTime) {
                $conflicts[] = [
                    'work_order_id' => $existingWO->id,
                    'work_order_unique_id' => $existingWO->unique_id,
                    'status' => $existingWO->status,
                    'planned_start' => $existingWO->start_time,
                    'planned_end' => $existingWO->end_time,
                    'realistic_end' => $existingWO->status === 'Start' ? $conflictEndTime : null,
                    'operator_id' => $existingWO->operator_id,
                    'bom_id' => $existingWO->bom_id,
                    'conflict_type' => self::getConflictType($newStartTime, $newEndTime, $conflictStartTime, $conflictEndTime),
                    'overlap_duration' => self::calculateOverlapDuration($newStartTime, $newEndTime, $conflictStartTime, $conflictEndTime),
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Calculate realistic end time for a running work order based on actual start time
     */
    private static function calculateRealisticEndTime($workOrder)
    {
        // Get the actual start time from work order logs
        $startLog = $workOrder->workOrderLogs()
            ->where('status', 'Start')
            ->orderBy('changed_at', 'asc')
            ->first();

        if ($startLog) {
            // Calculate original planned duration
            $plannedDuration = $workOrder->start_time->diffInMinutes($workOrder->end_time);

            // Calculate realistic end time: actual start + planned duration
            $actualStartTime = \Carbon\Carbon::parse($startLog->changed_at);
            $realisticEndTime = $actualStartTime->copy()->addMinutes($plannedDuration);

            return $realisticEndTime;
        }

        // Fallback to planned end time if no start log found
        return $workOrder->end_time;
    }

    /**
     * Determine the type of scheduling conflict
     */
    private static function getConflictType($newStart, $newEnd, $existingStart, $existingEnd)
    {
        if ($newStart >= $existingStart && $newEnd <= $existingEnd) {
            return 'completely_within'; // New WO is completely within existing WO
        } elseif ($newStart <= $existingStart && $newEnd >= $existingEnd) {
            return 'completely_covers'; // New WO completely covers existing WO
        } elseif ($newStart < $existingStart && $newEnd > $existingStart) {
            return 'overlaps_start'; // New WO overlaps with start of existing WO
        } elseif ($newStart < $existingEnd && $newEnd > $existingEnd) {
            return 'overlaps_end'; // New WO overlaps with end of existing WO
        }

        return 'partial_overlap';
    }

    /**
     * Calculate the duration of overlap between two time periods
     */
    private static function calculateOverlapDuration($newStart, $newEnd, $existingStart, $existingEnd)
    {
        $overlapStart = max($newStart, $existingStart);
        $overlapEnd = min($newEnd, $existingEnd);

        return $overlapStart->diffInMinutes($overlapEnd);
    }

    /**
     * Check if a machine is currently occupied (has WO in "Start" status)
     *
     * @param  int  $machineId
     * @param  int  $factoryId  - Factory ID for multi-tenancy
     * @return bool
     */
    public static function isMachineCurrentlyOccupied($machineId, $factoryId)
    {
        return self::where('machine_id', $machineId)
            ->where('factory_id', $factoryId) // Multi-tenancy: filter by factory
            ->where('status', 'Start')
            ->exists();
    }

    /**
     * Get current running Work Order for a machine
     *
     * @param  int  $machineId
     * @param  int  $factoryId  - Factory ID for multi-tenancy
     * @return WorkOrder|null
     */
    public static function getCurrentRunningWorkOrder($machineId, $factoryId)
    {
        return self::where('machine_id', $machineId)
            ->where('factory_id', $factoryId) // Multi-tenancy: filter by factory
            ->where('status', 'Start')
            ->first();
    }

    /**
     * Validate if a Work Order can transition to 'Start' status
     * Ensures only one WO can be in 'Start' status per machine at any time
     * Also checks if starting would conflict with planned schedules
     *
     * @param  int  $machineId
     * @param  int  $factoryId  - Factory ID for multi-tenancy
     * @param  int|null  $excludeWorkOrderId  - Exclude current WO when updating
     * @return array
     */
    public static function validateStartStatusTransition($machineId, $factoryId, $excludeWorkOrderId = null)
    {
        $validation = [
            'can_start' => true,
            'conflicting_work_order' => null,
            'message' => null,
        ];

        // Check if another WO is already in 'Start' status on this machine
        $runningWorkOrder = self::where('machine_id', $machineId)
            ->where('factory_id', $factoryId) // Multi-tenancy: filter by factory
            ->where('status', 'Start')
            ->when($excludeWorkOrderId, function ($query) use ($excludeWorkOrderId) {
                return $query->where('id', '!=', $excludeWorkOrderId);
            })
            ->with(['operator.user', 'machine']) // Load related data for detailed message
            ->first();

        if ($runningWorkOrder) {
            $operatorName = $runningWorkOrder->operator?->user
                ? "{$runningWorkOrder->operator->user->first_name} {$runningWorkOrder->operator->user->last_name}"
                : 'Unknown';

            $machineName = $runningWorkOrder->machine
                ? "{$runningWorkOrder->machine->assetId} - {$runningWorkOrder->machine->name}"
                : 'Unknown Machine';

            $estimatedCompletion = $runningWorkOrder->end_time
                ? \Carbon\Carbon::parse($runningWorkOrder->end_time)->format('M d, H:i')
                : 'Unknown';

            $validation['can_start'] = false;
            $validation['conflicting_work_order'] = $runningWorkOrder;
            $validation['message'] = "Cannot start: Machine {$machineName} is already running Work Order #{$runningWorkOrder->unique_id} (Operator: {$operatorName}, Est. completion: {$estimatedCompletion}). Complete or hold the running work order first.";

            return $validation;
        }

        // If no running WO found, check for scheduled conflicts with planned times
        $currentTime = now();

        // Check if the current time falls within any scheduled work order's planned time window
        $conflictingScheduledWO = self::where('machine_id', $machineId)
            ->where('factory_id', $factoryId)
            ->where('status', 'Assigned') // Only check scheduled (assigned) work orders
            ->when($excludeWorkOrderId, function ($query) use ($excludeWorkOrderId) {
                return $query->where('id', '!=', $excludeWorkOrderId);
            })
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where(function ($query) use ($currentTime) {
                $query->where('start_time', '<=', $currentTime)
                    ->where('end_time', '>', $currentTime);
            })
            ->with(['operator.user', 'machine'])
            ->first();

        if ($conflictingScheduledWO) {
            $operatorName = $conflictingScheduledWO->operator?->user
                ? "{$conflictingScheduledWO->operator->user->first_name} {$conflictingScheduledWO->operator->user->last_name}"
                : 'Unknown';

            $machineName = $conflictingScheduledWO->machine
                ? "{$conflictingScheduledWO->machine->assetId} - {$conflictingScheduledWO->machine->name}"
                : 'Unknown Machine';

            $scheduledStart = \Carbon\Carbon::parse($conflictingScheduledWO->start_time)->format('M d, H:i');
            $scheduledEnd = \Carbon\Carbon::parse($conflictingScheduledWO->end_time)->format('M d, H:i');

            $validation['can_start'] = false;
            $validation['conflicting_work_order'] = $conflictingScheduledWO;
            $validation['message'] = "Cannot start: Current time conflicts with planned Work Order #{$conflictingScheduledWO->unique_id} on {$machineName} (Operator: {$operatorName}, Planned: {$scheduledStart} - {$scheduledEnd}). This work order is scheduled to run during this time slot.";
        }

        return $validation;
    }

    /**
     * Validate scheduling for a new Work Order
     * Returns validation result with conflicts and recommendations
     *
     * @param  array  $workOrderData
     * @return array
     */
    public static function validateScheduling($workOrderData)
    {
        $machineId = $workOrderData['machine_id'];
        $operatorId = $workOrderData['operator_id'] ?? null;
        $factoryId = $workOrderData['factory_id']; // Multi-tenancy: get factory ID
        $startTime = \Carbon\Carbon::parse($workOrderData['start_time']);
        $endTime = \Carbon\Carbon::parse($workOrderData['end_time']);
        $excludeId = $workOrderData['id'] ?? null;
        $newStatus = $workOrderData['status'] ?? null;

        $validation = [
            'is_valid' => true,
            'conflicts' => [],
            'warnings' => [],
            'recommendations' => [],
            'start_validation' => null,
            'operator_conflicts' => [],
            'shift_conflicts' => [],
        ];

        // Check if trying to transition to 'Start' status
        if ($newStatus === 'Start') {
            $startValidation = self::validateStartStatusTransition($machineId, $factoryId, $excludeId);
            $validation['start_validation'] = $startValidation;

            if (! $startValidation['can_start']) {
                $validation['is_valid'] = false;
            }
        }

        // Check if machine is currently occupied within the same factory (for scheduling purposes)
        if (self::isMachineCurrentlyOccupied($machineId, $factoryId)) {
            $currentWO = self::getCurrentRunningWorkOrder($machineId, $factoryId);
            if (! $excludeId || $currentWO->id !== $excludeId) {
                $validation['warnings'][] = [
                    'type' => 'machine_currently_occupied',
                    'message' => "Machine is currently running Work Order: {$currentWO->unique_id}",
                    'current_work_order' => $currentWO->unique_id,
                    'estimated_completion' => $currentWO->end_time,
                ];
            }
        }

        // Check for machine scheduling conflicts within the same factory
        $machineConflicts = self::checkSchedulingConflicts($machineId, $startTime, $endTime, $factoryId, $excludeId);

        if (! empty($machineConflicts)) {
            $validation['is_valid'] = false;
            $validation['conflicts'] = $machineConflicts;

            // Add recommendations based on conflicts
            $validation['recommendations'] = self::generateSchedulingRecommendations($machineId, $startTime, $endTime, $machineConflicts);
        }

        // Check operator scheduling conflicts and shift validation
        if ($operatorId) {
            $operator = \App\Models\Operator::find($operatorId);
            if ($operator) {
                // Check operator scheduling conflicts
                $operatorConflicts = $operator->checkSchedulingConflicts($startTime, $endTime, $factoryId, $excludeId);

                if (! empty($operatorConflicts)) {
                    $validation['operator_conflicts'] = $operatorConflicts;

                    // Check for shift conflicts specifically
                    $shiftConflicts = array_filter($operatorConflicts, function ($conflict) {
                        return $conflict['type'] === 'shift_conflict';
                    });

                    if (! empty($shiftConflicts)) {
                        $validation['shift_conflicts'] = $shiftConflicts;
                        $validation['warnings'][] = [
                            'type' => 'operator_shift_conflict',
                            'message' => "Work order time conflicts with operator's shift schedule",
                            'operator_name' => $operator->user?->getFilamentName() ?? 'Unknown',
                            'shift_details' => $operator->shift ? [
                                'name' => $operator->shift->name,
                                'start_time' => $operator->shift->start_time,
                                'end_time' => $operator->shift->end_time,
                            ] : null,
                        ];
                    }

                    // Check for work order conflicts
                    $workOrderConflicts = array_filter($operatorConflicts, function ($conflict) {
                        return $conflict['type'] === 'work_order_conflict';
                    });

                    if (! empty($workOrderConflicts)) {
                        $validation['is_valid'] = false;
                        $validation['warnings'][] = [
                            'type' => 'operator_availability_conflict',
                            'message' => 'Operator is already assigned to other work orders during this time',
                            'operator_name' => $operator->user?->getFilamentName() ?? 'Unknown',
                            'conflicting_work_orders' => array_map(function ($conflict) {
                                return [
                                    'work_order_id' => $conflict['work_order_unique_id'],
                                    'start' => $conflict['planned_start'],
                                    'end' => $conflict['planned_end'],
                                    'status' => $conflict['status'],
                                ];
                            }, $workOrderConflicts),
                        ];
                    }
                }

                // Add operator-specific recommendations
                if (! empty($operatorConflicts)) {
                    $validation['recommendations'] = array_merge(
                        $validation['recommendations'] ?? [],
                        self::generateOperatorSchedulingRecommendations($operator, $startTime, $endTime, $operatorConflicts)
                    );
                }
            }
        }

        return $validation;
    }

    /**
     * Generate scheduling recommendations to resolve conflicts
     */
    private static function generateSchedulingRecommendations($machineId, $startTime, $endTime, $conflicts)
    {
        $recommendations = [];
        $duration = $startTime->diffInMinutes($endTime);

        // Find next available slot after conflicts
        $latestConflictEnd = null;
        foreach ($conflicts as $conflict) {
            $conflictEnd = \Carbon\Carbon::parse($conflict['planned_end']);
            if (! $latestConflictEnd || $conflictEnd > $latestConflictEnd) {
                $latestConflictEnd = $conflictEnd;
            }
        }

        if ($latestConflictEnd) {
            $recommendedStart = $latestConflictEnd->copy(); // Add buffer here with ->addMinuteds() if required
            $recommendedEnd = $recommendedStart->copy()->addMinutes($duration);

            $recommendations[] = [
                'type' => 'reschedule_after_conflicts',
                'suggested_start_time' => $recommendedStart,
                'suggested_end_time' => $recommendedEnd,
                'message' => "Reschedule to start at {$recommendedStart->format('Y-m-d H:i')} (after conflicting work orders)",
            ];
        }

        // Suggest alternative machines (if needed, this would require machine compatibility logic)
        $recommendations[] = [
            'type' => 'consider_alternative_machine',
            'message' => 'Consider using an alternative machine if available and compatible',
        ];

        return $recommendations;
    }

    /**
     * Generate operator-specific scheduling recommendations
     */
    private static function generateOperatorSchedulingRecommendations($operator, $startTime, $endTime, $operatorConflicts)
    {
        $recommendations = [];
        $duration = $startTime->diffInMinutes($endTime);

        foreach ($operatorConflicts as $conflict) {
            if ($conflict['type'] === 'shift_conflict') {
                // Recommend scheduling within shift hours
                if ($operator->shift) {
                    $shift = $operator->shift;

                    // Find next shift occurrence
                    $nextShiftStart = $startTime->copy()->setTimeFromTimeString($shift->start_time);
                    $nextShiftEnd = $startTime->copy()->setTimeFromTimeString($shift->end_time);

                    // Handle overnight shifts
                    if ($nextShiftEnd < $nextShiftStart) {
                        if ($startTime->hour < 12) {
                            $nextShiftStart->subDay();
                        } else {
                            $nextShiftEnd->addDay();
                        }
                    }

                    // If the suggested time is before the shift starts, recommend shift start time
                    if ($nextShiftStart > $startTime) {
                        $recommendedEnd = $nextShiftStart->copy()->addMinutes($duration);

                        // Check if it fits within shift
                        if ($recommendedEnd <= $nextShiftEnd) {
                            $recommendations[] = [
                                'type' => 'reschedule_within_shift',
                                'suggested_start_time' => $nextShiftStart,
                                'suggested_end_time' => $recommendedEnd,
                                'message' => "Reschedule to start at {$nextShiftStart->format('Y-m-d H:i')} (during {$shift->name} shift)",
                            ];
                        }
                    }
                }

                $recommendations[] = [
                    'type' => 'consider_alternative_operator',
                    'message' => 'Consider assigning a different operator who is available during this time',
                ];

            } elseif ($conflict['type'] === 'work_order_conflict') {
                // Find the latest end time of conflicting work orders
                $latestConflictEnd = \Carbon\Carbon::parse($conflict['planned_end']);
                $recommendedStart = $latestConflictEnd->copy();
                $recommendedEnd = $recommendedStart->copy()->addMinutes($duration);

                // Check if recommended time is within operator's shift
                if ($operator->shift) {
                    $shiftStart = $recommendedStart->copy()->setTimeFromTimeString($operator->shift->start_time);
                    $shiftEnd = $recommendedStart->copy()->setTimeFromTimeString($operator->shift->end_time);

                    // Handle overnight shifts
                    if ($shiftEnd < $shiftStart) {
                        if ($recommendedStart->hour < 12) {
                            $shiftStart->subDay();
                        } else {
                            $shiftEnd->addDay();
                        }
                    }

                    if ($recommendedStart >= $shiftStart && $recommendedEnd <= $shiftEnd) {
                        $recommendations[] = [
                            'type' => 'reschedule_after_operator_conflicts',
                            'suggested_start_time' => $recommendedStart,
                            'suggested_end_time' => $recommendedEnd,
                            'message' => "Reschedule to start at {$recommendedStart->format('Y-m-d H:i')} (after operator's conflicting work orders)",
                        ];
                    } else {
                        $recommendations[] = [
                            'type' => 'operator_availability_issue',
                            'message' => 'Operator has conflicting work orders and recommended time falls outside shift hours',
                        ];
                    }
                } else {
                    $recommendations[] = [
                        'type' => 'reschedule_after_operator_conflicts',
                        'suggested_start_time' => $recommendedStart,
                        'suggested_end_time' => $recommendedEnd,
                        'message' => "Reschedule to start at {$recommendedStart->format('Y-m-d H:i')} (after operator's conflicting work orders)",
                    ];
                }
            }
        }

        return $recommendations;
    }
}
