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
     */
    public function getMachineStatusAnalytics(array $options): array
    {
        $period = $options['time_period'] ?? 'today';
        $enableComparison = $options['enable_comparison'] ?? false;
        $comparisonType = $options['comparison_type'] ?? 'previous_period';

        $dateFrom = isset($options['date_from']) ? Carbon::parse($options['date_from']) : null;
        $dateTo = isset($options['date_to']) ? Carbon::parse($options['date_to']) : null;

        [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

        $cacheKey = "machine_status_analytics_{$period}_".md5(json_encode($options));
        $cacheTTL = $this->getCacheTTL($period);

        return $this->getCachedKPI($cacheKey, function () use ($startDate, $endDate, $enableComparison, $comparisonType, $options) {
            // Fetch primary period data
            $primaryData = $this->fetchMachineStatusHistory($startDate, $endDate);

            $result = [
                'primary_period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'label' => $this->getPeriodLabel($options['time_period'] ?? 'today', $startDate, $endDate),
                    'daily_breakdown' => $primaryData['daily'],
                    'summary' => $primaryData['summary'],
                ],
            ];

            // Add comparison if enabled
            if ($enableComparison) {
                [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);

                $comparisonData = $this->fetchMachineStatusHistory($compStart, $compEnd);

                $result['comparison_period'] = [
                    'start_date' => $compStart->toDateString(),
                    'end_date' => $compEnd->toDateString(),
                    'label' => $this->getPeriodLabel($comparisonType, $compStart, $compEnd),
                    'daily_breakdown' => $comparisonData['daily'],
                    'summary' => $comparisonData['summary'],
                ];

                $result['comparison_analysis'] = $this->calculateMetricsComparison(
                    $primaryData['summary'],
                    $comparisonData['summary']
                );
            }

            return $result;
        }, $cacheTTL);
    }

    /**
     * Fetch machine status history from kpi_machine_daily table
     */
    protected function fetchMachineStatusHistory(Carbon $startDate, Carbon $endDate): array
    {
        $dailyData = MachineDaily::where('factory_id', $this->factory->id)
            ->whereBetween('summary_date', [$startDate, $endDate])
            ->orderBy('summary_date', 'asc')
            ->get();

        // Build daily breakdown
        $dailyBreakdown = [];
        $totalUtilization = 0;
        $totalActiveUtilization = 0;
        $totalUptime = 0;
        $totalDowntime = 0;
        $daysCount = 0;

        foreach ($dailyData as $day) {
            $date = $day->summary_date->toDateString();

            if (! isset($dailyBreakdown[$date])) {
                $dailyBreakdown[$date] = [
                    'utilization_rate' => 0,
                    'active_utilization_rate' => 0,
                    'uptime_hours' => 0,
                    'downtime_hours' => 0,
                    'units_produced' => 0,
                    'work_orders_count' => 0,
                    'machine_count' => 0,
                ];
                $daysCount++;
            }

            $dailyBreakdown[$date]['utilization_rate'] += $day->utilization_rate;
            $dailyBreakdown[$date]['active_utilization_rate'] += $day->active_utilization_rate ?? 0;
            $dailyBreakdown[$date]['uptime_hours'] += $day->uptime_hours;
            $dailyBreakdown[$date]['downtime_hours'] += $day->downtime_hours;
            $dailyBreakdown[$date]['units_produced'] += $day->units_produced;
            $dailyBreakdown[$date]['work_orders_count'] += $day->work_orders_completed ?? 0;
            $dailyBreakdown[$date]['machine_count']++;

            $totalUtilization += $day->utilization_rate;
            $totalActiveUtilization += $day->active_utilization_rate ?? 0;
            $totalUptime += $day->uptime_hours;
            $totalDowntime += $day->downtime_hours;
        }

        // Calculate averages and add date to each breakdown entry
        $formattedDailyBreakdown = [];
        foreach ($dailyBreakdown as $date => $data) {
            $entry = $data;
            $entry['date'] = $date; // Add date to the entry

            if ($data['machine_count'] > 0) {
                $entry['avg_utilization_rate'] = round($data['utilization_rate'] / $data['machine_count'], 2);
                $entry['avg_active_utilization_rate'] = round($data['active_utilization_rate'] / $data['machine_count'], 2);
            }

            $formattedDailyBreakdown[] = $entry;
        }

        // Calculate summary statistics
        $machineCount = $dailyData->unique('machine_id')->count();
        $avgUtilization = $daysCount > 0 && $machineCount > 0
            ? round($totalUtilization / ($daysCount * $machineCount), 2)
            : 0;

        $avgActiveUtilization = $daysCount > 0 && $machineCount > 0
            ? round($totalActiveUtilization / ($daysCount * $machineCount), 2)
            : 0;

        $summary = [
            'avg_scheduled_utilization' => $avgUtilization,
            'avg_active_utilization' => $avgActiveUtilization,
            'total_uptime_hours' => round($totalUptime, 2),
            'total_downtime_hours' => round($totalDowntime, 2),
            'total_units_produced' => $dailyData->sum('units_produced'),
            'total_work_orders' => $dailyData->sum('work_orders_completed'),
            'machine_count' => $machineCount,
            'days_analyzed' => $daysCount,
        ];

        return [
            'daily' => $formattedDailyBreakdown,
            'summary' => $summary,
        ];
    }

    /**
     * Calculate comparison metrics between two periods
     */
    protected function calculateMetricsComparison(array $current, array $previous): array
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
            'work_orders' => [
                'current' => $current['total_work_orders'] ?? 0,
                'previous' => $previous['total_work_orders'] ?? 0,
                'difference' => ($current['total_work_orders'] ?? 0) - ($previous['total_work_orders'] ?? 0),
                'percentage_change' => $this->calculatePercentageChange(
                    $current['total_work_orders'] ?? 0,
                    $previous['total_work_orders'] ?? 0
                ),
                'trend' => ($current['total_work_orders'] ?? 0) > ($previous['total_work_orders'] ?? 0) ? 'up' : 'down',
                'status' => ($current['total_work_orders'] ?? 0) > ($previous['total_work_orders'] ?? 0) ? 'improved' : 'declined',
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
