<?php

namespace App\Services\KPI;

use App\Models\Factory;
use App\Models\KPI\MachineDaily;
use App\Models\KPI\MachineStatusDaily;
use App\Models\Machine;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use Carbon\Carbon;

class OperationalKPIService extends BaseKPIService
{
    public function __construct(Factory $factory)
    {
        parent::__construct($factory, 'tier_2');
    }

    /**
     * Get machine status analytics with historical data and comparisons
     * Returns historical breakdown of machine status distribution (Running/Setup/Hold/Scheduled/Idle)
     */
    public function getMachineStatusAnalytics(array $options): array
    {
        $period = $options['time_period'] ?? 'yesterday';
        $enableComparison = $options['enable_comparison'] ?? false;
        $comparisonType = $options['comparison_type'] ?? 'previous_period';

        $dateFrom = isset($options['date_from']) ? Carbon::parse($options['date_from']) : null;
        $dateTo = isset($options['date_to']) ? Carbon::parse($options['date_to']) : null;

        [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

        $cacheKey = "machine_status_analytics_v2_{$period}_".md5(json_encode($options));
        $cacheTTL = $this->getCacheTTL($period);

        return $this->getCachedKPI($cacheKey, function () use ($startDate, $endDate, $enableComparison, $comparisonType, $options) {
            // Fetch primary period data
            $primaryData = $this->fetchMachineStatusDistribution($startDate, $endDate);

            $primarySnapshot = $this->calculateMachineStatusForDate($endDate->copy(), true);

            $result = [
                'primary_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'label' => $this->getPeriodLabel($options['time_period'] ?? 'yesterday', $startDate, $endDate),
                    'daily_breakdown' => $primaryData['daily'],
                    'summary' => $primaryData['summary'],
                    'status_snapshot' => $primarySnapshot['status_groups'] ?? [],
                    'snapshot_total_machines' => $primarySnapshot['total_machines'] ?? 0,
                    'snapshot_date' => $endDate->toDateString(),
                ],
            ];

            // Add comparison if enabled
            if ($enableComparison) {
                [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);

                $comparisonData = $this->fetchMachineStatusDistribution($compStart, $compEnd);
                $comparisonSnapshot = $this->calculateMachineStatusForDate($compEnd->copy(), true);

                $result['comparison_period'] = [
                    'start_date' => $compStart->toDateString(),
                    'end_date' => $compEnd->toDateString(),
                    'label' => $this->getPeriodLabel($comparisonType, $compStart, $compEnd),
                    'daily_breakdown' => $comparisonData['daily'],
                    'summary' => $comparisonData['summary'],
                    'status_snapshot' => $comparisonSnapshot['status_groups'] ?? [],
                    'snapshot_total_machines' => $comparisonSnapshot['total_machines'] ?? 0,
                    'snapshot_date' => $compEnd->toDateString(),
                ];

                $result['comparison_analysis'] = $this->calculateStatusDistributionComparison(
                    $primaryData['summary'],
                    $comparisonData['summary']
                );
            }

            return $result;
        }, $cacheTTL);
    }

    /**
     * Fetch machine status distribution history
     * Returns daily breakdown of machines by status: Running, Setup, Hold, Scheduled, Idle
     */
    protected function fetchMachineStatusDistribution(Carbon $startDate, Carbon $endDate): array
    {
        $records = MachineStatusDaily::where('factory_id', $this->factory->id)
            ->whereBetween('summary_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn ($record) => Carbon::parse($record->summary_date)->toDateString());

        $dailyBreakdown = [];
        $current = $startDate->copy();

        $totalRunning = 0;
        $totalSetup = 0;
        $totalHold = 0;
        $totalScheduled = 0;
        $totalIdle = 0;
        $totalMachinesSum = 0;

        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();

            if ($records->has($dateStr)) {
                $record = $records->get($dateStr);
                $dayData = [
                    'running' => (int) $record->running_count,
                    'setup' => (int) $record->setup_count,
                    'hold' => (int) $record->hold_count,
                    'scheduled' => (int) $record->scheduled_count,
                    'idle' => (int) $record->idle_count,
                    'total_machines' => (int) $record->total_machines,
                ];
            } else {
                $calculated = $this->calculateMachineStatusForDate($current);
                $dayData = [
                    'running' => $calculated['running'],
                    'setup' => $calculated['setup'],
                    'hold' => $calculated['hold'],
                    'scheduled' => $calculated['scheduled'],
                    'idle' => $calculated['idle'],
                    'total_machines' => $calculated['total_machines'],
                ];
            }

            $dayRecord = array_merge(['date' => $dateStr], $dayData);
            $dailyBreakdown[] = $dayRecord;

            $totalRunning += $dayData['running'];
            $totalSetup += $dayData['setup'];
            $totalHold += $dayData['hold'];
            $totalScheduled += $dayData['scheduled'];
            $totalIdle += $dayData['idle'];
            $totalMachinesSum += $dayData['total_machines'];

            $current->addDay();
        }

        $daysCount = count($dailyBreakdown);
        $averageDenominator = $totalMachinesSum > 0 ? $totalMachinesSum : 0;

        $summary = [
            'avg_running' => $daysCount > 0 ? round($totalRunning / $daysCount, 1) : 0,
            'avg_setup' => $daysCount > 0 ? round($totalSetup / $daysCount, 1) : 0,
            'avg_hold' => $daysCount > 0 ? round($totalHold / $daysCount, 1) : 0,
            'avg_scheduled' => $daysCount > 0 ? round($totalScheduled / $daysCount, 1) : 0,
            'avg_idle' => $daysCount > 0 ? round($totalIdle / $daysCount, 1) : 0,
            'total_machines' => $daysCount > 0 ? (int) round($totalMachinesSum / $daysCount) : 0,
            'days_analyzed' => $daysCount,
            'avg_running_pct' => $averageDenominator > 0
                ? round(($totalRunning / $averageDenominator) * 100, 1)
                : 0,
            'avg_setup_pct' => $averageDenominator > 0
                ? round(($totalSetup / $averageDenominator) * 100, 1)
                : 0,
            'avg_hold_pct' => $averageDenominator > 0
                ? round(($totalHold / $averageDenominator) * 100, 1)
                : 0,
            'avg_scheduled_pct' => $averageDenominator > 0
                ? round(($totalScheduled / $averageDenominator) * 100, 1)
                : 0,
            'avg_idle_pct' => $averageDenominator > 0
                ? round(($totalIdle / $averageDenominator) * 100, 1)
                : 0,
        ];

        return [
            'daily' => $dailyBreakdown,
            'summary' => $summary,
        ];
    }

    protected function calculateMachineStatusForDate(Carbon $date, bool $returnDetails = false): array
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $machines = Machine::where('factory_id', $this->factory->id)
            ->get(['id', 'name', 'assetId']);
        $totalMachines = $machines->count();

        $logGroups = WorkOrderLog::whereHas('workOrder', function ($query) {
                $query->where('factory_id', $this->factory->id);
            })
            ->where('changed_at', '<=', $dayEnd)
            ->with(['workOrder' => function ($query) {
                $query->select(
                    'id',
                    'machine_id',
                    'status',
                    'start_time',
                    'end_time',
                    'updated_at',
                    'created_at',
                    'unique_id'
                );
            }])
            ->orderBy('changed_at', 'desc')
            ->get()
            ->groupBy(fn ($log) => $log->workOrder?->machine_id);

        $latestOrders = WorkOrder::where('factory_id', $this->factory->id)
            ->where(function ($query) use ($dayEnd) {
                $query->whereNull('updated_at')
                    ->orWhere('updated_at', '<=', $dayEnd);
            })
            ->orderByRaw('COALESCE(updated_at, created_at) DESC')
            ->select(
                'id',
                'machine_id',
                'status',
                'start_time',
                'end_time',
                'updated_at',
                'created_at',
                'unique_id'
            )
            ->get()
            ->groupBy('machine_id');

        $counts = [
            'hold' => 0,
            'setup' => 0,
            'running' => 0,
            'scheduled' => 0,
            'idle' => 0,
        ];

        $statusGroups = [];

        if ($returnDetails) {
            $statusGroups = [
                'running' => ['count' => 0, 'machines' => []],
                'hold' => ['count' => 0, 'machines' => []],
                'setup' => ['count' => 0, 'machines' => []],
                'scheduled' => ['count' => 0, 'machines' => []],
                'idle' => ['count' => 0, 'machines' => []],
            ];
        }

