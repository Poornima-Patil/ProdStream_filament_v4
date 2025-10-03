<?php

namespace App\Models;

use Carbon\Carbon;
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
        'work_order_group_id',
        'dependency_status',
        'sequence_order',
        'is_dependency_root',
        'dependency_satisfied_at',
        'dependency_metadata',
    ];

    protected function casts(): array
    {
        return [
            'hold_reason_id' => 'integer',
            'machine_id' => 'integer',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'dependency_satisfied_at' => 'datetime',
            'dependency_metadata' => 'array',
            'is_dependency_root' => 'boolean',
        ];
    }

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

    public function workOrderGroup(): BelongsTo
    {
        return $this->belongsTo(WorkOrderGroup::class);
    }

    public function batches()
    {
        return $this->hasMany(WorkOrderBatch::class);
    }

    public function batchKeys()
    {
        return $this->hasMany(WorkOrderBatchKey::class);
    }

    public function keyConsumptions()
    {
        return $this->hasMany(BatchKeyConsumption::class, 'consumer_work_order_id');
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
                $factoryAdmin = User::where('factory_id', $this->factory_id)
                    ->whereHas('roles', function ($query) {
                        $query->where('name', 'Factory Admin');
                    })->first();
                $userId = $factoryAdmin?->id;
            }

            // Fallback to any super admin
            if (! $userId) {
                $superAdmin = User::role('Super Admin')->first();
                $userId = $superAdmin?->id;
            }

            // Final fallback to first user or default ID
            if (! $userId) {
                $userId = User::first()?->id ?? 1;
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
     * Performance-optimized query scopes for multi-tenancy
     */

    /**
     * Scope to factory with optimized index usage
     */
    public function scopeForFactory($query, int $factoryId)
    {
        return $query->where('factory_id', $factoryId);
    }

    /**
     * Scope for active work orders (uses optimized index)
     */
    public function scopeActive($query, int $factoryId)
    {
        return $query->where('factory_id', $factoryId)
            ->whereIn('status', ['Assigned', 'Start', 'Hold']);
    }

    /**
     * Scope for completed work orders with time range (uses KPI index)
     */
    public function scopeCompletedInRange($query, int $factoryId, Carbon $startDate, Carbon $endDate)
    {
        return $query->where('factory_id', $factoryId)
            ->where('status', 'Completed')
            ->whereBetween('updated_at', [$startDate, $endDate]);
    }

    /**
     * Scope for today's work orders (uses created_status index)
     */
    public function scopeToday($query, int $factoryId)
    {
        return $query->where('factory_id', $factoryId)
            ->whereDate('created_at', today());
    }

    /**
     * Scope for machine schedule optimization
     */
    public function scopeForMachineSchedule($query, int $factoryId, int $machineId, ?string $status = null)
    {
        $query = $query->where('factory_id', $factoryId)
            ->where('machine_id', $machineId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->whereNotNull('start_time')
            ->orderBy('start_time');
    }

    /**
     * Scope for operator workload (uses operator_status index)
     */
    public function scopeForOperator($query, int $factoryId, int $operatorId, ?string $status = null)
    {
        $query = $query->where('factory_id', $factoryId)
            ->where('operator_id', $operatorId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * Scope for dependency chain analysis
     */
    public function scopeInGroup($query, int $groupId, ?bool $isRoot = null)
    {
        $query = $query->where('work_order_group_id', $groupId);

        if ($isRoot !== null) {
            $query->where('is_dependency_root', $isRoot);
        }

        return $query->orderBy('sequence_order');
    }

    /**
     * Scope for KPI calculations with optimized joins
     */
    public function scopeForKpiCalculation($query, int $factoryId, Carbon $startDate, Carbon $endDate)
    {
        return $query->where('work_orders.factory_id', $factoryId)
            ->whereBetween('work_orders.created_at', [$startDate, $endDate])
            ->select([
                'work_orders.id',
                'work_orders.status',
                'work_orders.qty',
                'work_orders.ok_qtys',
                'work_orders.scrapped_qtys',
                'work_orders.start_time',
                'work_orders.end_time',
                'work_orders.created_at',
                'work_orders.updated_at'
            ]);
    }

    /**
     * Scope with optimized eager loading for dashboards
     */
    public function scopeWithDashboardData($query)
    {
        return $query->with([
            'machine:id,name,assetId',
            'operator.user:id,first_name,last_name',
            'workOrderGroup:id,name,status'
        ]);
    }

    /**
     * Scope for batch system queries
     */
    public function scopeWithBatchData($query)
    {
        return $query->with([
            'batches' => function ($q) {
                $q->select('id', 'work_order_id', 'batch_number', 'status', 'planned_quantity', 'actual_quantity')
                  ->orderBy('batch_number');
            },
            'batchKeys' => function ($q) {
                $q->where('is_consumed', false)
                  ->select('id', 'work_order_id', 'key_code', 'quantity_produced', 'is_consumed');
            }
        ]);
    }


    /**
     * Cursor pagination for large datasets
     */
    public function scopeWithCursorPagination($query, ?string $cursor = null, int $perPage = 50)
    {
        if ($cursor) {
            $query->where('id', '>', $cursor);
        }

        return $query->orderBy('id')->limit($perPage);
    }

    /**
     * Get paginated work orders using cursor pagination for performance
     */
    public static function getCursorPaginated(int $factoryId, ?string $cursor = null, int $perPage = 50): array
    {
        $workOrders = self::forFactory($factoryId)
            ->withCursorPagination($cursor, $perPage)
            ->withDashboardData()
            ->get();

        $nextCursor = $workOrders->count() === $perPage ? $workOrders->last()->id : null;

        return [
            'data' => $workOrders,
            'next_cursor' => $nextCursor,
            'has_more' => $nextCursor !== null,
        ];
    }

    protected static function booted()
    {
        parent::booted();

        static::created(function ($workOrder) {
            // Create log with the work order's actual status, not hardcoded 'Assigned'
            $workOrder->createWorkOrderLog($workOrder->status);
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

            // Process dependency chain updates when quantities change
            if ($workOrder->isDirty(['ok_qtys', 'scrapped_qtys'])) {
                // Auto-generate batch keys for root work orders in groups
                if ($workOrder->work_order_group_id &&
                    $workOrder->is_dependency_root &&
                    $workOrder->isDirty('ok_qtys')) {
                    $workOrder->autoGenerateBatchKeysFromQuantities();
                }
                $workOrder->processDependencyChainUpdates();
            }
        });
    }

    /**
     * Check for scheduling conflicts when planning a new Work Order
     *
     * @param  int  $machineId
     * @param Carbon $newStartTime
     * @param Carbon $newEndTime
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
            $actualStartTime = Carbon::parse($startLog->changed_at);
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
                ? Carbon::parse($runningWorkOrder->end_time)->format('M d, H:i')
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

            $scheduledStart = Carbon::parse($conflictingScheduledWO->start_time)->format('M d, H:i');
            $scheduledEnd = Carbon::parse($conflictingScheduledWO->end_time)->format('M d, H:i');

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
        $startTime = Carbon::parse($workOrderData['start_time']);
        $endTime = Carbon::parse($workOrderData['end_time']);
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
            $operator = Operator::find($operatorId);
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
            $conflictEnd = Carbon::parse($conflict['planned_end']);
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
                $latestConflictEnd = Carbon::parse($conflict['planned_end']);
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

    /**
     * Check if all dependencies for this work order are satisfied
     *
     * @return bool
     */
    public function areDependenciesSatisfied(): bool
    {
        // If this is a root work order (no dependencies), it's always ready
        if ($this->is_dependency_root) {
            return true;
        }

        // Get all dependencies where this work order is the successor
        $dependencies = WorkOrderDependency::where('successor_work_order_id', $this->id)
            ->where('work_order_group_id', $this->work_order_group_id)
            ->get();

        if ($dependencies->isEmpty()) {
            // No dependencies defined, treat as ready if in a group
            return $this->work_order_group_id !== null;
        }

        // Check if all dependencies are satisfied
        foreach ($dependencies as $dependency) {
            if (!$dependency->is_satisfied) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get incoming dependencies (where this work order is the successor)
     */
    public function getIncomingDependencies()
    {
        return WorkOrderDependency::where('successor_work_order_id', $this->id)
            ->where('work_order_group_id', $this->work_order_group_id);
    }

    /**
     * Update work order status based on dependency satisfaction
     *
     * @return bool Whether the status was changed
     */
    public function updateStatusBasedOnDependencies(): bool
    {
        // Only process work orders that are in a group and currently waiting
        if (!$this->work_order_group_id || $this->status !== 'Waiting') {
            return false;
        }

        // Check if dependencies are satisfied
        if ($this->areDependenciesSatisfied()) {
            // Get details of which dependencies were satisfied for logging
            $satisfiedDependencies = $this->getIncomingDependencies()
                ->where('is_satisfied', true)
                ->with(['predecessor'])
                ->get();

            $triggeringWorkOrders = $satisfiedDependencies->map(function ($dependency) {
                return [
                    'predecessor_id' => $dependency->predecessor_work_order_id,
                    'predecessor_unique_id' => $dependency->predecessor->unique_id,
                    'dependency_type' => $dependency->dependency_type,
                    'required_quantity' => $dependency->required_quantity,
                    'satisfied_at' => $dependency->satisfied_at,
                ];
            });

            $this->update([
                'status' => 'Assigned',
                'dependency_status' => 'ready'
            ]);

            Log::info('Work order status updated from Waiting to Assigned due to dependency satisfaction', [
                'work_order_id' => $this->id,
                'unique_id' => $this->unique_id,
                'group_id' => $this->work_order_group_id,
                'group_name' => $this->workOrderGroup?->name,
                'triggering_work_orders' => $triggeringWorkOrders->toArray(),
                'timestamp' => now()->toISOString(),
            ]);

            // Create a work order log entry for this status change
            $this->createWorkOrderLog('Assigned');

            // Create a frontend-visible log entry
            WorkOrderGroupLog::logDependencySatisfied($this, $triggeringWorkOrders->toArray());

            return true;
        }

        return false;
    }

    /**
     * Process dependency chain updates when this work order's quantities change
     * This should be called when ok_qtys or scrapped_qtys are updated
     *
     * @return void
     */
    public function processDependencyChainUpdates(): void
    {
        if (!$this->work_order_group_id) {
            return;
        }

        // Find all dependencies where this work order is the predecessor
        $dependentWorkOrders = WorkOrderDependency::where('predecessor_work_order_id', $this->id)
            ->where('work_order_group_id', $this->work_order_group_id)
            ->with('successor')
            ->get();

        foreach ($dependentWorkOrders as $dependency) {
            // Update the dependency satisfaction status
            $dependency->checkSatisfaction();

            // If dependency is now satisfied, check if successor can be moved to Assigned
            if ($dependency->is_satisfied && $dependency->successor) {
                $dependency->successor->updateStatusBasedOnDependencies();
            }
        }

        // Also trigger group-wide dependency check for any other waiting work orders
        $this->workOrderGroup?->updateWaitingWorkOrderStatuses();
    }

    /**
     * Initialize work order statuses in a group based on dependencies
     * Called when a work order group is activated
     *
     * @return void
     */
    public function initializeGroupWorkOrderStatuses(): void
    {
        if (!$this->work_order_group_id) {
            return;
        }

        $group = $this->workOrderGroup;
        if (!$group) {
            return;
        }

        // Get all work orders in the group
        $workOrders = $group->workOrders()->get();

        foreach ($workOrders as $workOrder) {
            if ($workOrder->is_dependency_root) {
                // Root work orders start as Assigned
                $workOrder->update([
                    'status' => 'Assigned',
                    'dependency_status' => 'ready'
                ]);
            } else {
                // Non-root work orders start as Waiting until dependencies are satisfied
                $workOrder->update([
                    'status' => 'Waiting',
                    'dependency_status' => 'blocked'
                ]);
            }
        }

        Log::info('Initialized work order statuses for group', [
            'group_id' => $group->id,
            'work_orders_count' => $workOrders->count()
        ]);
    }

    /**
     * Check if this work order uses batch system (only for grouped work orders)
     */
    public function usesBatchSystem(): bool
    {
        return $this->work_order_group_id !== null;
    }

    /**
     * Get available keys from this work order for consumption
     */
    public function getAvailableKeys()
    {
        if (!$this->usesBatchSystem()) {
            return collect();
        }

        return $this->batchKeys()->where('is_consumed', false)->get();
    }

    /**
     * Create a new batch for this work order
     */
    public function createBatch(int $plannedQuantity, array $keysRequired = []): ?WorkOrderBatch
    {
        if (!$this->usesBatchSystem()) {
            return null; // Only grouped work orders use batches
        }

        return WorkOrderBatch::createBatch($this, $plannedQuantity, $keysRequired);
    }

    /**
     * Get the current active batch for this work order
     */
    public function getCurrentBatch(): ?WorkOrderBatch
    {
        return $this->batches()
            ->whereIn('status', ['planned', 'in_progress'])
            ->orderBy('batch_number', 'desc')
            ->first();
    }

    /**
     * Get completed batches for this work order
     */
    public function getCompletedBatches()
    {
        return $this->batches()
            ->where('status', 'completed')
            ->orderBy('batch_number', 'asc')
            ->get();
    }

    /**
     * Calculate total quantity produced across all completed batches
     */
    public function getTotalBatchQuantity(): int
    {
        if (!$this->usesBatchSystem()) {
            return $this->ok_qtys ?? 0; // Use traditional quantity for individual WOs
        }

        return $this->batches()
            ->where('status', 'completed')
            ->sum('actual_quantity');
    }

    /**
     * Get batch progress summary
     */
    public function getBatchProgress(): array
    {
        if (!$this->usesBatchSystem()) {
            return [
                'total_planned' => $this->qty,
                'total_completed' => $this->ok_qtys ?? 0,
                'percentage' => $this->qty > 0 ? (($this->ok_qtys ?? 0) / $this->qty) * 100 : 0,
                'batches_completed' => 0,
                'batches_total' => 0,
            ];
        }

        $totalPlanned = $this->batches()->sum('planned_quantity');
        $totalCompleted = $this->batches()->where('status', 'completed')->sum('actual_quantity');
        $batchesCompleted = $this->batches()->where('status', 'completed')->count();
        $batchesTotal = $this->batches()->count();

        return [
            'total_planned' => $totalPlanned,
            'total_completed' => $totalCompleted,
            'percentage' => $totalPlanned > 0 ? ($totalCompleted / $totalPlanned) * 100 : 0,
            'batches_completed' => $batchesCompleted,
            'batches_total' => $batchesTotal,
        ];
    }

    /**
     * Check if this work order can start a new batch
     */
    public function canStartNewBatch(): bool
    {
        if (!$this->usesBatchSystem()) {
            return false; // Individual work orders don't use batches
        }

        // Check if there's already a batch in progress
        $currentBatch = $this->getCurrentBatch();
        if ($currentBatch && $currentBatch->status === 'in_progress') {
            return false; // Can't start new batch while one is in progress
        }

        // For dependency-based work orders, check if dependencies are satisfied
        if ($this->work_order_group_id && !$this->is_dependency_root) {
            return $this->areDependenciesSatisfied() && $this->hasRequiredKeys();
        }

        return true;
    }

    /**
     * Check if this work order has all required keys available
     */
    public function hasRequiredKeys(): bool
    {
        if (!$this->usesBatchSystem() || $this->is_dependency_root) {
            return true; // Root work orders don't need keys
        }

        $dependencies = WorkOrderDependency::where('successor_work_order_id', $this->id)
            ->where('work_order_group_id', $this->work_order_group_id)
            ->with('predecessor')
            ->get();

        foreach ($dependencies as $dependency) {
            $availableKeys = $dependency->predecessor->getAvailableKeys();
            if ($availableKeys->isEmpty()) {
                return false; // No keys available from this predecessor
            }
        }

        return true;
    }

    /**
     * Check if operator can change work order status
     */
    public function canOperatorChangeStatus(string $newStatus): array
    {
        $result = [
            'can_change' => true,
            'reason' => null,
            'required_action' => null
        ];

        // For grouped work orders, enforce batch system
        if ($this->usesBatchSystem()) {
            $currentBatch = $this->getCurrentBatch();

            if ($newStatus === 'Start') {
                if (!$currentBatch || $currentBatch->status !== 'in_progress') {
                    $result['can_change'] = false;
                    $result['reason'] = 'No active batch in progress';
                    $result['required_action'] = 'start_new_batch';
                }
            } elseif (in_array($newStatus, ['Hold', 'Completed'])) {
                if (!$currentBatch || $currentBatch->status !== 'in_progress') {
                    $result['can_change'] = false;
                    $result['reason'] = 'No active batch to hold/complete';
                    $result['required_action'] = 'start_new_batch';
                }
            }

        }

        return $result;
    }

    /**
     * Get status options available to operator
     */
    public function getOperatorStatusOptions(): array
    {
        $currentStatus = $this->status;
        $options = [];

        if ($this->usesBatchSystem()) {
            $currentBatch = $this->getCurrentBatch();
            $hasActiveBatch = $currentBatch && $currentBatch->status === 'in_progress';

            switch ($currentStatus) {
                case 'Assigned':
                    if ($hasActiveBatch) {
                        $options['Start'] = 'Start';
                    } else {
                        $options['Assigned'] = 'Assigned (Start new batch first)';
                    }
                    break;

                case 'Start':
                    if ($hasActiveBatch) {
                        $options['Hold'] = 'Hold';
                        $options['Completed'] = 'Completed';
                    } else {
                        $options['Start'] = 'Start (No active batch)';
                    }
                    break;

                case 'Hold':
                    if ($hasActiveBatch) {
                        $options['Start'] = 'Start';
                    } else {
                        $options['Hold'] = 'Hold (Start new batch first)';
                    }
                    break;

                case 'Completed':
                    $options['Completed'] = 'Completed';
                    break;

                case 'Waiting':
                    if ($this->areDependenciesSatisfied() && $this->hasRequiredKeys()) {
                        $options['Assigned'] = 'Assigned';
                    } else {
                        $options['Waiting'] = 'Waiting (Dependencies not satisfied)';
                    }
                    break;
            }
        } else {
            // Individual work orders - traditional status options
            switch ($currentStatus) {
                case 'Assigned':
                    $options['Start'] = 'Start';
                    break;
                case 'Start':
                    $options['Hold'] = 'Hold';
                    $options['Completed'] = 'Completed';
                    break;
                case 'Hold':
                    $options['Start'] = 'Start';
                    break;
                case 'Completed':
                    $options['Completed'] = 'Completed';
                    break;
            }
        }

        return $options;
    }

    /**
     * Get required keys information for this work order
     */
    public function getRequiredKeysInfo(): array
    {
        if (!$this->usesBatchSystem() || $this->is_dependency_root) {
            return [];
        }

        $dependencies = WorkOrderDependency::where('successor_work_order_id', $this->id)
            ->where('work_order_group_id', $this->work_order_group_id)
            ->with('predecessor')
            ->get();

        $keysInfo = [];
        foreach ($dependencies as $dependency) {
            $availableKeys = $dependency->predecessor->getAvailableKeys();
            $keysInfo[] = [
                'predecessor_id' => $dependency->predecessor_work_order_id,
                'predecessor_name' => $dependency->predecessor->unique_id,
                'dependency_type' => $dependency->dependency_type,
                'required_quantity' => $dependency->required_quantity,
                'available_keys_count' => $availableKeys->count(),
                'available_keys' => $availableKeys->map(function ($key) {
                    return [
                        'id' => $key->id,
                        'key_code' => $key->key_code,
                        'quantity_produced' => $key->quantity_produced,
                        'generated_at' => $key->generated_at,
                    ];
                }),
                'is_satisfied' => $availableKeys->isNotEmpty()
            ];
        }

        return $keysInfo;
    }

    /**
     * Override quantity methods to use batch system for grouped work orders
     */
    public function getOkQuantity(): int
    {
        if ($this->usesBatchSystem()) {
            return $this->getTotalBatchQuantity();
        }

        return $this->ok_qtys ?? 0;
    }

    /**
     * Check if work order is completed (all planned quantity produced)
     */
    public function isCompleted(): bool
    {
        if ($this->usesBatchSystem()) {
            $progress = $this->getBatchProgress();
            return $progress['total_completed'] >= $this->qty;
        }

        return ($this->ok_qtys ?? 0) >= $this->qty;
    }

    /**
     * Auto-generate batch keys for root work orders when quantities are updated
     * This ensures dependent work orders have keys available for consumption
     */
    public function autoGenerateBatchKeysFromQuantities(): void
    {
        // Only process if this is a root work order in a group
        if (!$this->work_order_group_id || !$this->is_dependency_root) {
            return;
        }

        // Check if there are any manually created batches for this work order
        // If manual batches exist, disable auto-generation to prevent conflicts
        $hasManualBatches = $this->batches()->exists();
        if ($hasManualBatches) {
            \Illuminate\Support\Facades\Log::info('Skipping auto-generation - manual batches exist for work order', [
                'work_order_id' => $this->id,
                'work_order_unique_id' => $this->unique_id,
                'manual_batches_count' => $this->batches()->count()
            ]);
            return;
        }

        // Get the configured batch size for this work order
        $workOrderGroup = $this->workOrderGroup;
        $batchSize = $workOrderGroup->getBatchSizeForWorkOrder($this->id);

        if (!$batchSize) {
            // No batch configuration set, use default batch size of 25
            $batchSize = 25;
        }

        $currentOkQtys = $this->ok_qtys ?? 0;
        $existingBatchQuantity = $this->getTotalBatchQuantity();

        // Calculate how many new quantities need batch keys
        $unbatchedQuantity = $currentOkQtys - $existingBatchQuantity;

        if ($unbatchedQuantity <= 0) {
            return; // No new quantities to process
        }

        // Create and complete batches for the unbatched quantities
        while ($unbatchedQuantity > 0) {
            $batchQuantity = min($batchSize, $unbatchedQuantity);

            // Create a new batch
            $batch = WorkOrderBatch::createBatch($this, $batchQuantity);

            // Start the batch (no keys required for root work orders)
            $batch->startBatch();

            // Complete the batch immediately to generate a key
            $batch->completeBatch($batchQuantity);

            $unbatchedQuantity -= $batchQuantity;

            \Illuminate\Support\Facades\Log::info('Auto-generated batch key for root work order', [
                'work_order_id' => $this->id,
                'work_order_unique_id' => $this->unique_id,
                'batch_number' => $batch->batch_number,
                'batch_quantity' => $batchQuantity,
                'remaining_unbatched' => $unbatchedQuantity
            ]);
        }

        // Update dependent work order statuses in case they can now proceed
        if ($workOrderGroup) {
            $workOrderGroup->updateWaitingWorkOrderStatuses();
        }
    }
}
