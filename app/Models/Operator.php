<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operator extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'operator_proficiency_id',
        'shift_id',
        'user_id',
        'factory_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function operator_proficiency()
    {
        return $this->belongsTo(OperatorProficiency::class, 'operator_proficiency_id');
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    /**
     * Get scheduled work orders for this operator within a date range
     * Only returns work orders that fall within the operator's shift hours
     */
    public function getScheduledWorkOrders($startDate, $endDate)
    {
        return $this->workOrders()
            ->where('work_orders.factory_id', $this->factory_id)
            ->whereIn('status', ['Assigned', 'Start', 'Hold', 'Completed'])
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where(function ($query) use ($startDate, $endDate) {
                // Include work orders that overlap with the date range
                // Work order overlaps if: start_time <= endDate AND end_time >= startDate
                $query->where('start_time', '<=', $endDate)
                    ->where('end_time', '>=', $startDate);
            })
            ->with(['machine', 'bom.purchaseOrder.partNumber'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get currently running work order for this operator
     */
    public function getRunningWorkOrder()
    {
        return $this->workOrders()
            ->where('work_orders.factory_id', $this->factory_id)
            ->where('status', 'Start')
            ->with(['machine', 'bom.purchaseOrder.partNumber'])
            ->first();
    }

    /**
     * Get gantt calendar data for the operator schedule with planned vs actual bars
     */
    public function getGanttCalendarData($viewType = 'week', $date = null)
    {
        $date = $date ? Carbon::parse($date)->setTimezone(config('app.timezone')) : now()->setTimezone(config('app.timezone'));

        // Define date ranges based on view type
        switch ($viewType) {
            case 'day':
                $startDate = $date->copy()->startOfDay();
                $endDate = $date->copy()->endOfDay();
                break;
            case 'week':
                $startDate = $date->copy()->startOfWeek(Carbon::MONDAY);
                $endDate = $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(5)->endOfDay(); // Monday to Saturday
                break;
            case 'month':
                $startDate = $date->copy()->startOfMonth();
                $endDate = $date->copy()->endOfMonth();
                break;
            default:
                $startDate = $date->copy()->startOfWeek(Carbon::MONDAY);
                $endDate = $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(5)->endOfDay(); // Monday to Saturday
        }

        $workOrders = $this->getScheduledWorkOrders($startDate, $endDate);
        $plannedBars = [];
        $actualBars = [];
        $shiftBlocks = [];

        foreach ($workOrders as $workOrder) {
            $machineName = $workOrder->machine
                ? "{$workOrder->machine->assetId} - {$workOrder->machine->name}"
                : 'No Machine';

            $partNumber = $workOrder->bom?->purchaseOrder?->partNumber?->partnumber ?? 'Unknown Part';

            // Planned bar data
            $shiftConflict = ! $this->isWorkOrderWithinShift($workOrder);

            $plannedBars[] = [
                'id' => "planned-{$workOrder->id}",
                'work_order_id' => $workOrder->id,
                'unique_id' => $workOrder->unique_id,
                'title' => "WO #{$workOrder->unique_id}",
                'subtitle' => $partNumber,
                'start' => $workOrder->start_time->format('c'),
                'end' => $workOrder->end_time->format('c'),
                'status' => $workOrder->status,
                'machine' => $machineName,
                'shift_conflict' => $shiftConflict,
                'type' => 'planned',
                'backgroundColor' => '#3b82f6', // Blue for planned
                'borderColor' => '#1e40af',
                'textColor' => '#ffffff',
            ];

            // Actual bar data (if exists)
            $actualStartLog = $workOrder->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
            $actualEndLog = $workOrder->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();

            if ($actualStartLog) {
                $actualStart = Carbon::parse($actualStartLog->changed_at)->setTimezone(config('app.timezone'));
                $actualEnd = $actualEndLog
                    ? Carbon::parse($actualEndLog->changed_at)->setTimezone(config('app.timezone'))
                    : null; // Still running if no end log

                // If still running, estimate end time or use current time + remaining duration
                if (! $actualEnd) {
                    $plannedDuration = $workOrder->start_time->diffInMinutes($workOrder->end_time);
                    $actualEnd = $actualStart->copy()->addMinutes($plannedDuration);

                    // Cap at current time for visual clarity
                    $currentTime = now()->setTimezone(config('app.timezone'));
                    if ($actualEnd > $currentTime) {
                        $actualEnd = $currentTime;
                    }
                }

                // Calculate progress percentage
                $totalQty = $workOrder->qty ?? 0;
                $okQtys = $workOrder->ok_qtys ?? 0;
                $progress = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;

                // Determine status color
                $statusColor = $this->getActualStatusColor($actualEndLog ? $actualEndLog->status : $workOrder->status, $progress);

                $actualBars[] = [
                    'id' => "actual-{$workOrder->id}",
                    'work_order_id' => $workOrder->id,
                    'unique_id' => $workOrder->unique_id,
                    'title' => $progress > 0 ? "{$progress}%" : "WO #{$workOrder->unique_id}",
                    'subtitle' => $partNumber,
                    'start' => $actualStart->format('c'),
                    'end' => $actualEnd->format('c'),
                    'status' => $actualEndLog ? $actualEndLog->status : $workOrder->status,
                    'machine' => $machineName,
                    'type' => 'actual',
                    'progress' => $progress,
                    'backgroundColor' => '#e5e7eb', // Gray background
                    'progressColor' => $statusColor,
                    'borderColor' => '#9ca3af',
                    'textColor' => '#374151',
                    'is_running' => ! $actualEndLog, // No end log means still running
                ];
            }
        }

        // Add shift blocks
        $shiftBlocks = $this->getGanttShiftBlocks($startDate, $endDate, $viewType);

        return [
            'planned_bars' => $plannedBars,
            'actual_bars' => $actualBars,
            'shift_blocks' => $shiftBlocks,
            'date_range' => [
                'start' => $startDate->format('c'),
                'end' => $endDate->format('c'),
            ],
        ];
    }

    /**
     * Get shift blocks for gantt chart background
     */
    private function getGanttShiftBlocks($startDate, $endDate, $viewType)
    {
        if (! $this->shift) {
            return [];
        }

        $blocks = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            // Skip weekends for shift display (assuming 5-day work week)
            if ($currentDate->isWeekend()) {
                $currentDate->addDay();

                continue;
            }

            $shiftStart = $currentDate->copy()->setTimeFromTimeString($this->shift->start_time);
            $shiftEnd = $currentDate->copy()->setTimeFromTimeString($this->shift->end_time);

            // Handle overnight shifts
            if ($shiftEnd < $shiftStart) {
                $shiftEnd->addDay();
            }
            
            // For Gantt chart visualization, extend shift to end of the time block
            // Weekly view uses 2-hour blocks, so extend to end of that block
            if ($viewType === 'week') {
                // Round up to the next even hour and go to end of that 2-hour block
                $endHour = $shiftEnd->hour;
                if ($endHour % 2 === 1) {
                    // If odd hour (like 21:00), go to end of next even hour (22:00 -> 24:00)
                    $shiftEnd->addHour()->endOfHour();
                } else {
                    // If even hour (like 22:00), go to end of next 2-hour block (22:00 -> 24:00)
                    $shiftEnd->addHours(2)->startOfHour()->subSecond();
                }
            } else {
                // For day/month view, just extend to end of hour
                $shiftEnd->endOfHour();
            }

            $blocks[] = [
                'id' => "shift-{$this->id}-{$currentDate->format('Y-m-d')}",
                'start' => $shiftStart->format('c'),
                'end' => $shiftEnd->format('c'),
                'shift_name' => $this->shift->name,
                'backgroundColor' => '#e5f3ff',
                'borderColor' => '#3b82f6',
            ];

            $currentDate->addDay();
        }

        return $blocks;
    }

    /**
     * Get color for actual work order status
     */
    private function getActualStatusColor(string $status, int $progress): string
    {
        return match (strtolower($status)) {
            'start' => $progress > 0 ? '#eab308' : '#f59e0b', // Yellow/amber for running
            'hold' => '#ef4444',        // Red for hold
            'completed' => '#22c55e',   // Green for completed
            'closed' => '#a855f7',      // Purple for closed
            default => '#6b7280',       // Gray for unknown
        };
    }

    /**
     * Get calendar events for the operator schedule with shift awareness
     */
    public function getCalendarEvents($viewType = 'week', $date = null)
    {
        $date = $date ? Carbon::parse($date) : now();

        // Define date ranges based on view type
        switch ($viewType) {
            case 'day':
                $startDate = $date->copy()->startOfDay();
                $endDate = $date->copy()->endOfDay();
                break;
            case 'week':
                $startDate = $date->copy()->startOfWeek(Carbon::MONDAY);
                $endDate = $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(5)->endOfDay(); // Monday to Saturday
                break;
            case 'month':
                $startDate = $date->copy()->startOfMonth();
                $endDate = $date->copy()->endOfMonth();
                break;
            default:
                $startDate = $date->copy()->startOfWeek(Carbon::MONDAY);
                $endDate = $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(5)->endOfDay(); // Monday to Saturday
        }

        $workOrders = $this->getScheduledWorkOrders($startDate, $endDate);
        $events = [];

        foreach ($workOrders as $workOrder) {
            $machineName = $workOrder->machine
                ? "{$workOrder->machine->assetId} - {$workOrder->machine->name}"
                : 'No Machine';

            $partNumber = $workOrder->bom?->purchaseOrder?->partNumber?->partnumber ?? 'Unknown Part';

            // Check if work order time conflicts with operator's shift
            $shiftConflict = ! $this->isWorkOrderWithinShift($workOrder);

            $events[] = [
                'id' => "wo-{$workOrder->id}",
                'title' => "WO #{$workOrder->unique_id}",
                'subtitle' => $partNumber,
                'start' => $workOrder->start_time->format('c'),
                'end' => $workOrder->end_time->format('c'),
                'status' => $workOrder->status,
                'machine' => $machineName,
                'work_order_id' => $workOrder->id,
                'unique_id' => $workOrder->unique_id,
                'shift_conflict' => $shiftConflict,
                'backgroundColor' => $shiftConflict
                    ? '#dc2626' // Red for shift conflicts
                    : $this->getWorkOrderStatusColor($workOrder->status),
                'borderColor' => $shiftConflict
                    ? '#b91c1c'
                    : $this->getWorkOrderStatusBorderColor($workOrder->status),
                'textColor' => '#ffffff',
            ];
        }

        // Add shift blocks to show operator availability
        $shiftEvents = $this->getShiftCalendarEvents($startDate, $endDate, $viewType);
        $events = array_merge($events, $shiftEvents);

        return $events;
    }

    /**
     * Get shift calendar events to show operator availability
     */
    private function getShiftCalendarEvents($startDate, $endDate, $viewType)
    {
        if (! $this->shift) {
            return [];
        }

        $events = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            // Skip weekends for shift display (assuming 5-day work week)
            if ($currentDate->isWeekend()) {
                $currentDate->addDay();

                continue;
            }

            $shiftStart = $currentDate->copy()->setTimeFromTimeString($this->shift->start_time);
            $shiftEnd = $currentDate->copy()->setTimeFromTimeString($this->shift->end_time);

            // Handle overnight shifts
            if ($shiftEnd < $shiftStart) {
                $shiftEnd->addDay();
            }

            $events[] = [
                'id' => "shift-{$this->id}-{$currentDate->format('Y-m-d')}",
                'title' => "Shift: {$this->shift->name}",
                'subtitle' => 'Available',
                'start' => $shiftStart->format('c'),
                'end' => $shiftEnd->format('c'),
                'status' => 'shift',
                'backgroundColor' => '#e5f3ff',
                'borderColor' => '#3b82f6',
                'textColor' => '#1e40af',
                'className' => 'shift-block',
            ];

            $currentDate->addDay();
        }

        return $events;
    }

    /**
     * Check if a work order falls within the operator's shift hours
     */
    public function isWorkOrderWithinShift($workOrder): bool
    {
        if (! $this->shift || ! $workOrder->start_time || ! $workOrder->end_time) {
            return true; // If no shift defined, assume it's valid
        }

        $workOrderStart = Carbon::parse($workOrder->start_time);
        $workOrderEnd = Carbon::parse($workOrder->end_time);

        // Get shift times for the work order date
        $shiftStart = $workOrderStart->copy()->setTimeFromTimeString($this->shift->start_time);
        $shiftEnd = $workOrderStart->copy()->setTimeFromTimeString($this->shift->end_time);

        // Handle overnight shifts
        if ($shiftEnd < $shiftStart) {
            // If work order starts after midnight, check against previous day's shift
            if ($workOrderStart->hour < 12) {
                $shiftStart->subDay();
            } else {
                $shiftEnd->addDay();
            }
        }

        // Check if work order is completely within shift hours
        return $workOrderStart >= $shiftStart && $workOrderEnd <= $shiftEnd;
    }

    /**
     * Check for scheduling conflicts when planning a new Work Order for this operator
     * Considers both operator availability and shift constraints
     *
     * @param Carbon $newStartTime
     * @param Carbon $newEndTime
     * @param  int  $factoryId  - Factory ID for multi-tenancy
     * @param  int|null  $excludeWorkOrderId  - Exclude current WO when updating
     * @return array
     */
    public function checkSchedulingConflicts($newStartTime, $newEndTime, $factoryId, $excludeWorkOrderId = null)
    {
        $conflicts = [];

        // Check shift conflicts first
        if (! $this->isTimeWithinShift($newStartTime, $newEndTime)) {
            $conflicts[] = [
                'type' => 'shift_conflict',
                'message' => "Work order time conflicts with operator's shift ({$this->shift->name}: {$this->shift->start_time} - {$this->shift->end_time})",
                'shift_name' => $this->shift->name,
                'shift_start' => $this->shift->start_time,
                'shift_end' => $this->shift->end_time,
            ];
        }

        // Check existing work order conflicts
        $existingWorkOrders = $this->workOrders()
            ->where('factory_id', $factoryId)
            ->whereIn('status', ['Assigned', 'Start', 'Hold'])
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->when($excludeWorkOrderId, function ($query) use ($excludeWorkOrderId) {
                return $query->where('id', '!=', $excludeWorkOrderId);
            })
            ->get();

        foreach ($existingWorkOrders as $existingWO) {
            $conflictStartTime = $existingWO->start_time;
            $conflictEndTime = $existingWO->end_time;

            // Time-based conflict detection
            if ($newStartTime < $conflictEndTime && $newEndTime > $conflictStartTime) {
                $conflicts[] = [
                    'type' => 'work_order_conflict',
                    'work_order_id' => $existingWO->id,
                    'work_order_unique_id' => $existingWO->unique_id,
                    'status' => $existingWO->status,
                    'planned_start' => $existingWO->start_time,
                    'planned_end' => $existingWO->end_time,
                    'machine_id' => $existingWO->machine_id,
                    'overlap_duration' => $this->calculateOverlapDuration($newStartTime, $newEndTime, $conflictStartTime, $conflictEndTime),
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Check if given time range is within operator's shift
     */
    private function isTimeWithinShift($startTime, $endTime): bool
    {
        if (! $this->shift) {
            return true; // No shift constraint
        }

        $shiftStart = $startTime->copy()->setTimeFromTimeString($this->shift->start_time);
        $shiftEnd = $startTime->copy()->setTimeFromTimeString($this->shift->end_time);

        // Handle overnight shifts
        if ($shiftEnd < $shiftStart) {
            if ($startTime->hour < 12) {
                $shiftStart->subDay();
            } else {
                $shiftEnd->addDay();
            }
        }

        return $startTime >= $shiftStart && $endTime <= $shiftEnd;
    }

    /**
     * Calculate overlap duration between two time periods
     */
    private function calculateOverlapDuration($newStart, $newEnd, $existingStart, $existingEnd)
    {
        $overlapStart = max($newStart, $existingStart);
        $overlapEnd = min($newEnd, $existingEnd);

        return $overlapStart->diffInMinutes($overlapEnd);
    }

    /**
     * Check if operator is currently occupied (has WO in "Start" status)
     */
    public function isCurrentlyOccupied($factoryId): bool
    {
        return $this->workOrders()
            ->where('factory_id', $factoryId)
            ->where('status', 'Start')
            ->exists();
    }

    /**
     * Get current running Work Order for this operator
     */
    public function getCurrentRunningWorkOrder($factoryId)
    {
        return $this->workOrders()
            ->where('factory_id', $factoryId)
            ->where('status', 'Start')
            ->first();
    }

    /**
     * Get background color for work order status
     */
    private function getWorkOrderStatusColor(string $status): string
    {
        return match ($status) {
            'Start' => '#ef4444',      // Red - Currently running
            'Assigned' => '#f97316',    // Orange - Assigned/Planned
            'Hold' => '#eab308',        // Yellow - On hold
            'Completed' => '#22c55e',   // Green - Completed
            default => '#6b7280',       // Gray - Unknown status
        };
    }

    /**
     * Get border color for work order status
     */
    private function getWorkOrderStatusBorderColor(string $status): string
    {
        return match ($status) {
            'Start' => '#dc2626',       // Darker red
            'Assigned' => '#ea580c',    // Darker orange
            'Hold' => '#ca8a04',        // Darker yellow
            'Completed' => '#16a34a',   // Darker green
            default => '#4b5563',       // Darker gray
        };
    }
}
