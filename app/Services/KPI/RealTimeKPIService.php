<?php

namespace App\Services\KPI;

use App\Models\Factory;
use App\Models\Machine;

class RealTimeKPIService extends BaseKPIService
{
    public function __construct(Factory $factory)
    {
        parent::__construct($factory, 'tier_1');
    }

    /**
     * Get current machine status snapshot
     * Returns machines grouped by status: Running, Hold, Scheduled, Idle
     */
    public function getCurrentMachineStatus(bool $skipCache = false): array
    {
        $callback = function () {
            $machines = Machine::where('factory_id', $this->factory->id)
                ->with([
                    'workOrders' => function ($query) {
                        // Get work orders SCHEDULED/PLANNED for TODAY only (based on start_time)
                        // Start = actively running today
                        // Hold = on hold today
                        // Assigned = scheduled to start today
                        $today = now()->startOfDay();
                        $endOfToday = now()->endOfDay();

                        $query->whereIn('status', ['Start', 'Assigned', 'Hold'])
                            ->whereBetween('start_time', [$today, $endOfToday])
                            ->with([
                                'operator.user:id,first_name,last_name',
                                'bom.purchaseOrder.partNumber:id,partnumber',
                                'workOrderLogs' => function ($q) {
                                    $q->where('status', 'Hold')
                                        ->with('holdReason:id,code,description')
                                        ->latest('changed_at')
                                        ->limit(1);
                                },
                            ])
                            ->orderBy('created_at', 'desc');
                    },
                ])
                ->get();

            $statusGroups = [
                'running' => ['count' => 0, 'machines' => []],
                'hold' => ['count' => 0, 'machines' => []],
                'scheduled' => ['count' => 0, 'machines' => []],
                'idle' => ['count' => 0, 'machines' => []],
            ];

            foreach ($machines as $machine) {
                $workOrders = $machine->workOrders;

                // Determine machine status based on work orders
                // Priority: Hold > Start > Assigned > Idle
                if ($workOrders->isEmpty()) {
                    // No work orders = Idle
                    $statusGroups['idle']['machines'][] = [
                        'id' => $machine->id,
                        'name' => $machine->name,
                        'asset_id' => $machine->assetId,
                        'status' => 'idle',
                    ];
                    $statusGroups['idle']['count']++;
                } elseif ($workOrders->contains('status', 'Hold')) {
                    // Has work orders on hold = Hold (HIGHEST PRIORITY - requires attention)
                    $holdWOs = $workOrders->where('status', 'Hold');
                    $primaryHoldWO = $holdWOs->first();

                    $operatorName = $primaryHoldWO?->operator?->user
                        ? "{$primaryHoldWO->operator->user->first_name} {$primaryHoldWO->operator->user->last_name}"
                        : 'Unassigned';

                    $partNumber = $primaryHoldWO?->bom?->purchaseOrder?->partNumber?->partnumber ?? 'N/A';

                    // Get hold reason from the latest Hold log entry
                    $holdLog = $primaryHoldWO?->workOrderLogs->first();
                    $holdReason = $holdLog?->holdReason?->description ?? 'No reason specified';
                    $holdSince = $holdLog?->changed_at ?? $primaryHoldWO?->updated_at;

                    $statusGroups['hold']['machines'][] = [
                        'id' => $machine->id,
                        'name' => $machine->name,
                        'asset_id' => $machine->assetId,
                        'status' => 'hold',
                        'hold_wo_count' => $holdWOs->count(),
                        'primary_wo_id' => $primaryHoldWO?->id,
                        'primary_wo_number' => $primaryHoldWO?->unique_id ?? 'N/A',
                        'unique_id' => $primaryHoldWO?->unique_id ?? 'N/A',
                        'operator' => $operatorName,
                        'part_number' => $partNumber,
                        'hold_reason' => $holdReason,
                        'hold_since' => $holdSince?->toDateTimeString(),
                        'hold_duration' => $holdSince ? now()->diffForHumans($holdSince, true) : 'Unknown',
                        'hold_priority' => $holdSince ? now()->diffInMinutes($holdSince) : 0,
                        'wo_numbers' => $holdWOs->pluck('unique_id')->take(3)->toArray(),
                    ];
                    $statusGroups['hold']['count']++;
                } elseif ($workOrders->contains('status', 'Start')) {
                    // Has at least one running work order = Running
                    $runningWO = $workOrders->firstWhere('status', 'Start');

                    $operatorName = $runningWO?->operator?->user
                        ? "{$runningWO->operator->user->first_name} {$runningWO->operator->user->last_name}"
                        : 'Unassigned';

                    $partNumber = $runningWO?->bom?->purchaseOrder?->partNumber?->partnumber ?? 'N/A';

                    // Calculate estimated completion time
                    $estimatedCompletion = $runningWO?->end_time
                        ? $runningWO->end_time->diffForHumans()
                        : 'N/A';

                    $statusGroups['running']['machines'][] = [
                        'id' => $machine->id,
                        'name' => $machine->name,
                        'asset_id' => $machine->assetId,
                        'status' => 'running',
                        'wo_id' => $runningWO?->id,
                        'wo_number' => $runningWO?->unique_id ?? 'N/A',
                        'unique_id' => $runningWO?->unique_id ?? 'N/A',
                        'operator' => $operatorName,
                        'part_number' => $partNumber,
                        'estimated_completion' => $estimatedCompletion,
                        'end_time' => $runningWO?->end_time?->toDateTimeString(),
                        'qty_target' => $runningWO?->qty ?? 0,
                        'qty_produced' => $runningWO?->ok_qtys ?? 0,
                        'progress_percentage' => $runningWO && $runningWO->qty > 0
                            ? round(($runningWO->ok_qtys / $runningWO->qty) * 100, 1)
                            : 0,
                    ];
                    $statusGroups['running']['count']++;
                } elseif ($workOrders->contains('status', 'Assigned')) {
                    // Has assigned work orders (but none running) = Scheduled
                    $assignedWOs = $workOrders->where('status', 'Assigned');
                    $nextWO = $assignedWOs->first();

                    $operatorName = $nextWO?->operator?->user
                        ? "{$nextWO->operator->user->first_name} {$nextWO->operator->user->last_name}"
                        : 'Unassigned';

                    $partNumber = $nextWO?->bom?->purchaseOrder?->partNumber?->partnumber ?? 'N/A';

                    $scheduledStart = $nextWO?->start_time
                        ? $nextWO->start_time->format('M d, H:i')
                        : 'Not scheduled';

                    $statusGroups['scheduled']['machines'][] = [
                        'id' => $machine->id,
                        'name' => $machine->name,
                        'asset_id' => $machine->assetId,
                        'status' => 'scheduled',
                        'assigned_wo_count' => $assignedWOs->count(),
                        'next_wo_id' => $nextWO?->id,
                        'next_wo_number' => $nextWO?->unique_id ?? 'N/A',
                        'unique_id' => $nextWO?->unique_id ?? 'N/A',
                        'operator' => $operatorName,
                        'part_number' => $partNumber,
                        'scheduled_start' => $scheduledStart,
                        'start_time' => $nextWO?->start_time?->toDateTimeString(),
                    ];
                    $statusGroups['scheduled']['count']++;
                }
            }

            // Sort machines by priority within each status group
            // Hold machines: longest hold time first (most urgent)
            if (! empty($statusGroups['hold']['machines'])) {
                usort($statusGroups['hold']['machines'], function ($a, $b) {
                    return ($b['hold_priority'] ?? 0) <=> ($a['hold_priority'] ?? 0);
                });
            }

            // Scheduled machines: earliest start time first
            if (! empty($statusGroups['scheduled']['machines'])) {
                usort($statusGroups['scheduled']['machines'], function ($a, $b) {
                    $timeA = $a['start_time'] ?? null;
                    $timeB = $b['start_time'] ?? null;

                    if ($timeA === null) {
                        return 1;
                    }
                    if ($timeB === null) {
                        return -1;
                    }

                    return $timeA <=> $timeB;
                });
            }

            // Running machines: lowest progress percentage first (recently started)
            if (! empty($statusGroups['running']['machines'])) {
                usort($statusGroups['running']['machines'], function ($a, $b) {
                    return ($a['progress_percentage'] ?? 0) <=> ($b['progress_percentage'] ?? 0);
                });
            }

            return [
                'status_groups' => $statusGroups,
                'total_machines' => $machines->count(),
                'updated_at' => now()->toDateTimeString(),
            ];
        };

        // Skip cache if requested (for manual refresh)
        if ($skipCache) {
            return $callback();
        }

        return $this->getCachedKPI('current_machine_status', $callback, 300);
    }

