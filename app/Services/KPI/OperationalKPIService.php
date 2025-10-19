<?php

namespace App\Services\KPI;

use App\Models\Factory;
use App\Models\KPI\MachineDaily;
use Carbon\Carbon;

class OperationalKPIService extends BaseKPIService
{
    public function __construct(Factory $factory)
    {
        parent::__construct($factory, 'tier_2');
    }

    /**
     * Get machine status analytics with historical data and comparisons
     * Returns historical breakdown of machine status distribution (Running/Hold/Scheduled/Idle)
     */
    public function getMachineStatusAnalytics(array $options): array
    {
        $period = $options['time_period'] ?? 'yesterday';
        $enableComparison = $options['enable_comparison'] ?? false;
        $comparisonType = $options['comparison_type'] ?? 'previous_period';

        $dateFrom = isset($options['date_from']) ? Carbon::parse($options['date_from']) : null;
        $dateTo = isset($options['date_to']) ? Carbon::parse($options['date_to']) : null;

        [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

        $cacheKey = "machine_status_analytics_{$period}_".md5(json_encode($options));
        $cacheTTL = $this->getCacheTTL($period);

        return $this->getCachedKPI($cacheKey, function () use ($startDate, $endDate, $enableComparison, $comparisonType, $options) {
            // Fetch primary period data
            $primaryData = $this->fetchMachineStatusDistribution($startDate, $endDate);

            $result = [
                'primary_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'label' => $this->getPeriodLabel($options['time_period'] ?? 'yesterday', $startDate, $endDate),
                    'daily_breakdown' => $primaryData['daily'],
                    'summary' => $primaryData['summary'],
                ],
            ];

            // Add comparison if enabled
            if ($enableComparison) {
                [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);

                $comparisonData = $this->fetchMachineStatusDistribution($compStart, $compEnd);

                $result['comparison_period'] = [
                    'start_date' => $compStart->toDateString(),
                    'end_date' => $compEnd->toDateString(),
                    'label' => $this->getPeriodLabel($comparisonType, $compStart, $compEnd),
                    'daily_breakdown' => $comparisonData['daily'],
                    'summary' => $comparisonData['summary'],
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
     * Returns daily breakdown of machines by status: Running, Hold, Scheduled, Idle
     */
    protected function fetchMachineStatusDistribution(Carbon $startDate, Carbon $endDate): array
    {
        $totalMachines = \App\Models\Machine::where('factory_id', $this->factory->id)->count();

        // Initialize daily breakdown
        $dailyBreakdown = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();
            $dayStart = $current->copy()->startOfDay();
            $dayEnd = $current->copy()->endOfDay();

            // Get work orders for this day
            $workOrders = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
                ->whereBetween('start_time', [$dayStart, $dayEnd])
                ->whereIn('status', ['Start', 'Assigned', 'Hold', 'Completed'])
                ->get(['id', 'machine_id', 'status']);

            // Group machines by status
            $machinesRunning = $workOrders->where('status', 'Start')->pluck('machine_id')->unique()->count();
            $machinesOnHold = $workOrders->where('status', 'Hold')->pluck('machine_id')->unique()->count();
            $machinesScheduled = $workOrders->where('status', 'Assigned')->pluck('machine_id')->unique()->count();

            // Machines with any work orders (Running, Hold, or Scheduled)
            $machinesWithWork = $workOrders->pluck('machine_id')->unique()->count();
            $machinesIdle = $totalMachines - $machinesWithWork;

            $dailyBreakdown[] = [
                'date' => $dateStr,
                'running' => $machinesRunning,
                'hold' => $machinesOnHold,
                'scheduled' => $machinesScheduled,
                'idle' => $machinesIdle,
                'total_machines' => $totalMachines,
            ];

            $current->addDay();
        }

        // Calculate summary statistics
        $totalRunning = 0;
        $totalHold = 0;
        $totalScheduled = 0;
        $totalIdle = 0;
        $daysCount = count($dailyBreakdown);

        foreach ($dailyBreakdown as $day) {
            $totalRunning += $day['running'];
            $totalHold += $day['hold'];
            $totalScheduled += $day['scheduled'];
            $totalIdle += $day['idle'];
        }

        $summary = [
            'avg_running' => $daysCount > 0 ? round($totalRunning / $daysCount, 1) : 0,
            'avg_hold' => $daysCount > 0 ? round($totalHold / $daysCount, 1) : 0,
            'avg_scheduled' => $daysCount > 0 ? round($totalScheduled / $daysCount, 1) : 0,
            'avg_idle' => $daysCount > 0 ? round($totalIdle / $daysCount, 1) : 0,
            'total_machines' => $totalMachines,
            'days_analyzed' => $daysCount,
            'avg_running_pct' => $totalMachines > 0 && $daysCount > 0
                ? round(($totalRunning / ($totalMachines * $daysCount)) * 100, 1)
                : 0,
            'avg_hold_pct' => $totalMachines > 0 && $daysCount > 0
                ? round(($totalHold / ($totalMachines * $daysCount)) * 100, 1)
                : 0,
            'avg_scheduled_pct' => $totalMachines > 0 && $daysCount > 0
                ? round(($totalScheduled / ($totalMachines * $daysCount)) * 100, 1)
                : 0,
            'avg_idle_pct' => $totalMachines > 0 && $daysCount > 0
                ? round(($totalIdle / ($totalMachines * $daysCount)) * 100, 1)
                : 0,
        ];

        return [
            'daily' => $dailyBreakdown,
            'summary' => $summary,
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
     * Get all operational KPIs (implements abstract method)
     */
    public function getKPIs(array $options = []): array
    {
        return [
            'machine_status' => $this->getMachineStatusAnalytics($options),
            // Future: Add more Tier 2 KPIs here
            // 'production_throughput' => $this->getProductionThroughputAnalytics($options),
            // 'operator_performance' => $this->getOperatorPerformanceAnalytics($options),
        ];
    }
}
