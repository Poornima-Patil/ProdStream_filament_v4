<?php

namespace App\Services;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class KPIService
{
    /**
     * Calculate Work Order Completion Rate for a specific factory
     * Formula: (Completed Work Orders / Total Work Orders) × 100%
     */
    public function getWorkOrderCompletionRate($factoryId, $period = '30d')
    {
        $cacheKey = "kpi_completion_rate_factory_{$factoryId}_{$period}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $period) {
            $dateRange = $this->getDateRange($period);

            // Get total work orders for this factory in the period
            $totalOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->count();

            // Get completed work orders for this factory in the period
            $completedOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->where('status', 'Completed')
                ->count();

            if ($totalOrders === 0) {
                return [
                    'rate' => 0,
                    'total_orders' => 0,
                    'completed_orders' => 0,
                    'trend' => 0,
                    'status' => 'neutral'
                ];
            }

            $rate = round(($completedOrders / $totalOrders) * 100, 1);
            $trend = $this->calculateTrend('completion_rate', $rate, $factoryId, $period);
            $status = $this->getStatus($rate, 85); // 85% target

            return [
                'rate' => $rate,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'trend' => $trend,
                'status' => $status
            ];
        });
    }

    /**
     * Calculate Production Throughput for a specific factory
     * Formula: Total Units Produced / Time Period (units per day)
     */
    public function getProductionThroughput($factoryId, $period = '30d')
    {
        $cacheKey = "kpi_production_throughput_factory_{$factoryId}_{$period}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $period) {
            $dateRange = $this->getDateRange($period);
            $days = $this->getPeriodDays($period);

            // Get total units produced from completed work orders
            $totalUnitsProduced = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->where('status', 'Completed')
                ->sum('ok_qtys');

            // Get production breakdown by BOM (part numbers)
            $productionByPart = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->where('status', 'Completed')
                ->with(['bom.purchaseOrder.partNumber'])
                ->selectRaw('bom_id, SUM(ok_qtys) as total_units, COUNT(*) as order_count')
                ->groupBy('bom_id')
                ->orderBy('total_units', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) use ($totalUnitsProduced) {
                    // Get the part number with revision from BOM -> PurchaseOrder -> PartNumber
                    $partNumber = 'Unknown Part';
                    if ($item->bom && $item->bom->purchaseOrder && $item->bom->purchaseOrder->partNumber) {
                        $pn = $item->bom->purchaseOrder->partNumber;
                        $partNumber = $pn->partnumber . '_' . $pn->revision;
                    } elseif ($item->bom) {
                        $partNumber = $item->bom->unique_id;
                    }

                    return [
                        'part' => $partNumber,
                        'units' => (int) $item->total_units,
                        'percentage' => $totalUnitsProduced > 0 ? round(($item->total_units / $totalUnitsProduced) * 100, 1) : 0,
                        'orders' => $item->order_count
                    ];
                })
                ->toArray();

            // Get production breakdown by machine
            $productionByMachine = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->where('status', 'Completed')
                ->with('machine')
                ->selectRaw('machine_id, SUM(ok_qtys) as total_units, COUNT(*) as order_count, AVG(ok_qtys/qty * 100) as avg_efficiency')
                ->groupBy('machine_id')
                ->orderBy('total_units', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    $efficiency = round($item->avg_efficiency ?? 0, 1);
                    return [
                        'machine' => $item->machine ? $item->machine->name : 'Machine-' . $item->machine_id,
                        'units' => (int) $item->total_units,
                        'efficiency' => $efficiency,
                        'orders' => $item->order_count,
                        'status' => $this->getMachineStatus($efficiency)
                    ];
                })
                ->toArray();

            if ($totalUnitsProduced === 0 || $days === 0) {
                return [
                    'throughput' => 0,
                    'total_units' => 0,
                    'units_per_day' => 0,
                    'days' => $days,
                    'trend' => 0,
                    'status' => 'neutral',
                    'production_by_part' => [],
                    'production_by_machine' => []
                ];
            }

            $unitsPerDay = round($totalUnitsProduced / $days, 1);
            $trend = $this->calculateTrend('production_throughput', $unitsPerDay, $factoryId, $period);
            $status = $this->getProductionStatus($unitsPerDay);

            return [
                'throughput' => $unitsPerDay,
                'total_units' => $totalUnitsProduced,
                'units_per_day' => $unitsPerDay,
                'days' => $days,
                'trend' => $trend,
                'status' => $status,
                'production_by_part' => $productionByPart,
                'production_by_machine' => $productionByMachine
            ];
        });
    }

    /**
     * Get all KPIs for the executive dashboard
     */
    public function getExecutiveKPIs($factoryId, $period = '30d')
    {
        return [
            'work_order_completion_rate' => $this->getWorkOrderCompletionRate($factoryId, $period),
            'production_throughput' => $this->getProductionThroughput($factoryId, $period),
            // Placeholder for future KPIs
            'scrap_rate' => $this->getPlaceholderKPI('Scrap Rate', '%'),
            'machine_utilization' => $this->getPlaceholderKPI('Machine Utilization', '%'),
            'quality_rate' => $this->getPlaceholderKPI('Quality Rate', '%'),
            'on_time_delivery' => $this->getPlaceholderKPI('On-Time Delivery', '%'),
        ];
    }

    /**
     * Get placeholder KPI structure for future implementation
     */
    private function getPlaceholderKPI($name, $unit)
    {
        return [
            'name' => $name,
            'value' => '--',
            'unit' => $unit,
            'trend' => 0,
            'status' => 'neutral',
            'coming_soon' => true
        ];
    }

    /**
     * Get machine status based on efficiency
     */
    private function getMachineStatus($efficiency)
    {
        if ($efficiency >= 90) {
            return 'excellent'; // Green
        } elseif ($efficiency >= 80) {
            return 'good'; // Light green
        } elseif ($efficiency >= 70) {
            return 'warning'; // Yellow
        } else {
            return 'critical'; // Red
        }
    }

    /**
     * Get number of days in a period
     */
    private function getPeriodDays($period)
    {
        switch ($period) {
            case 'today':
                return 1;
            case '7d':
                return 7;
            case '30d':
                return 30;
            case '90d':
                return 90;
            case 'yesterday':
                return 1;
            case 'previous_7d':
                return 7;
            case 'previous_30d':
                return 30;
            case 'previous_90d':
                return 90;
            default:
                return 30;
        }
    }

    /**
     * Get production status based on units per day
     */
    private function getProductionStatus($unitsPerDay)
    {
        // Define thresholds based on your production targets
        if ($unitsPerDay >= 1000) {
            return 'excellent'; // Green
        } elseif ($unitsPerDay >= 750) {
            return 'good'; // Light green
        } elseif ($unitsPerDay >= 500) {
            return 'warning'; // Yellow
        } else {
            return 'critical'; // Red
        }
    }

    /**
     * Calculate trend compared to previous period
     */
    private function calculateTrend($kpiType, $currentValue, $factoryId, $period)
    {
        try {
            $previousPeriod = $this->getPreviousPeriod($period);
            $cacheKey = "kpi_{$kpiType}_trend_factory_{$factoryId}_{$period}";

            return Cache::remember($cacheKey, 600, function () use ($kpiType, $currentValue, $factoryId, $previousPeriod) {
                if ($kpiType === 'completion_rate') {
                    $dateRange = $this->getDateRange($previousPeriod);

                    $totalOrders = WorkOrder::where('factory_id', $factoryId)
                        ->whereBetween('created_at', $dateRange)
                        ->count();

                    $completedOrders = WorkOrder::where('factory_id', $factoryId)
                        ->whereBetween('created_at', $dateRange)
                        ->where('status', 'Completed')
                        ->count();

                    if ($totalOrders === 0) {
                        return 0;
                    }

                    $previousValue = ($completedOrders / $totalOrders) * 100;
                    return round($currentValue - $previousValue, 1);
                } elseif ($kpiType === 'production_throughput') {
                    $dateRange = $this->getDateRange($previousPeriod);
                    $days = $this->getPeriodDays($previousPeriod);

                    $totalUnitsProduced = WorkOrder::where('factory_id', $factoryId)
                        ->whereBetween('created_at', $dateRange)
                        ->where('status', 'Completed')
                        ->sum('ok_qtys');

                    if ($totalUnitsProduced === 0 || $days === 0) {
                        return 0;
                    }

                    $previousValue = $totalUnitsProduced / $days;
                    return round($currentValue - $previousValue, 1);
                }

                return 0;
            });
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get status based on value vs target
     */
    private function getStatus($value, $target)
    {
        $percentage = ($value / $target) * 100;

        if ($percentage >= 100) {
            return 'excellent'; // Green
        } elseif ($percentage >= 90) {
            return 'good'; // Light green
        } elseif ($percentage >= 75) {
            return 'warning'; // Yellow
        } else {
            return 'critical'; // Red
        }
    }

    /**
     * Get date range based on period
     */
    private function getDateRange($period)
    {
        $endDate = Carbon::now();

        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                break;
            case '7d':
                $startDate = Carbon::now()->subDays(7);
                break;
            case '30d':
                $startDate = Carbon::now()->subDays(30);
                break;
            case '90d':
                $startDate = Carbon::now()->subDays(90);
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday();
                $endDate = Carbon::yesterday()->endOfDay();
                break;
            case 'previous_7d':
                $startDate = Carbon::now()->subDays(14);
                $endDate = Carbon::now()->subDays(7);
                break;
            case 'previous_30d':
                $startDate = Carbon::now()->subDays(60);
                $endDate = Carbon::now()->subDays(30);
                break;
            case 'previous_90d':
                $startDate = Carbon::now()->subDays(180);
                $endDate = Carbon::now()->subDays(90);
                break;
            default:
                $startDate = Carbon::now()->subDays(30);
        }

        return [$startDate, $endDate];
    }

    /**
     * Get previous period for trend calculation
     */
    private function getPreviousPeriod($period)
    {
        switch ($period) {
            case 'today':
                return 'yesterday';
            case '7d':
                return 'previous_7d';
            case '30d':
                return 'previous_30d';
            case '90d':
                return 'previous_90d';
            default:
                return 'previous_30d';
        }
    }

    /**
     * Clear KPI cache for a specific factory
     */
    public function clearKPICache($factoryId)
    {
        $patterns = [
            "kpi_completion_rate_factory_{$factoryId}_*",
            "kpi_production_throughput_factory_{$factoryId}_*",
            "kpi_*_trend_factory_{$factoryId}_*"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