        foreach ($machines as $machine) {
            $machineId = $machine->id;
            $status = null;
            $startTime = null;

            if ($logGroups->has($machineId)) {
                $log = $logGroups[$machineId]->first();
                if ($log && $log->workOrder) {
                    $status = $log->status;
                    $startTime = $log->workOrder->start_time
                        ? Carbon::parse($log->workOrder->start_time)
                        : null;
                }
            } elseif ($latestOrders->has($machineId)) {
                $wo = $latestOrders[$machineId]->first();
                $status = $wo->status;
                $startTime = $wo->start_time
                    ? Carbon::parse($wo->start_time)
                    : null;
            }

            $bucket = 'idle';

            $machineWorkOrder = $latestOrders->has($machineId)
                ? $latestOrders[$machineId]->first()
                : null;

            if ($logGroups->has($machineId)) {
                $log = $logGroups[$machineId]->first();
                if ($log && $log->workOrder) {
                    $machineWorkOrder = $log->workOrder;
                }
            }

            switch ($status) {
                case 'Hold':
                    $bucket = 'hold';
                    break;
                case 'Setup':
                    $bucket = 'setup';
                    break;
                case 'Start':
                    $bucket = 'running';
                    break;
                case 'Assigned':
                    $bucket = ($startTime && $startTime->isBetween($dayStart, $dayEnd))
                        ? 'scheduled'
                        : 'idle';
                    break;
            }

            $counts[$bucket]++;

            if ($returnDetails) {
                $statusGroups[$bucket]['machines'][] = [
                    'id' => $machine->id,
                    'name' => $machine->name,
                    'asset_id' => $machine->assetId ?? null,
                    'status' => $bucket,
                    'wo_number' => $machineWorkOrder?->unique_id ?? null,
                    'wo_id' => $machineWorkOrder?->id,
                    'start_time' => $machineWorkOrder?->start_time?->toDateTimeString(),
                    'end_time' => $machineWorkOrder?->end_time?->toDateTimeString(),
                ];
            }
        }

        if ($returnDetails) {
            foreach ($statusGroups as $statusKey => &$group) {
                $group['count'] = $counts[$statusKey];
            }

            return [
                'status_groups' => $statusGroups,
                'total_machines' => $totalMachines,
            ];
        }