    /**
     * Get current work order status distribution
     * Returns work orders grouped by status: Assigned, Start, Hold, Completed, Closed
     *
     * DASHBOARD MODE (Real-Time):
     * - PLANNED: Assigned WOs scheduled for TODAY (start_time = today)
     * - REAL-TIME EXECUTION:
     *   - Start: ALL currently running (no date filter)
     *   - Hold: ALL currently on hold (no date filter)
     *   - Completed: Status changed to Completed TODAY (via work_order_logs)
     *   - Closed: Status changed to Closed TODAY (via work_order_logs)
     */
    public function getCurrentWorkOrderStatus(bool $skipCache = false): array
    {
        $callback = function () {
            $today = now()->startOfDay();
            $endOfToday = now()->endOfDay();

            // SECTION 1: PLANNED - Assigned WOs scheduled for TODAY
            $assignedWOs = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
                ->where('status', 'Assigned')
                ->whereBetween('start_time', [$today, $endOfToday])
                ->with([
                    'machine:id,name,assetId',
                    'operator.user:id,first_name,last_name',
                    'bom.purchaseOrder.partNumber:id,partnumber',
                ])
                ->orderBy('start_time', 'asc')
                ->get();

            // SECTION 2: REAL-TIME EXECUTION

            // Start: ALL currently running WOs (no date filter)
            $startWOs = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
                ->where('status', 'Start')
                ->with([
                    'machine:id,name,assetId',
                    'operator.user:id,first_name,last_name',
                    'bom.purchaseOrder.partNumber:id,partnumber',
                ])
                ->orderBy('start_time', 'asc')
                ->get();

            // Hold: ALL currently on-hold WOs (no date filter)
            $holdWOs = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
                ->where('status', 'Hold')
                ->with([
                    'machine:id,name,assetId',
                    'operator.user:id,first_name,last_name',
                    'bom.purchaseOrder.partNumber:id,partnumber',
                    'workOrderLogs' => function ($q) {
                        $q->where('status', 'Hold')
                            ->with('holdReason:id,code,description')
                            ->latest('changed_at')
                            ->limit(1);
                    },
                ])
                ->orderBy('updated_at', 'desc')
                ->get();

            // Completed: Status changed to Completed TODAY (check work_order_logs)
            $completedWOIds = \App\Models\WorkOrderLog::whereBetween('changed_at', [$today, $endOfToday])
                ->where('status', 'Completed')
                ->pluck('work_order_id')
                ->unique();

            $completedWOs = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
                ->where('status', 'Completed')
                ->whereIn('id', $completedWOIds)
                ->with([
                    'machine:id,name,assetId',
                    'operator.user:id,first_name,last_name',
                    'bom.purchaseOrder.partNumber:id,partnumber',
                ])
                ->orderBy('updated_at', 'desc')
                ->get();

            // Closed: Status changed to Closed TODAY (check work_order_logs)
            $closedWOIds = \App\Models\WorkOrderLog::whereBetween('changed_at', [$today, $endOfToday])
                ->where('status', 'Closed')
                ->pluck('work_order_id')
                ->unique();

            $closedWOs = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
                ->where('status', 'Closed')
                ->whereIn('id', $closedWOIds)
                ->with([
                    'machine:id,name,assetId',
                    'operator.user:id,first_name,last_name',
                    'bom.purchaseOrder.partNumber:id,partnumber',
                ])
                ->orderBy('updated_at', 'desc')
                ->get();

            // Combine all work orders for processing
            $workOrders = collect()
                ->merge($assignedWOs)
                ->merge($startWOs)
                ->merge($holdWOs)
                ->merge($completedWOs)
                ->merge($closedWOs);

            $statusDistribution = [
                'hold' => ['count' => 0, 'work_orders' => []],
                'start' => ['count' => 0, 'work_orders' => []],
                'assigned' => ['count' => 0, 'work_orders' => []],
                'completed' => ['count' => 0, 'work_orders' => []],
                'closed' => ['count' => 0, 'work_orders' => []],
            ];

            foreach ($workOrders as $wo) {
                $status = strtolower($wo->status);
                $operatorName = $wo->operator?->user
                    ? "{$wo->operator->user->first_name} {$wo->operator->user->last_name}"
                    : 'Unassigned';

                $partNumber = $wo->bom?->purchaseOrder?->partNumber?->partnumber ?? 'N/A';

                $woData = [
                    'id' => $wo->id,
                    'wo_number' => $wo->unique_id ?? 'N/A',
                    'machine_name' => $wo->machine?->name ?? 'N/A',
                    'machine_asset_id' => $wo->machine?->assetId ?? 'N/A',
                    'operator' => $operatorName,
                    'part_number' => $partNumber,
                    'qty_target' => $wo->qty ?? 0,
                    'qty_produced' => $wo->ok_qtys ?? 0,
                    'start_time' => $wo->start_time?->toDateTimeString(),
                    'end_time' => $wo->end_time?->toDateTimeString(),
                ];

                // Add status-specific fields
                if ($status === 'hold') {
                    $holdLog = $wo->workOrderLogs->first();
                    $woData['hold_reason'] = $holdLog?->holdReason?->description ?? 'No reason specified';
                    $woData['hold_since'] = $holdLog?->changed_at?->toDateTimeString() ?? $wo->updated_at?->toDateTimeString();
                    $woData['hold_duration'] = $holdLog?->changed_at
                        ? now()->diffForHumans($holdLog->changed_at, true)
                        : 'Unknown';
                } elseif ($status === 'start') {
                    $woData['progress_percentage'] = $wo->qty > 0
                        ? round(($wo->ok_qtys / $wo->qty) * 100, 1)
                        : 0;
                    $woData['estimated_completion'] = $wo->end_time
                        ? $wo->end_time->diffForHumans()
                        : 'N/A';
                } elseif ($status === 'assigned') {
                    $woData['scheduled_start'] = $wo->start_time
                        ? $wo->start_time->format('M d, H:i')
                        : 'Not scheduled';
                } elseif ($status === 'completed') {
                    $woData['completed_at'] = $wo->end_time?->toDateTimeString() ?? $wo->updated_at?->toDateTimeString();
                    $woData['completion_rate'] = $wo->qty > 0
                        ? round(($wo->ok_qtys / $wo->qty) * 100, 1)
                        : 0;
                } elseif ($status === 'closed') {
                    $woData['closed_at'] = $wo->end_time?->toDateTimeString() ?? $wo->updated_at?->toDateTimeString();
                    $woData['completion_rate'] = $wo->qty > 0
                        ? round(($wo->ok_qtys / $wo->qty) * 100, 1)
                        : 0;
                }

                $statusDistribution[$status]['work_orders'][] = $woData;
                $statusDistribution[$status]['count']++;
            }

            // Sort work orders within each status group
            // Hold: longest hold time first
            if (! empty($statusDistribution['hold']['work_orders'])) {
                usort($statusDistribution['hold']['work_orders'], function ($a, $b) {
                    return ($b['hold_since'] ?? '') <=> ($a['hold_since'] ?? '');
                });
            }

            // Start: lowest progress first
            if (! empty($statusDistribution['start']['work_orders'])) {
                usort($statusDistribution['start']['work_orders'], function ($a, $b) {
                    return ($a['progress_percentage'] ?? 0) <=> ($b['progress_percentage'] ?? 0);
                });
            }

            // Assigned: earliest start time first
            if (! empty($statusDistribution['assigned']['work_orders'])) {
                usort($statusDistribution['assigned']['work_orders'], function ($a, $b) {
                    return ($a['start_time'] ?? '') <=> ($b['start_time'] ?? '');
                });
            }

            // Completed: most recent first
            if (! empty($statusDistribution['completed']['work_orders'])) {
                usort($statusDistribution['completed']['work_orders'], function ($a, $b) {
                    return ($b['completed_at'] ?? '') <=> ($a['completed_at'] ?? '');
                });
            }

            // Closed: most recent first
            if (! empty($statusDistribution['closed']['work_orders'])) {
                usort($statusDistribution['closed']['work_orders'], function ($a, $b) {
                    return ($b['closed_at'] ?? '') <=> ($a['closed_at'] ?? '');
                });
            }

            return [
                'status_distribution' => $statusDistribution,
                'total_work_orders' => $workOrders->count(),
                'updated_at' => now()->toDateTimeString(),
            ];
        };

        // Skip cache if requested (for manual refresh)
        if ($skipCache) {
            return $callback();
        }

        return $this->getCachedKPI('current_work_order_status', $callback, 300);
    }