        return [
            'running' => $counts['running'],
            'setup' => $counts['setup'],
            'hold' => $counts['hold'],
            'scheduled' => $counts['scheduled'],
            'idle' => $counts['idle'],
            'total_machines' => $totalMachines,
        ];
    }

    /**
     * Calculate comparison metrics for machine status distribution
     */
    protected function calculateStatusDistributionComparison(array $current, array $previous): array
    {
        return [
            'running' => [
                'current' => $current['avg_running'] ?? 0,
                'previous' => $previous['avg_running'] ?? 0,
                'difference' => round(($current['avg_running'] ?? 0) - ($previous['avg_running'] ?? 0), 1),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_running'] ?? 0,
                    $previous['avg_running'] ?? 0
                ),
                'trend' => ($current['avg_running'] ?? 0) > ($previous['avg_running'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_running'] ?? 0) > ($previous['avg_running'] ?? 0) ? 'improved' : 'declined',
            ],
            'setup' => [
                'current' => $current['avg_setup'] ?? 0,
                'previous' => $previous['avg_setup'] ?? 0,
                'difference' => round(($current['avg_setup'] ?? 0) - ($previous['avg_setup'] ?? 0), 1),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_setup'] ?? 0,
                    $previous['avg_setup'] ?? 0
                ),
                'trend' => ($current['avg_setup'] ?? 0) > ($previous['avg_setup'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_setup'] ?? 0) < ($previous['avg_setup'] ?? 0) ? 'improved' : 'declined',
            ],
            'hold' => [
                'current' => $current['avg_hold'] ?? 0,
                'previous' => $previous['avg_hold'] ?? 0,
                'difference' => round(($current['avg_hold'] ?? 0) - ($previous['avg_hold'] ?? 0), 1),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_hold'] ?? 0,
                    $previous['avg_hold'] ?? 0
                ),
                'trend' => ($current['avg_hold'] ?? 0) > ($previous['avg_hold'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_hold'] ?? 0) < ($previous['avg_hold'] ?? 0) ? 'improved' : 'declined',
            ],
            'scheduled' => [
                'current' => $current['avg_scheduled'] ?? 0,
                'previous' => $previous['avg_scheduled'] ?? 0,
                'difference' => round(($current['avg_scheduled'] ?? 0) - ($previous['avg_scheduled'] ?? 0), 1),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_scheduled'] ?? 0,
                    $previous['avg_scheduled'] ?? 0
                ),
                'trend' => ($current['avg_scheduled'] ?? 0) > ($previous['avg_scheduled'] ?? 0) ? 'up' : 'down',
                'status' => 'neutral',
            ],
            'idle' => [
                'current' => $current['avg_idle'] ?? 0,
                'previous' => $previous['avg_idle'] ?? 0,
                'difference' => round(($current['avg_idle'] ?? 0) - ($previous['avg_idle'] ?? 0), 1),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_idle'] ?? 0,
                    $previous['avg_idle'] ?? 0
                ),
                'trend' => ($current['avg_idle'] ?? 0) > ($previous['avg_idle'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_idle'] ?? 0) < ($previous['avg_idle'] ?? 0) ? 'improved' : 'declined',
            ],
        ];
    }

    /**
     * Calculate percentage change between two values
     */
    protected function calculatePercentageChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get human-readable label for period
     */
    protected function getPeriodLabel(string $period, Carbon $start, Carbon $end): string
    {
        return match ($period) {
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            '7d' => 'Last 7 Days',
            '14d' => 'Last 14 Days',
            '30d' => 'Last 30 Days',
            '60d' => 'Last 60 Days',
            '90d' => 'Last 90 Days',
            'this_quarter' => 'This Quarter',
            'this_year' => 'This Year',
            'previous_period' => 'Previous Period',
            'previous_week' => 'Previous Week',
            'previous_month' => 'Previous Month',
            'previous_quarter' => 'Previous Quarter',
            'previous_year' => 'Previous Year',
            'custom' => $start->format('M d, Y').' - '.$end->format('M d, Y'),
            default => $start->format('M d').' - '.$end->format('M d, Y'),
        };
    }

    /**
     * Get production schedule adherence analytics with historical data
     */
    public function getProductionScheduleAdherenceAnalytics(array $options): array
    {
        $period = $options['time_period'] ?? 'this_month';
        $enableComparison = $options['enable_comparison'] ?? false;
        $comparisonType = $options['comparison_type'] ?? 'previous_period';
        $dateFrom = isset($options['date_from']) ? Carbon::parse($options['date_from']) : null;
        $dateTo = isset($options['date_to']) ? Carbon::parse($options['date_to']) : null;

        [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

        // Fetch primary period data
        $primaryData = $this->fetchProductionScheduleAdherenceData($startDate, $endDate);

        $result = [
            'primary_period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'label' => $this->getPeriodLabel($period, $startDate, $endDate),
                'summary' => $primaryData['summary'],
                'scheduled_today' => $primaryData['scheduled_today'],
                'other_completions' => $primaryData['other_completions'],
                'at_risk' => $primaryData['at_risk'],
            ],
            'summary' => $primaryData['summary'],
            'scheduled_today' => $primaryData['scheduled_today'],
            'other_completions' => $primaryData['other_completions'],
            'at_risk' => $primaryData['at_risk'],
            'updated_at' => now()->toDateTimeString(),
            'period_label' => $this->getPeriodLabel($period, $startDate, $endDate),
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ];

        // Add comparison if enabled
        if ($enableComparison) {
            [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);

            $comparisonData = $this->fetchProductionScheduleAdherenceData($compStart, $compEnd);

            $result['comparison_period'] = [
                'start_date' => $compStart->toDateString(),
                'end_date' => $compEnd->toDateString(),
                'label' => $this->getPeriodLabel($comparisonType, $compStart, $compEnd),
                'summary' => $comparisonData['summary'],
                'scheduled_today' => $comparisonData['scheduled_today'],
                'other_completions' => $comparisonData['other_completions'],
                'at_risk' => $comparisonData['at_risk'],
            ];

            $result['comparison_analysis'] = $this->calculateScheduleAdherenceComparison(
                $primaryData['summary'],
                $comparisonData['summary']
            );
        }

        return $result;
    }

    /**
     * Fetch production schedule adherence data for a specific date range
     */
    protected function fetchProductionScheduleAdherenceData(Carbon $startDate, Carbon $endDate): array
    {
        // For analytics mode, fetch all work orders completed in the selected period
        $completedWorkOrders = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
            ->whereIn('status', ['Completed', 'Closed'])
            ->whereHas('workOrderLogs', function ($query) use ($startDate, $endDate) {
                $query->where('status', 'Completed')
                    ->whereBetween('changed_at', [$startDate, $endDate]);
            })
            ->with([
                'machine:id,name,assetId',
                'operator.user:id,first_name,last_name',
                'bom.purchaseOrder.partNumber:id,partnumber',
            ])
            ->get();

        // Initialize counters
        $totalScheduled = 0;
        $onTimeCount = 0;
        $earlyCount = 0;
        $lateCount = 0;
        $totalDelayMinutes = 0;
        $lateWorkOrders = 0;

        $onTime = [];
        $early = [];
        $late = [];

        foreach ($completedWorkOrders as $wo) {
            $completionLog = $wo->workOrderLogs()
                ->where('status', 'Completed')
                ->whereBetween('changed_at', [$startDate, $endDate])
                ->orderBy('changed_at', 'desc')
                ->first();

            if (! $completionLog || ! $wo->end_time) {
                continue;
            }

            $scheduledEnd = Carbon::parse($wo->end_time);
            $actualCompletion = Carbon::parse($completionLog->changed_at);
            $varianceMinutes = $actualCompletion->diffInMinutes($scheduledEnd, false);

            $operatorName = $wo->operator?->user
                ? "{$wo->operator->user->first_name} {$wo->operator->user->last_name}"
                : 'Unassigned';

            $partNumber = $wo->bom?->purchaseOrder?->partNumber?->partnumber ?? 'N/A';

            $woData = [
                'wo_number' => $wo->unique_id ?? 'N/A',
                'machine_name' => $wo->machine?->name ?? 'N/A',
                'machine_asset_id' => $wo->machine?->assetId ?? 'N/A',
                'part_number' => $partNumber,
                'operator' => $operatorName,
                'scheduled_end' => $scheduledEnd->format('M d, H:i'),
                'actual_completion' => $actualCompletion->format('M d, H:i'),
                'variance_minutes' => $varianceMinutes,
                'variance_display' => ($varianceMinutes >= 0 ? '+' : '').($varianceMinutes).' min',
            ];

            $totalScheduled++;

            // Categorize based on ±15 minute threshold
            if (abs($varianceMinutes) <= 15) {
                $onTimeCount++;
                $onTime[] = $woData;
            } elseif ($varianceMinutes < -15) {
                $earlyCount++;
                $early[] = $woData;
            } else {
                $lateCount++;
                $late[] = $woData;
                $totalDelayMinutes += $varianceMinutes;
                $lateWorkOrders++;
            }
        }

        $onTimeRate = $totalScheduled > 0 ? round(($onTimeCount / $totalScheduled) * 100, 2) : 0;
        $avgDelayMinutes = $lateWorkOrders > 0 ? round($totalDelayMinutes / $lateWorkOrders, 0) : 0;

        return [
            'summary' => [
                'scheduled_today' => $totalScheduled,
                'on_time_count' => $onTimeCount,
                'early_count' => $earlyCount,
                'late_count' => $lateCount,
                'on_time_rate' => $onTimeRate,
                'avg_delay_minutes' => $avgDelayMinutes,
                'early_from_future_count' => 0, // Not applicable in analytics mode
                'late_from_past_count' => 0, // Not applicable in analytics mode
                'total_completions_today' => $totalScheduled,
            ],
            'scheduled_today' => [
                'on_time' => $onTime,
                'early' => $early,
                'late' => $late,
            ],
            'other_completions' => [
                'early_from_future' => [],
                'late_from_past' => [],
            ],
            'at_risk' => [
                'high_risk' => [],
                'medium_risk' => [],
                'on_track' => [],
            ],
        ];
    }

    /**
     * Calculate comparison metrics for schedule adherence
     */
    protected function calculateScheduleAdherenceComparison(array $current, array $previous): array
    {
        return [
            'total_completions' => [
                'current' => $current['total_completions_today'] ?? 0,
                'previous' => $previous['total_completions_today'] ?? 0,
                'difference' => ($current['total_completions_today'] ?? 0) - ($previous['total_completions_today'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_completions_today'] ?? 0,
                    $previous['total_completions_today'] ?? 0
                ),
                'trend' => ($current['total_completions_today'] ?? 0) > ($previous['total_completions_today'] ?? 0) ? 'up' : 'down',
            ],
            'on_time_rate' => [
                'current' => $current['on_time_rate'] ?? 0,
                'previous' => $previous['on_time_rate'] ?? 0,
                'difference' => round(($current['on_time_rate'] ?? 0) - ($previous['on_time_rate'] ?? 0), 2),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['on_time_rate'] ?? 0,
                    $previous['on_time_rate'] ?? 0
                ),
                'trend' => ($current['on_time_rate'] ?? 0) > ($previous['on_time_rate'] ?? 0) ? 'up' : 'down',
                'status' => ($current['on_time_rate'] ?? 0) > ($previous['on_time_rate'] ?? 0) ? 'improved' : 'declined',
            ],
            'on_time_count' => [
                'current' => $current['on_time_count'] ?? 0,
                'previous' => $previous['on_time_count'] ?? 0,
                'difference' => ($current['on_time_count'] ?? 0) - ($previous['on_time_count'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['on_time_count'] ?? 0,
                    $previous['on_time_count'] ?? 0
                ),
                'trend' => ($current['on_time_count'] ?? 0) > ($previous['on_time_count'] ?? 0) ? 'up' : 'down',
                'status' => ($current['on_time_count'] ?? 0) > ($previous['on_time_count'] ?? 0) ? 'improved' : 'declined',
            ],
            'early_count' => [
                'current' => $current['early_count'] ?? 0,
                'previous' => $previous['early_count'] ?? 0,
                'difference' => ($current['early_count'] ?? 0) - ($previous['early_count'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['early_count'] ?? 0,
                    $previous['early_count'] ?? 0
                ),
                'trend' => ($current['early_count'] ?? 0) > ($previous['early_count'] ?? 0) ? 'up' : 'down',
            ],
            'late_count' => [
                'current' => $current['late_count'] ?? 0,
                'previous' => $previous['late_count'] ?? 0,
                'difference' => ($current['late_count'] ?? 0) - ($previous['late_count'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['late_count'] ?? 0,
                    $previous['late_count'] ?? 0
                ),
                'trend' => ($current['late_count'] ?? 0) > ($previous['late_count'] ?? 0) ? 'up' : 'down',
                'status' => ($current['late_count'] ?? 0) < ($previous['late_count'] ?? 0) ? 'improved' : 'declined',
            ],
            'avg_delay_minutes' => [
                'current' => $current['avg_delay_minutes'] ?? 0,
                'previous' => $previous['avg_delay_minutes'] ?? 0,
                'difference' => ($current['avg_delay_minutes'] ?? 0) - ($previous['avg_delay_minutes'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_delay_minutes'] ?? 0,
                    $previous['avg_delay_minutes'] ?? 0
                ),
                'trend' => ($current['avg_delay_minutes'] ?? 0) > ($previous['avg_delay_minutes'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_delay_minutes'] ?? 0) < ($previous['avg_delay_minutes'] ?? 0) ? 'improved' : 'declined',
            ],
        ];
    }

    /**
     * Get work order status analytics with historical data and comparisons
     * Returns historical breakdown of work order status distribution
     */
    public function getWorkOrderStatusAnalytics(array $options): array
    {
        $period = $options['time_period'] ?? 'yesterday';
        $enableComparison = $options['enable_comparison'] ?? false;
        $comparisonType = $options['comparison_type'] ?? 'previous_period';

        $dateFrom = isset($options['date_from']) ? Carbon::parse($options['date_from']) : null;
        $dateTo = isset($options['date_to']) ? Carbon::parse($options['date_to']) : null;

        [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

        $cacheKey = "work_order_status_analytics_{$period}_".md5(json_encode($options));
        $cacheTTL = $this->getCacheTTL($period);

        return $this->getCachedKPI($cacheKey, function () use ($startDate, $endDate, $enableComparison, $comparisonType, $period) {
            // Fetch primary period data
            $primaryData = $this->fetchWorkOrderStatusDistribution($startDate, $endDate);

            $result = [
                'status_distribution' => $primaryData['status_distribution'],
                'total_work_orders' => $primaryData['total_work_orders'],
                'updated_at' => now()->toDateTimeString(),
                'period_label' => $this->getPeriodLabel($period, $startDate, $endDate),
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
                'primary_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'label' => $this->getPeriodLabel($period, $startDate, $endDate),
                    'summary' => $primaryData['summary'],
                ],
            ];

            // Add comparison if enabled
            if ($enableComparison) {
                [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);

                $comparisonData = $this->fetchWorkOrderStatusDistribution($compStart, $compEnd);

                $result['comparison_period'] = [
                    'start_date' => $compStart->toDateString(),
                    'end_date' => $compEnd->toDateString(),
                    'label' => $this->getPeriodLabel($comparisonType, $compStart, $compEnd),
                    'summary' => $comparisonData['summary'],
                ];

                $result['comparison_analysis'] = $this->calculateWorkOrderStatusComparison(
                    $primaryData['summary'],
                    $comparisonData['summary']
                );
            }

            return $result;
        }, $cacheTTL);
    }

    /**
     * Fetch work order status distribution for a specific date range
     * Returns work orders grouped by status for the analytics period
     */
    protected function fetchWorkOrderStatusDistribution(Carbon $startDate, Carbon $endDate): array
    {
        // Get all work orders that had activity during this period
        // We'll check work_order_logs to see status changes during the period
        $workOrders = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
            ->whereHas('workOrderLogs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('changed_at', [$startDate, $endDate]);
            })
            ->with([
                'machine:id,name,assetId',
                'operator.user:id,first_name,last_name',
                'bom.purchaseOrder.partNumber:id,partnumber',
                'workOrderLogs' => function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('changed_at', [$startDate, $endDate])
                        ->with('holdReason:id,code,description')
                        ->orderBy('changed_at', 'desc');
                },
            ])
            ->get();

        $statusDistribution = [
            'assigned' => ['count' => 0, 'work_orders' => []],
            'setup' => ['count' => 0, 'work_orders' => []],
            'start' => ['count' => 0, 'work_orders' => []],
            'hold' => ['count' => 0, 'work_orders' => []],
            'completed' => ['count' => 0, 'work_orders' => []],
            'closed' => ['count' => 0, 'work_orders' => []],
        ];

        // Track which statuses each work order had during the period
        $workOrderStatusCounts = [
            'assigned' => 0,
            'setup' => 0,
            'start' => 0,
            'hold' => 0,
            'completed' => 0,
            'closed' => 0,
        ];

        foreach ($workOrders as $wo) {
            $logs = $wo->workOrderLogs;

            if ($logs->isEmpty()) {
                continue;
            }

            // Get all unique statuses this work order had during the period
            $statuses = $logs->pluck('status')->unique()->map(fn ($s) => strtolower($s));

            foreach ($statuses as $status) {
                if (! isset($statusDistribution[$status])) {
                    continue;
                }

                // Find the most recent log for this status
                $statusLog = $logs->firstWhere('status', ucfirst($status));

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
                    'status_changed_at' => $statusLog?->changed_at?->toDateTimeString(),
                ];

                // Add status-specific fields
                if ($status === 'hold') {
                    $woData['hold_reason'] = $statusLog?->holdReason?->description ?? 'No reason specified';
                    $woData['hold_since'] = $statusLog?->changed_at?->toDateTimeString();
                    $woData['hold_duration'] = $statusLog?->changed_at
                        ? now()->diffForHumans($statusLog->changed_at, true)
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
                } elseif ($status === 'setup') {
                    $woData['setup_since'] = $statusLog?->changed_at?->toDateTimeString();
                    $woData['setup_duration'] = $statusLog?->changed_at
                        ? now()->diffForHumans($statusLog->changed_at, true)
                        : 'Unknown';
                    $woData['scheduled_start'] = $wo->start_time
                        ? $wo->start_time->format('M d, H:i')
                        : 'Not scheduled';
                } elseif ($status === 'completed') {
                    $woData['completed_at'] = $statusLog?->changed_at?->toDateTimeString() ?? $wo->updated_at?->toDateTimeString();
                    $woData['completion_rate'] = $wo->qty > 0
                        ? round(($wo->ok_qtys / $wo->qty) * 100, 1)
                        : 0;
                } elseif ($status === 'closed') {
                    $woData['closed_at'] = $statusLog?->changed_at?->toDateTimeString() ?? $wo->updated_at?->toDateTimeString();
                    $woData['completion_rate'] = $wo->qty > 0
                        ? round(($wo->ok_qtys / $wo->qty) * 100, 1)
                        : 0;
                }

                $statusDistribution[$status]['work_orders'][] = $woData;
                $workOrderStatusCounts[$status]++;
            }
        }

        // Update counts
        foreach ($statusDistribution as $status => &$data) {
            $data['count'] = $workOrderStatusCounts[$status];
        }

        // Sort work orders within each status group
        foreach ($statusDistribution as $status => &$data) {
            if (empty($data['work_orders'])) {
                continue;
            }

            usort($data['work_orders'], function ($a, $b) use ($status) {
                if ($status === 'hold') {
                    return ($b['hold_since'] ?? '') <=> ($a['hold_since'] ?? '');
                } elseif ($status === 'start') {
                    return ($a['progress_percentage'] ?? 0) <=> ($b['progress_percentage'] ?? 0);
                } elseif ($status === 'assigned') {
                    return ($a['start_time'] ?? '') <=> ($b['start_time'] ?? '');
                } elseif ($status === 'completed') {
                    return ($b['completed_at'] ?? '') <=> ($a['completed_at'] ?? '');
                } elseif ($status === 'closed') {
                    return ($b['closed_at'] ?? '') <=> ($a['closed_at'] ?? '');
                }

                return 0;
            });
        }

        $totalWorkOrders = array_sum($workOrderStatusCounts);

        return [
            'status_distribution' => $statusDistribution,
            'total_work_orders' => $totalWorkOrders,
            'summary' => [
                'total' => $totalWorkOrders,
                'assigned_count' => $workOrderStatusCounts['assigned'],
                'setup_count' => $workOrderStatusCounts['setup'],
                'start_count' => $workOrderStatusCounts['start'],
                'hold_count' => $workOrderStatusCounts['hold'],
                'completed_count' => $workOrderStatusCounts['completed'],
                'closed_count' => $workOrderStatusCounts['closed'],
                'assigned_pct' => $totalWorkOrders > 0 ? round(($workOrderStatusCounts['assigned'] / $totalWorkOrders) * 100, 1) : 0,
                'setup_pct' => $totalWorkOrders > 0 ? round(($workOrderStatusCounts['setup'] / $totalWorkOrders) * 100, 1) : 0,
                'start_pct' => $totalWorkOrders > 0 ? round(($workOrderStatusCounts['start'] / $totalWorkOrders) * 100, 1) : 0,
                'hold_pct' => $totalWorkOrders > 0 ? round(($workOrderStatusCounts['hold'] / $totalWorkOrders) * 100, 1) : 0,
                'completed_pct' => $totalWorkOrders > 0 ? round(($workOrderStatusCounts['completed'] / $totalWorkOrders) * 100, 1) : 0,
                'closed_pct' => $totalWorkOrders > 0 ? round(($workOrderStatusCounts['closed'] / $totalWorkOrders) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Calculate comparison metrics for work order status distribution
     */
    protected function calculateWorkOrderStatusComparison(array $current, array $previous): array
    {
        return [
            'total' => [
                'current' => $current['total'] ?? 0,
                'previous' => $previous['total'] ?? 0,
                'difference' => ($current['total'] ?? 0) - ($previous['total'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total'] ?? 0,
                    $previous['total'] ?? 0
                ),
                'trend' => ($current['total'] ?? 0) > ($previous['total'] ?? 0) ? 'up' : 'down',
            ],
            'assigned' => [
                'current' => $current['assigned_count'] ?? 0,
                'previous' => $previous['assigned_count'] ?? 0,
                'difference' => ($current['assigned_count'] ?? 0) - ($previous['assigned_count'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['assigned_count'] ?? 0,
                    $previous['assigned_count'] ?? 0
                ),
                'trend' => ($current['assigned_count'] ?? 0) > ($previous['assigned_count'] ?? 0) ? 'up' : 'down',
                'status' => 'neutral', // Assigned is neither good nor bad
            ],
            'setup' => [
                'current' => $current['setup_count'] ?? 0,
                'previous' => $previous['setup_count'] ?? 0,
                'difference' => ($current['setup_count'] ?? 0) - ($previous['setup_count'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['setup_count'] ?? 0,
                    $previous['setup_count'] ?? 0
                ),
                'trend' => ($current['setup_count'] ?? 0) > ($previous['setup_count'] ?? 0) ? 'up' : 'down',
                'status' => 'neutral', // Setup is neither good nor bad
            ],
            'start' => [
                'current' => $current['start_count'] ?? 0,
                'previous' => $previous['start_count'] ?? 0,
                'difference' => ($current['start_count'] ?? 0) - ($previous['start_count'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['start_count'] ?? 0,
                    $previous['start_count'] ?? 0
                ),
                'trend' => ($current['start_count'] ?? 0) > ($previous['start_count'] ?? 0) ? 'up' : 'down',
                'status' => ($current['start_count'] ?? 0) > ($previous['start_count'] ?? 0) ? 'improved' : 'declined',
            ],
            'hold' => [
                'current' => $current['hold_count'] ?? 0,
                'previous' => $previous['hold_count'] ?? 0,
                'difference' => ($current['hold_count'] ?? 0) - ($previous['hold_count'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['hold_count'] ?? 0,
                    $previous['hold_count'] ?? 0
                ),
                'trend' => ($current['hold_count'] ?? 0) > ($previous['hold_count'] ?? 0) ? 'up' : 'down',
                'status' => ($current['hold_count'] ?? 0) < ($previous['hold_count'] ?? 0) ? 'improved' : 'declined',
            ],
            'completed' => [
                'current' => $current['completed_count'] ?? 0,
                'previous' => $previous['completed_count'] ?? 0,
                'difference' => ($current['completed_count'] ?? 0) - ($previous['completed_count'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['completed_count'] ?? 0,
                    $previous['completed_count'] ?? 0
                ),
                'trend' => ($current['completed_count'] ?? 0) > ($previous['completed_count'] ?? 0) ? 'up' : 'down',
                'status' => ($current['completed_count'] ?? 0) > ($previous['completed_count'] ?? 0) ? 'improved' : 'declined',
            ],
            'closed' => [
                'current' => $current['closed_count'] ?? 0,
                'previous' => $previous['closed_count'] ?? 0,
                'difference' => ($current['closed_count'] ?? 0) - ($previous['closed_count'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['closed_count'] ?? 0,
                    $previous['closed_count'] ?? 0
                ),
                'trend' => ($current['closed_count'] ?? 0) > ($previous['closed_count'] ?? 0) ? 'up' : 'down',
                'status' => ($current['closed_count'] ?? 0) > ($previous['closed_count'] ?? 0) ? 'improved' : 'declined',
            ],
        ];
    }

    /**
     * Get machine utilization analytics with historical data from kpi_machine_daily table
     * Returns historical breakdown of machine utilization metrics
     */
    public function getMachineUtilizationAnalytics(array $options): array
    {
        $period = $options['time_period'] ?? 'last_week';
        $enableComparison = $options['enable_comparison'] ?? false;
        $comparisonType = $options['comparison_type'] ?? 'previous_period';

        $dateFrom = isset($options['date_from']) ? Carbon::parse($options['date_from']) : null;
        $dateTo = isset($options['date_to']) ? Carbon::parse($options['date_to']) : null;

        [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

        $cacheKey = "machine_utilization_analytics_{$period}_".md5(json_encode($options));
        $cacheTTL = $this->getCacheTTL($period);

        return $this->getCachedKPI($cacheKey, function () use ($startDate, $endDate, $enableComparison, $comparisonType, $period) {
            // Fetch primary period data
            $primaryData = $this->fetchMachineUtilizationData($startDate, $endDate);

            $result = [
                'primary_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'label' => $this->getPeriodLabel($period, $startDate, $endDate),
                    'daily_breakdown' => $primaryData['daily'],
                    'summary' => $primaryData['summary'],
                ],
            ];

            // Add comparison if enabled
            if ($enableComparison) {
                [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);

                $comparisonData = $this->fetchMachineUtilizationData($compStart, $compEnd);

                $result['comparison_period'] = [
                    'start_date' => $compStart->toDateString(),
                    'end_date' => $compEnd->toDateString(),
                    'label' => $this->getPeriodLabel($comparisonType, $compStart, $compEnd),
                    'daily_breakdown' => $comparisonData['daily'],
                    'summary' => $comparisonData['summary'],
                ];

                $result['comparison_analysis'] = $this->calculateMachineUtilizationComparison(
                    $primaryData['summary'],
                    $comparisonData['summary']
                );
            }

            return $result;
        }, $cacheTTL);
    }

    /**
     * Fetch machine utilization data from kpi_machine_daily table for a specific date range
     */
    protected function fetchMachineUtilizationData(Carbon $startDate, Carbon $endDate): array
    {
        // Fetch aggregated data from kpi_machine_daily table
        $dailyRecords = MachineDaily::where('factory_id', $this->factory->id)
            ->whereBetween('summary_date', [$startDate, $endDate])
            ->with('machine:id,name,assetId')
            ->orderBy('summary_date', 'asc')
            ->get();

        // Group by date for daily breakdown
        $dailyBreakdown = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();

            $dayRecords = $dailyRecords->filter(function ($record) use ($dateStr) {
                return Carbon::parse($record->summary_date)->toDateString() === $dateStr;
            });

            $machineCount = $dayRecords->count();

            $dailyBreakdown[] = [
                'date' => $dateStr,
                'avg_utilization_rate' => $machineCount > 0
                    ? round($dayRecords->avg('utilization_rate'), 2)
                    : 0,
                'avg_active_utilization_rate' => $machineCount > 0
                    ? round($dayRecords->avg('active_utilization_rate'), 2)
                    : 0,
                'uptime_hours' => round($dayRecords->sum('uptime_hours'), 2),
                'downtime_hours' => round($dayRecords->sum('downtime_hours'), 2),
                'planned_downtime_hours' => round($dayRecords->sum('planned_downtime_hours'), 2),
                'unplanned_downtime_hours' => round($dayRecords->sum('unplanned_downtime_hours'), 2),
                'units_produced' => $dayRecords->sum('units_produced'),
                'work_orders_completed' => $dayRecords->sum('work_orders_completed'),
                'machines_tracked' => $machineCount,
            ];

            $current->addDay();
        }

        // Calculate summary statistics
        $daysCount = count($dailyBreakdown);
        $totalMachines = $dailyRecords->pluck('machine_id')->unique()->count();

        $summary = [
            'avg_scheduled_utilization' => $daysCount > 0
                ? round(collect($dailyBreakdown)->avg('avg_utilization_rate'), 2)
                : 0,
            'avg_active_utilization' => $daysCount > 0
                ? round(collect($dailyBreakdown)->avg('avg_active_utilization_rate'), 2)
                : 0,
            'total_uptime_hours' => round(collect($dailyBreakdown)->sum('uptime_hours'), 2),
            'total_downtime_hours' => round(collect($dailyBreakdown)->sum('downtime_hours'), 2),
            'total_planned_downtime_hours' => round(collect($dailyBreakdown)->sum('planned_downtime_hours'), 2),
            'total_unplanned_downtime_hours' => round(collect($dailyBreakdown)->sum('unplanned_downtime_hours'), 2),
            'total_units_produced' => collect($dailyBreakdown)->sum('units_produced'),
            'total_work_orders_completed' => collect($dailyBreakdown)->sum('work_orders_completed'),
            'machines_analyzed' => $totalMachines,
            'days_analyzed' => $daysCount,
        ];

        return [
            'daily' => $dailyBreakdown,
            'summary' => $summary,
        ];
    }

    /**
     * Calculate comparison metrics for machine utilization
     */
    protected function calculateMachineUtilizationComparison(array $current, array $previous): array
    {
        return [
            'scheduled_utilization' => [
                'current' => $current['avg_scheduled_utilization'] ?? 0,
                'previous' => $previous['avg_scheduled_utilization'] ?? 0,
                'difference' => round(($current['avg_scheduled_utilization'] ?? 0) - ($previous['avg_scheduled_utilization'] ?? 0), 2),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_scheduled_utilization'] ?? 0,
                    $previous['avg_scheduled_utilization'] ?? 0
                ),
                'trend' => ($current['avg_scheduled_utilization'] ?? 0) > ($previous['avg_scheduled_utilization'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_scheduled_utilization'] ?? 0) > ($previous['avg_scheduled_utilization'] ?? 0) ? 'improved' : 'declined',
            ],
            'active_utilization' => [
                'current' => $current['avg_active_utilization'] ?? 0,
                'previous' => $previous['avg_active_utilization'] ?? 0,
                'difference' => round(($current['avg_active_utilization'] ?? 0) - ($previous['avg_active_utilization'] ?? 0), 2),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_active_utilization'] ?? 0,
                    $previous['avg_active_utilization'] ?? 0
                ),
                'trend' => ($current['avg_active_utilization'] ?? 0) > ($previous['avg_active_utilization'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_active_utilization'] ?? 0) > ($previous['avg_active_utilization'] ?? 0) ? 'improved' : 'declined',
            ],
            'uptime_hours' => [
                'current' => $current['total_uptime_hours'] ?? 0,
                'previous' => $previous['total_uptime_hours'] ?? 0,
                'difference' => round(($current['total_uptime_hours'] ?? 0) - ($previous['total_uptime_hours'] ?? 0), 2),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_uptime_hours'] ?? 0,
                    $previous['total_uptime_hours'] ?? 0
                ),
                'trend' => ($current['total_uptime_hours'] ?? 0) > ($previous['total_uptime_hours'] ?? 0) ? 'up' : 'down',
                'status' => ($current['total_uptime_hours'] ?? 0) > ($previous['total_uptime_hours'] ?? 0) ? 'improved' : 'declined',
            ],
            'downtime_hours' => [
                'current' => $current['total_downtime_hours'] ?? 0,
                'previous' => $previous['total_downtime_hours'] ?? 0,
                'difference' => round(($current['total_downtime_hours'] ?? 0) - ($previous['total_downtime_hours'] ?? 0), 2),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_downtime_hours'] ?? 0,
                    $previous['total_downtime_hours'] ?? 0
                ),
                'trend' => ($current['total_downtime_hours'] ?? 0) > ($previous['total_downtime_hours'] ?? 0) ? 'up' : 'down',
                'status' => ($current['total_downtime_hours'] ?? 0) < ($previous['total_downtime_hours'] ?? 0) ? 'improved' : 'declined',
            ],
            'units_produced' => [
                'current' => $current['total_units_produced'] ?? 0,
                'previous' => $previous['total_units_produced'] ?? 0,
                'difference' => ($current['total_units_produced'] ?? 0) - ($previous['total_units_produced'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_units_produced'] ?? 0,
                    $previous['total_units_produced'] ?? 0
                ),
                'trend' => ($current['total_units_produced'] ?? 0) > ($previous['total_units_produced'] ?? 0) ? 'up' : 'down',
                'status' => ($current['total_units_produced'] ?? 0) > ($previous['total_units_produced'] ?? 0) ? 'improved' : 'declined',
            ],
            'work_orders_completed' => [
                'current' => $current['total_work_orders_completed'] ?? 0,
                'previous' => $previous['total_work_orders_completed'] ?? 0,
                'difference' => ($current['total_work_orders_completed'] ?? 0) - ($previous['total_work_orders_completed'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_work_orders_completed'] ?? 0,
                    $previous['total_work_orders_completed'] ?? 0
                ),
                'trend' => ($current['total_work_orders_completed'] ?? 0) > ($previous['total_work_orders_completed'] ?? 0) ? 'up' : 'down',
                'status' => ($current['total_work_orders_completed'] ?? 0) > ($previous['total_work_orders_completed'] ?? 0) ? 'improved' : 'declined',
            ],
        ];
    }

    /**
     * Get setup time analytics with historical data and comparisons
     * Returns historical breakdown of setup times per machine and daily aggregations
     * Setup time = gap between 'Assigned' and first 'Start' status in work_order_logs
     */
    public function getSetupTimeAnalytics(array $options): array
    {
        $period = $options['time_period'] ?? 'yesterday';
        $enableComparison = $options['enable_comparison'] ?? false;
        $comparisonType = $options['comparison_type'] ?? 'previous_period';
        $machineFilter = $options['machine_id'] ?? null;

        $dateFrom = isset($options['date_from']) ? Carbon::parse($options['date_from']) : null;
        $dateTo = isset($options['date_to']) ? Carbon::parse($options['date_to']) : null;

        [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

        $cacheKey = "setup_time_analytics_v2_{$period}_".md5(json_encode($options));
        $cacheTTL = $this->getCacheTTL($period);

        return $this->getCachedKPI($cacheKey, function () use ($startDate, $endDate, $enableComparison, $comparisonType, $options, $machineFilter) {
            // Fetch primary period data
            $primaryData = $this->fetchSetupTimeDistribution($startDate, $endDate, $machineFilter);

            $result = [
                'primary_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'label' => $this->getPeriodLabel($options['time_period'] ?? 'yesterday', $startDate, $endDate),
                    'daily_breakdown' => $primaryData['daily'],
                    'machine_breakdown' => $primaryData['by_machine'],
                    'summary' => $primaryData['summary'],
                ],
            ];

            // Add comparison if enabled
            if ($enableComparison) {
                [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);

                $comparisonData = $this->fetchSetupTimeDistribution($compStart, $compEnd, $machineFilter);

                $result['comparison_period'] = [
                    'start_date' => $compStart->toDateString(),
                    'end_date' => $compEnd->toDateString(),
                    'label' => $this->getPeriodLabel($comparisonType, $compStart, $compEnd),
                    'daily_breakdown' => $comparisonData['daily'],
                    'machine_breakdown' => $comparisonData['by_machine'],
                    'summary' => $comparisonData['summary'],
                ];

                $result['comparison_analysis'] = $this->calculateSetupTimeComparison(
                    $primaryData['summary'],
                    $comparisonData['summary']
                );
            }

            return $result;
        }, $cacheTTL);
    }

    /**
     * Fetch setup time distribution for a date range.
     * Calculates setup time using the gap between the most recent 'Setup' status and the next 'Start'.
     * Falls back to the latest 'Assigned' status when historical data has no 'Setup' entry.
     */
    protected function fetchSetupTimeDistribution(Carbon $startDate, Carbon $endDate, ?int $machineFilter = null): array
    {
        // Collect all start logs within the analysis window.
        $startLogs = \App\Models\WorkOrderLog::where('status', 'Start')
            ->whereBetween('changed_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->orderBy('changed_at', 'asc')
            ->get(['work_order_id', 'changed_at']);

        $dailyBreakdown = [];
        $machineBreakdown = [];
        $totalSetupMinutes = 0;
        $totalSetups = 0;

        foreach ($startLogs as $startLog) {
            // Identify the most recent 'Setup' before this Start.
            $setupLog = \App\Models\WorkOrderLog::where('work_order_id', $startLog->work_order_id)
                ->where('status', 'Setup')
                ->where('changed_at', '<', $startLog->changed_at)
                ->orderBy('changed_at', 'desc')
                ->first(['work_order_id', 'changed_at', 'status']);

            if (! $setupLog) {
                // Fallback for historical data without Setup status.
                $hasAnySetup = \App\Models\WorkOrderLog::where('work_order_id', $startLog->work_order_id)
                    ->where('status', 'Setup')
                    ->where('changed_at', '<', $startLog->changed_at)
                    ->exists();

                if (! $hasAnySetup) {
                    $setupLog = \App\Models\WorkOrderLog::where('work_order_id', $startLog->work_order_id)
                        ->where('status', 'Assigned')
                        ->where('changed_at', '<', $startLog->changed_at)
                        ->orderBy('changed_at', 'desc')
                        ->first(['work_order_id', 'changed_at', 'status']);
                }
            }

            if (! $setupLog) {
                continue; // No setup information available for this start.
            }

            // Only consider setups that occurred within the requested window.
            if (! $setupLog->changed_at->between($startDate->copy()->startOfDay(), $endDate->copy()->endOfDay())) {
                continue;
            }

            // Ignore subsequent starts that occur without a new setup.
            $priorStartExists = \App\Models\WorkOrderLog::where('work_order_id', $startLog->work_order_id)
                ->where('status', 'Start')
                ->where('changed_at', '>', $setupLog->changed_at)
                ->where('changed_at', '<', $startLog->changed_at)
                ->exists();

            if ($priorStartExists) {
                continue;
            }

            // Calculate setup time in minutes.
            $setupMinutes = $setupLog->changed_at->diffInMinutes($startLog->changed_at);
            if ($setupMinutes < 0) {
                continue;
            }

            // Get work order details (for factory and machine scope).
            $workOrder = \App\Models\WorkOrder::find($startLog->work_order_id, ['id', 'machine_id', 'factory_id']);
            if (! $workOrder || $workOrder->factory_id !== $this->factory->id) {
                continue;
            }

            // Apply machine filter if specified
            if ($machineFilter && $workOrder->machine_id != $machineFilter) {
                continue;
            }

            $date = $setupLog->changed_at->toDateString();

            // Initialize daily breakdown for this date
            if (! isset($dailyBreakdown[$date])) {
                $dailyBreakdown[$date] = [
                    'date' => $date,
                    'total_setup_time' => 0,
                    'total_setups' => 0,
                    'avg_setup_time' => 0,
                    'max_setup_time' => 0,
                    'min_setup_time' => PHP_INT_MAX,
                ];
            }

            // Update daily breakdown
            $dailyBreakdown[$date]['total_setup_time'] += $setupMinutes;
            $dailyBreakdown[$date]['total_setups'] += 1;
            $dailyBreakdown[$date]['max_setup_time'] = max($dailyBreakdown[$date]['max_setup_time'], $setupMinutes);
            $dailyBreakdown[$date]['min_setup_time'] = min($dailyBreakdown[$date]['min_setup_time'], $setupMinutes);

            $totalSetupMinutes += $setupMinutes;
            $totalSetups += 1;

            // Initialize machine breakdown
            if (! isset($machineBreakdown[$workOrder->machine_id])) {
                $machine = \App\Models\Machine::find($workOrder->machine_id, ['id', 'name', 'assetId']);
                $machineBreakdown[$workOrder->machine_id] = [
                    'machine_id' => $workOrder->machine_id,
                    'machine_name' => $machine?->name ?? 'Unknown',
                    'asset_id' => $machine?->assetId ?? null,
                    'total_setup_time' => 0,
                    'total_setups' => 0,
                    'avg_setup_time' => 0,
                ];
            }

            // Update machine breakdown
            $machineBreakdown[$workOrder->machine_id]['total_setup_time'] += $setupMinutes;
            $machineBreakdown[$workOrder->machine_id]['total_setups'] += 1;
        }

        // Calculate averages and finalize daily breakdown
        foreach ($dailyBreakdown as &$day) {
            if ($day['total_setups'] > 0) {
                $day['avg_setup_time'] = round($day['total_setup_time'] / $day['total_setups'], 2);
                $day['min_setup_time'] = min($day['min_setup_time'], $day['max_setup_time']);
            } else {
                $day['min_setup_time'] = 0;
            }
            // Convert minutes to hours for readability
            $day['total_setup_time_hours'] = round($day['total_setup_time'] / 60, 2);
            $day['avg_setup_time_minutes'] = $day['avg_setup_time'];
        }

        // Sort daily breakdown by date
        ksort($dailyBreakdown);
        $dailyBreakdown = array_values($dailyBreakdown);

        // Calculate averages and finalize machine breakdown
        foreach ($machineBreakdown as &$machine) {
            if ($machine['total_setups'] > 0) {
                $machine['avg_setup_time'] = round($machine['total_setup_time'] / $machine['total_setups'], 2);
            }
            $machine['total_setup_time_hours'] = round($machine['total_setup_time'] / 60, 2);
        }

        // Sort machine breakdown by total setup time (descending)
        usort($machineBreakdown, fn ($a, $b) => $b['total_setup_time'] <=> $a['total_setup_time']);

        // Calculate summary statistics
        $daysCount = count($dailyBreakdown);
        $machinesCount = count($machineBreakdown);

        $summary = [
            'total_setup_time' => round($totalSetupMinutes / 60, 2), // Hours
            'total_setup_minutes' => $totalSetupMinutes,
            'total_setups' => $totalSetups,
            'avg_daily_setup_time' => $daysCount > 0 ? round($totalSetupMinutes / $daysCount / 60, 2) : 0, // Hours
            'avg_setup_duration' => $totalSetups > 0 ? round($totalSetupMinutes / $totalSetups, 2) : 0, // Minutes
            'max_setup_duration' => $totalSetups > 0 ? max(array_column($dailyBreakdown, 'max_setup_time')) : 0,
            'min_setup_duration' => $totalSetups > 0 ? min(array_filter(array_column($dailyBreakdown, 'min_setup_time'))) : 0,
            'days_analyzed' => $daysCount,
            'machines_with_setups' => $machinesCount,
        ];

        // Calculate setup % of available time (8-hour shift = 480 minutes)
        if ($daysCount > 0) {
            $summary['avg_setup_percentage'] = round(($summary['avg_daily_setup_time'] * 60 / 480) * 100, 2);
        } else {
            $summary['avg_setup_percentage'] = 0;
        }

        return [
            'daily' => $dailyBreakdown,
            'by_machine' => array_values($machineBreakdown),
            'summary' => $summary,
        ];
    }

    /**
     * Calculate comparison metrics for setup time
     */
    protected function calculateSetupTimeComparison(array $current, array $previous): array
    {
        return [
            'total_setup_time' => [
                'current' => $current['total_setup_time'] ?? 0,
                'previous' => $previous['total_setup_time'] ?? 0,
                'difference' => round(($current['total_setup_time'] ?? 0) - ($previous['total_setup_time'] ?? 0), 2),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_setup_time'] ?? 0,
                    $previous['total_setup_time'] ?? 0
                ),
                'trend' => ($current['total_setup_time'] ?? 0) > ($previous['total_setup_time'] ?? 0) ? 'up' : 'down',
                'status' => ($current['total_setup_time'] ?? 0) < ($previous['total_setup_time'] ?? 0) ? 'improved' : 'declined',
            ],
            'avg_daily_setup_time' => [
                'current' => $current['avg_daily_setup_time'] ?? 0,
                'previous' => $previous['avg_daily_setup_time'] ?? 0,
                'difference' => round(($current['avg_daily_setup_time'] ?? 0) - ($previous['avg_daily_setup_time'] ?? 0), 2),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_daily_setup_time'] ?? 0,
                    $previous['avg_daily_setup_time'] ?? 0
                ),
                'trend' => ($current['avg_daily_setup_time'] ?? 0) > ($previous['avg_daily_setup_time'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_daily_setup_time'] ?? 0) < ($previous['avg_daily_setup_time'] ?? 0) ? 'improved' : 'declined',
            ],
            'avg_setup_duration' => [
                'current' => $current['avg_setup_duration'] ?? 0,
                'previous' => $previous['avg_setup_duration'] ?? 0,
                'difference' => round(($current['avg_setup_duration'] ?? 0) - ($previous['avg_setup_duration'] ?? 0), 2),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_setup_duration'] ?? 0,
                    $previous['avg_setup_duration'] ?? 0
                ),
                'trend' => ($current['avg_setup_duration'] ?? 0) > ($previous['avg_setup_duration'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_setup_duration'] ?? 0) < ($previous['avg_setup_duration'] ?? 0) ? 'improved' : 'declined',
            ],
            'total_setups' => [
                'current' => $current['total_setups'] ?? 0,
                'previous' => $previous['total_setups'] ?? 0,
                'difference' => ($current['total_setups'] ?? 0) - ($previous['total_setups'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_setups'] ?? 0,
                    $previous['total_setups'] ?? 0
                ),
                'trend' => ($current['total_setups'] ?? 0) > ($previous['total_setups'] ?? 0) ? 'up' : 'down',
                'status' => 'neutral',
            ],
        ];
    }

    /**
     * Get defect rate analytics with historical comparisons
     */
    public function getDefectRateAnalytics(array $options): array
    {
        $period = $options['time_period'] ?? 'yesterday';
        $enableComparison = $options['enable_comparison'] ?? false;
        $comparisonType = $options['comparison_type'] ?? 'previous_period';
        $machineFilter = $options['machine_id'] ?? null;

        $dateFrom = isset($options['date_from']) ? Carbon::parse($options['date_from']) : null;
        $dateTo = isset($options['date_to']) ? Carbon::parse($options['date_to']) : null;

        [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

        $cacheKey = "defect_rate_analytics_{$period}_".md5(json_encode($options));
        $cacheTTL = $this->getCacheTTL($period);

        return $this->getCachedKPI($cacheKey, function () use ($startDate, $endDate, $enableComparison, $comparisonType, $machineFilter, $options) {
            $primaryData = $this->fetchDefectRateDistribution($startDate, $endDate, $machineFilter);

            $result = [
                'primary_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'label' => $this->getPeriodLabel($options['time_period'] ?? 'yesterday', $startDate, $endDate),
                    'daily_breakdown' => $primaryData['daily'],
                    'machine_breakdown' => $primaryData['by_machine'],
                    'work_order_breakdown' => $primaryData['by_work_order'],
                    'summary' => $primaryData['summary'],
                ],
            ];

            if ($enableComparison) {
                [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);

                $comparisonData = $this->fetchDefectRateDistribution($compStart, $compEnd, $machineFilter);

                $result['comparison_period'] = [
                    'start_date' => $compStart->toDateString(),
                    'end_date' => $compEnd->toDateString(),
                    'label' => $this->getPeriodLabel($comparisonType, $compStart, $compEnd),
                    'daily_breakdown' => $comparisonData['daily'],
                    'machine_breakdown' => $comparisonData['by_machine'],
                    'work_order_breakdown' => $comparisonData['by_work_order'],
                    'summary' => $comparisonData['summary'],
                ];

                $result['comparison_analysis'] = $this->calculateDefectRateComparison(
                    $primaryData['summary'],
                    $comparisonData['summary']
                );
            }

            return $result;
        }, $cacheTTL);
    }

    /**
     * Aggregate defect rate metrics for the supplied window
     */
    protected function fetchDefectRateDistribution(Carbon $startDate, Carbon $endDate, ?int $machineFilter = null): array
    {
        $logs = \App\Models\WorkOrderLog::query()
            ->whereBetween('changed_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereRaw('(ok_qtys + scrapped_qtys) > 0')
            ->whereHas('workOrder', function ($query) use ($machineFilter) {
                $query->where('factory_id', $this->factory->id);

                if ($machineFilter) {
                    $query->where('machine_id', $machineFilter);
                }
            })
            ->with([
                'workOrder:id,unique_id,machine_id,factory_id,start_time,ok_qtys,scrapped_qtys',
                'workOrder.machine:id,name,assetId',
            ])
            ->orderBy('changed_at')
            ->get();

        $daily = [];
        $dailyWorkOrders = [];
        $machineBreakdown = [];
        $workOrderTotals = [];
        $totalScrap = 0;
        $totalOk = 0;

        foreach ($logs as $log) {
            $workOrder = $log->workOrder;

            if (! $workOrder || $workOrder->factory_id !== $this->factory->id) {
                continue;
            }

            $scrap = max(0, (int) $log->scrapped_qtys);
            $ok = max(0, (int) $log->ok_qtys);
            $produced = $scrap + $ok;

            if ($produced <= 0) {
                continue;
            }

            $date = $log->changed_at->toDateString();

            if (! isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'scrap_qty' => 0,
                    'ok_qty' => 0,
                    'produced_qty' => 0,
                    'scrap_work_orders' => [],
                ];
            }

            $daily[$date]['scrap_qty'] += $scrap;
            $daily[$date]['ok_qty'] += $ok;
            $daily[$date]['produced_qty'] += $produced;

            if ($scrap > 0) {
                $daily[$date]['scrap_work_orders'][$workOrder->id] = true;
            }

            if (! isset($dailyWorkOrders[$date][$workOrder->id])) {
                $dailyWorkOrders[$date][$workOrder->id] = [
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->unique_id ?? 'N/A',
                    'machine_name' => $workOrder->machine?->name ?? 'Unknown',
                    'machine_asset_id' => $workOrder->machine?->assetId,
                    'scrap_qty' => 0,
                    'ok_qty' => 0,
                ];
            }

            $dailyWorkOrders[$date][$workOrder->id]['scrap_qty'] += $scrap;
            $dailyWorkOrders[$date][$workOrder->id]['ok_qty'] += $ok;

            $machineId = $workOrder->machine_id;

            if (! isset($machineBreakdown[$machineId])) {
                $machineBreakdown[$machineId] = [
                    'machine_id' => $machineId,
                    'machine_name' => $workOrder->machine?->name ?? 'Unknown',
                    'asset_id' => $workOrder->machine?->assetId,
                    'scrap_qty' => 0,
                    'ok_qty' => 0,
                    'produced_qty' => 0,
                    'scrap_work_orders' => [],
                ];
            }

            $machineBreakdown[$machineId]['scrap_qty'] += $scrap;
            $machineBreakdown[$machineId]['ok_qty'] += $ok;
            $machineBreakdown[$machineId]['produced_qty'] += $produced;

            if ($scrap > 0) {
                $machineBreakdown[$machineId]['scrap_work_orders'][$workOrder->id] = true;
            }

            if (! isset($workOrderTotals[$workOrder->id])) {
                $workOrderTotals[$workOrder->id] = [
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->unique_id ?? 'N/A',
                    'machine_id' => $machineId,
                    'machine_name' => $workOrder->machine?->name ?? 'Unknown',
                    'machine_asset_id' => $workOrder->machine?->assetId,
                    'scrap_qty' => 0,
                    'ok_qty' => 0,
                    'produced_qty' => 0,
                    'last_scrap_at' => null,
                ];
            }

            $workOrderTotals[$workOrder->id]['scrap_qty'] += $scrap;
            $workOrderTotals[$workOrder->id]['ok_qty'] += $ok;
            $workOrderTotals[$workOrder->id]['produced_qty'] += $produced;

            if ($scrap > 0) {
                $lastScrapAt = $workOrderTotals[$workOrder->id]['last_scrap_at'];

                if (! $lastScrapAt || $log->changed_at->gt($lastScrapAt)) {
                    $workOrderTotals[$workOrder->id]['last_scrap_at'] = $log->changed_at->copy();
                }
            }

            $totalScrap += $scrap;
            $totalOk += $ok;
        }

        ksort($daily);
        $dailyBreakdown = [];

        foreach ($daily as $date => $day) {
            $produced = $day['produced_qty'];
            $defectRate = $produced > 0 ? round(($day['scrap_qty'] / $produced) * 100, 2) : 0;
            $defectiveWorkOrders = count($day['scrap_work_orders']);

            $worst = null;

            foreach ($dailyWorkOrders[$date] ?? [] as $wo) {
                $woProduced = $wo['scrap_qty'] + $wo['ok_qty'];
                $woRate = $woProduced > 0 ? round(($wo['scrap_qty'] / $woProduced) * 100, 2) : 0;

                if (! $worst || $woRate > $worst['defect_rate'] || ($woRate === $worst['defect_rate'] && $wo['scrap_qty'] > $worst['scrap_qty'])) {
                    $worst = [
                        'work_order_id' => $wo['work_order_id'],
                        'work_order_number' => $wo['work_order_number'],
                        'machine_name' => $wo['machine_name'],
                        'machine_asset_id' => $wo['machine_asset_id'],
                        'defect_rate' => $woRate,
                        'scrap_qty' => $wo['scrap_qty'],
                    ];
                }
            }

            $dailyBreakdown[] = [
                'date' => $date,
                'scrap_qty' => $day['scrap_qty'],
                'ok_qty' => $day['ok_qty'],
                'produced_qty' => $produced,
                'defect_rate' => $defectRate,
                'defective_work_orders' => $defectiveWorkOrders,
                'worst_work_order' => $worst,
            ];
        }

        foreach ($machineBreakdown as &$machine) {
            $produced = $machine['produced_qty'];
            $machine['defect_rate'] = $produced > 0 ? round(($machine['scrap_qty'] / $produced) * 100, 2) : 0;
            $machine['defective_work_orders'] = count($machine['scrap_work_orders']);
            unset($machine['scrap_work_orders']);
        }
        unset($machine);

        usort($machineBreakdown, fn ($a, $b) => $b['scrap_qty'] <=> $a['scrap_qty']);
        $machineBreakdown = array_values($machineBreakdown);

        foreach ($workOrderTotals as &$wo) {
            $produced = $wo['produced_qty'];
            $wo['defect_rate'] = $produced > 0 ? round(($wo['scrap_qty'] / $produced) * 100, 2) : 0;
            $wo['last_scrap_at'] = $wo['last_scrap_at'] ? $wo['last_scrap_at']->toDateTimeString() : null;
        }
        unset($wo);

        usort($workOrderTotals, function ($a, $b) {
            if ($b['defect_rate'] === $a['defect_rate']) {
                return $b['scrap_qty'] <=> $a['scrap_qty'];
            }

            return $b['defect_rate'] <=> $a['defect_rate'];
        });

        $workOrderBreakdown = array_values($workOrderTotals);

        $totalProduced = $totalScrap + $totalOk;
        $avgDefectRate = $totalProduced > 0 ? round(($totalScrap / $totalProduced) * 100, 2) : 0;
        $worstDefectRate = ! empty($workOrderBreakdown) ? ($workOrderBreakdown[0]['defect_rate'] ?? 0) : 0;
        $workOrdersWithScrap = count(array_filter($workOrderBreakdown, fn ($wo) => $wo['scrap_qty'] > 0));
        $machinesWithScrap = count(array_filter($machineBreakdown, fn ($machine) => $machine['scrap_qty'] > 0));

        $summary = [
            'total_scrap_qty' => $totalScrap,
            'total_ok_qty' => $totalOk,
            'total_produced_qty' => $totalProduced,
            'avg_defect_rate' => $avgDefectRate,
            'worst_defect_rate' => $worstDefectRate,
            'work_orders_with_scrap' => $workOrdersWithScrap,
            'machines_with_scrap' => $machinesWithScrap,
            'days_analyzed' => count($dailyBreakdown),
        ];

        return [
            'daily' => $dailyBreakdown,
            'by_machine' => $machineBreakdown,
            'by_work_order' => $workOrderBreakdown,
            'summary' => $summary,
        ];
    }

    /**
     * Compare defect rate metrics between two periods
     */
    protected function calculateDefectRateComparison(array $current, array $previous): array
    {
        return [
            'total_scrap_qty' => [
                'current' => $current['total_scrap_qty'] ?? 0,
                'previous' => $previous['total_scrap_qty'] ?? 0,
                'difference' => ($current['total_scrap_qty'] ?? 0) - ($previous['total_scrap_qty'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_scrap_qty'] ?? 0,
                    $previous['total_scrap_qty'] ?? 0
                ),
                'trend' => ($current['total_scrap_qty'] ?? 0) > ($previous['total_scrap_qty'] ?? 0) ? 'up' : 'down',
                'status' => ($current['total_scrap_qty'] ?? 0) < ($previous['total_scrap_qty'] ?? 0) ? 'improved' : 'declined',
            ],
            'avg_defect_rate' => [
                'current' => $current['avg_defect_rate'] ?? 0,
                'previous' => $previous['avg_defect_rate'] ?? 0,
                'difference' => round(($current['avg_defect_rate'] ?? 0) - ($previous['avg_defect_rate'] ?? 0), 2),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['avg_defect_rate'] ?? 0,
                    $previous['avg_defect_rate'] ?? 0
                ),
                'trend' => ($current['avg_defect_rate'] ?? 0) > ($previous['avg_defect_rate'] ?? 0) ? 'up' : 'down',
                'status' => ($current['avg_defect_rate'] ?? 0) < ($previous['avg_defect_rate'] ?? 0) ? 'improved' : 'declined',
            ],
            'work_orders_with_scrap' => [
                'current' => $current['work_orders_with_scrap'] ?? 0,
                'previous' => $previous['work_orders_with_scrap'] ?? 0,
                'difference' => ($current['work_orders_with_scrap'] ?? 0) - ($previous['work_orders_with_scrap'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['work_orders_with_scrap'] ?? 0,
                    $previous['work_orders_with_scrap'] ?? 0
                ),
                'trend' => ($current['work_orders_with_scrap'] ?? 0) > ($previous['work_orders_with_scrap'] ?? 0) ? 'up' : 'down',
                'status' => ($current['work_orders_with_scrap'] ?? 0) < ($previous['work_orders_with_scrap'] ?? 0) ? 'improved' : 'declined',
            ],
            'total_produced_qty' => [
                'current' => $current['total_produced_qty'] ?? 0,
                'previous' => $previous['total_produced_qty'] ?? 0,
                'difference' => ($current['total_produced_qty'] ?? 0) - ($previous['total_produced_qty'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_produced_qty'] ?? 0,
                    $previous['total_produced_qty'] ?? 0
                ),
                'trend' => ($current['total_produced_qty'] ?? 0) > ($previous['total_produced_qty'] ?? 0) ? 'up' : 'down',
                'status' => ($current['total_produced_qty'] ?? 0) >= ($previous['total_produced_qty'] ?? 0) ? 'improved' : 'declined',
            ],
        ];
    }

    /**
     * Get all operational KPIs (implements abstract method)
     */
    public function getKPIs(array $options = []): array
    {
        return [
            'machine_status' => $this->getMachineStatusAnalytics($options),
            'work_order_status' => $this->getWorkOrderStatusAnalytics($options),
            'machine_utilization' => $this->getMachineUtilizationAnalytics($options),
            'setup_time' => $this->getSetupTimeAnalytics($options),
            'defect_rate' => $this->getDefectRateAnalytics($options),
            // Future: Add more Tier 2 KPIs here
            // 'production_throughput' => $this->getProductionThroughputAnalytics($options),
            // 'operator_performance' => $this->getOperatorPerformanceAnalytics($options),
        ];
    }

}