    /**
     * Get production schedule adherence metrics
     * Shows on-time completion rate and at-risk work orders
     *
     * DASHBOARD MODE (Real-Time):
     * - COMPLETED TODAY: WOs scheduled to end today, categorized by timing
     * - AT-RISK: WOs currently running with approaching deadlines
     */
    public function getProductionScheduleAdherence(bool $skipCache = false): array
    {
        $callback = function () {
            $today = now()->startOfDay();
            $endOfToday = now()->endOfDay();

            // Get WOs scheduled to end TODAY
            $scheduledWOs = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
                ->whereBetween('end_time', [$today, $endOfToday])
                ->whereIn('status', ['Completed', 'Closed'])
                ->with([
                    'machine:id,name,assetId',
                    'operator.user:id,first_name,last_name',
                    'bom.purchaseOrder.partNumber:id,partnumber',
                ])
                ->get();

            // Get actual completion times from logs
            $woIds = $scheduledWOs->pluck('id');
            $completionLogs = \App\Models\WorkOrderLog::whereIn('work_order_id', $woIds)
                ->where('status', 'Completed')
                ->get(['work_order_id', 'changed_at'])
                ->keyBy('work_order_id');

            // Categorize by timing
            $onTime = [];
            $early = [];
            $late = [];
            $totalDelayMinutes = 0;
            $lateCount = 0;

            foreach ($scheduledWOs as $wo) {
                $completionLog = $completionLogs->get($wo->id);
                $actualCompletion = $completionLog ? \Carbon\Carbon::parse($completionLog->changed_at) : null;
                $scheduledEnd = \Carbon\Carbon::parse($wo->end_time);

                if (! $actualCompletion) {
                    continue; // Skip if no completion log found
                }

                $varianceMinutes = $actualCompletion->diffInMinutes($scheduledEnd, false);

                $operatorName = $wo->operator?->user
                    ? "{$wo->operator->user->first_name} {$wo->operator->user->last_name}"
                    : 'Unassigned';

                $partNumber = $wo->bom?->purchaseOrder?->partNumber?->partnumber ?? 'N/A';

                $woData = [
                    'id' => $wo->id,
                    'wo_number' => $wo->unique_id ?? 'N/A',
                    'machine_name' => $wo->machine?->name ?? 'N/A',
                    'machine_asset_id' => $wo->machine?->assetId ?? 'N/A',
                    'operator' => $operatorName,
                    'part_number' => $partNumber,
                    'scheduled_end' => $scheduledEnd->format('M d, H:i'),
                    'actual_completion' => $actualCompletion->format('M d, H:i'),
                    'variance_minutes' => $varianceMinutes,
                    'variance_display' => $this->formatVariance($varianceMinutes),
                ];

                if ($varianceMinutes > 15) {
                    // Early (completed more than 15 minutes before schedule)
                    $early[] = $woData;
                } elseif ($varianceMinutes < -15) {
                    // Late (completed more than 15 minutes after schedule)
                    $late[] = $woData;
                    $totalDelayMinutes += abs($varianceMinutes);
                    $lateCount++;
                } else {
                    // On-Time (within ±15 minutes)
                    $onTime[] = $woData;
                }
            }

            $totalScheduled = $scheduledWOs->count();
            $onTimeRate = $totalScheduled > 0
                ? round((count($onTime) / $totalScheduled) * 100, 1)
                : 0;

            $avgDelayMinutes = $lateCount > 0
                ? round($totalDelayMinutes / $lateCount, 0)
                : 0;

            // Get AT-RISK work orders (currently running, deadline approaching)
            $atRiskWOs = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
                ->where('status', 'Start')
                ->where('end_time', '>=', now())
                ->where('end_time', '<=', now()->addHours(8))
                ->with([
                    'machine:id,name,assetId',
                    'operator.user:id,first_name,last_name',
                    'bom.purchaseOrder.partNumber:id,partnumber',
                ])
                ->get();

            $highRisk = [];
            $mediumRisk = [];
            $onTrack = [];

            foreach ($atRiskWOs as $wo) {
                $scheduledEnd = \Carbon\Carbon::parse($wo->end_time);
                $hoursRemaining = now()->diffInHours($scheduledEnd, false);
                $progressPct = $wo->qty > 0
                    ? round(($wo->ok_qtys / $wo->qty) * 100, 1)
                    : 0;

                $operatorName = $wo->operator?->user
                    ? "{$wo->operator->user->first_name} {$wo->operator->user->last_name}"
                    : 'Unassigned';

                $partNumber = $wo->bom?->purchaseOrder?->partNumber?->partnumber ?? 'N/A';

                $woData = [
                    'id' => $wo->id,
                    'wo_number' => $wo->unique_id ?? 'N/A',
                    'machine_name' => $wo->machine?->name ?? 'N/A',
                    'machine_asset_id' => $wo->machine?->assetId ?? 'N/A',
                    'operator' => $operatorName,
                    'part_number' => $partNumber,
                    'scheduled_end' => $scheduledEnd->format('M d, H:i'),
                    'hours_remaining' => $hoursRemaining,
                    'progress_pct' => $progressPct,
                    'qty_target' => $wo->qty ?? 0,
                    'qty_produced' => $wo->ok_qtys ?? 0,
                ];

                // Risk calculation
                if ($hoursRemaining < 2 && $progressPct < 70) {
                    $highRisk[] = $woData;
                } elseif ($hoursRemaining < 4 && $progressPct < 80) {
                    $mediumRisk[] = $woData;
                } else {
                    $onTrack[] = $woData;
                }
            }

            return [
                'summary' => [
                    'scheduled_today' => $totalScheduled,
                    'on_time_count' => count($onTime),
                    'early_count' => count($early),
                    'late_count' => count($late),
                    'on_time_rate' => $onTimeRate,
                    'avg_delay_minutes' => $avgDelayMinutes,
                ],
                'completed' => [
                    'on_time' => $onTime,
                    'early' => $early,
                    'late' => $late,
                ],
                'at_risk' => [
                    'high_risk' => $highRisk,
                    'medium_risk' => $mediumRisk,
                    'on_track' => $onTrack,
                ],
                'updated_at' => now()->toDateTimeString(),
            ];
        };

        // Skip cache if requested (for manual refresh)
        if ($skipCache) {
            return $callback();
        }

        return $this->getCachedKPI('production_schedule_adherence', $callback, 300);
    }

    /**
     * Format variance in minutes to human-readable string
     */
    private function formatVariance(int $minutes): string
    {
        if ($minutes == 0) {
            return 'On Time';
        }

        $absMinutes = abs($minutes);
        $hours = floor($absMinutes / 60);
        $mins = $absMinutes % 60;

        if ($minutes > 0) {
            // Early
            if ($hours > 0) {
                return sprintf('Early by %dh %dm', $hours, $mins);
            }

            return sprintf('Early by %dm', $mins);
        } else {
            // Late
            if ($hours > 0) {
                return sprintf('Late by %dh %dm', $hours, $mins);
            }

            return sprintf('Late by %dm', $mins);
        }
    }

    /**
     * Get all real-time KPIs (implements abstract method)
     */
    public function getKPIs(array $options = []): array
    {
        return [
            'machine_status' => $this->getCurrentMachineStatus(),
            'work_order_status' => $this->getCurrentWorkOrderStatus(),
            'production_schedule_adherence' => $this->getProductionScheduleAdherence(),
            // Future: Add more Tier 1 KPIs here
            // 'current_throughput' => $this->getCurrentThroughputRate(),
            // 'current_quality' => $this->getCurrentQualityRate(),
        ];
    }
}
