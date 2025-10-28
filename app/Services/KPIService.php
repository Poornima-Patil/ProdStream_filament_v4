<?php

namespace App\Services;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;

class KPIService
{
    /**
     * Calculate Work Order Completion Rate for a specific factory using custom date range
     * Formula: (Completed Work Orders / Total Work Orders) × 100%
     */
    public function getWorkOrderCompletionRateWithDateRange($factoryId, $fromDate, $toDate)
    {
        $cacheKey = "kpi_completion_rate_factory_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get total work orders for this factory in the date range (filter by created_at)
            $totalOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Get completed work orders for this factory in the date range
            // Count work orders created in the date range that have completed status
            $completedOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Closed'])
                ->count();

            // Get status distribution for work orders created in the date range
            $statusDistribution = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            // Calculate percentage for each status
            $statusPercentages = [];
            $statuses = ['Assigned', 'Start', 'Hold', 'Completed', 'Closed'];

            foreach ($statuses as $statusKey) {
                $count = $statusDistribution->get($statusKey)?->count ?? 0;
                $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
                $statusPercentages[$statusKey] = [
                    'count' => $count,
                    'percentage' => $percentage,
                ];
            }

            if ($totalOrders === 0) {
                return [
                    'rate' => 0,
                    'total_orders' => $totalOrders,
                    'completed_orders' => $completedOrders,
                    'trend' => 0,
                    'status' => 'neutral',
                    'status_distribution' => $statusPercentages,
                ];
            }

            $rate = round(($completedOrders / $totalOrders) * 100, 1);
            $status = $this->getStatus($rate, 85); // 85% target

            return [
                'rate' => $rate,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'trend' => 0, // Trend calculation disabled for custom date ranges
                'status' => $status,
                'status_distribution' => $statusPercentages,
            ];
        });
    }

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
                    'status' => 'neutral',
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
                'status' => $status,
            ];
        });
    }

    /**
     * Calculate Production Throughput for a specific factory using custom date range
     * Formula: Total Units Produced / Time Period (units per day)
     */
    public function getProductionThroughputWithDateRange($factoryId, $fromDate, $toDate)
    {
        $cacheKey = "kpi_production_throughput_factory_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();
            $days = $startDate->diffInDays($endDate) + 1; // Include both start and end dates

            // Get total units produced from completed work orders
            $totalUnitsProduced = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->whereNotNull('start_time')
                ->where('status', 'Completed')
                ->sum('ok_qtys');

            // Get production breakdown by BOM (part numbers)
            $productionByPart = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->whereNotNull('start_time')
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
                        $partNumber = $pn->partnumber.'_'.$pn->revision;
                    } elseif ($item->bom) {
                        $partNumber = $item->bom->unique_id;
                    }

                    return [
                        'part' => $partNumber,
                        'units' => (int) $item->total_units,
                        'percentage' => $totalUnitsProduced > 0 ? round(($item->total_units / $totalUnitsProduced) * 100, 1) : 0,
                        'orders' => $item->order_count,
                    ];
                })
                ->toArray();

            // Get production breakdown by machine
            $productionByMachine = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->whereNotNull('start_time')
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
                        'machine' => $item->machine ? $item->machine->name : 'Machine-'.$item->machine_id,
                        'units' => (int) $item->total_units,
                        'efficiency' => $efficiency,
                        'orders' => $item->order_count,
                        'status' => $this->getMachineStatus($efficiency),
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
                    'production_by_machine' => [],
                ];
            }

            $unitsPerDay = round($totalUnitsProduced / $days, 1);
            $status = $this->getProductionStatus($unitsPerDay);

            return [
                'throughput' => $unitsPerDay,
                'total_units' => $totalUnitsProduced,
                'units_per_day' => $unitsPerDay,
                'days' => $days,
                'trend' => 0, // Trend calculation disabled for custom date ranges
                'status' => $status,
                'production_by_part' => $productionByPart,
                'production_by_machine' => $productionByMachine,
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
                        $partNumber = $pn->partnumber.'_'.$pn->revision;
                    } elseif ($item->bom) {
                        $partNumber = $item->bom->unique_id;
                    }

                    return [
                        'part' => $partNumber,
                        'units' => (int) $item->total_units,
                        'percentage' => $totalUnitsProduced > 0 ? round(($item->total_units / $totalUnitsProduced) * 100, 1) : 0,
                        'orders' => $item->order_count,
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
                        'machine' => $item->machine ? $item->machine->name : 'Machine-'.$item->machine_id,
                        'units' => (int) $item->total_units,
                        'efficiency' => $efficiency,
                        'orders' => $item->order_count,
                        'status' => $this->getMachineStatus($efficiency),
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
                    'production_by_machine' => [],
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
                'production_by_machine' => $productionByMachine,
            ];
        });
    }

    /**
     * Calculate Scrap Rate for a specific factory using custom date range
     * Formula: (Total Scrapped Quantity / Total Quantity) × 100%
     * Only considers work orders that are Completed, Hold, or Closed
     */
    public function getScrapRateWithDateRange($factoryId, $fromDate, $toDate)
    {
        $cacheKey = "kpi_scrap_rate_factory_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get work orders for this factory in the date range that are completed, hold, or closed
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Hold', 'Closed'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_qty' => 0,
                    'scrapped_qty' => 0,
                    'good_qty' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                    'by_status' => [
                        'completed' => ['rate' => 0, 'total_qty' => 0, 'scrapped_qty' => 0, 'count' => 0],
                        'hold' => ['rate' => 0, 'total_qty' => 0, 'scrapped_qty' => 0, 'count' => 0],
                        'closed' => ['rate' => 0, 'total_qty' => 0, 'scrapped_qty' => 0, 'count' => 0],
                    ],
                ];
            }

            // Overall calculations
            $totalQty = $workOrders->sum('qty');
            $scrappedQty = $workOrders->sum('scrapped_qtys');
            $goodQty = $totalQty - $scrappedQty;

            // Calculate by status
            $byStatus = [];
            foreach (['Completed', 'Hold', 'Closed'] as $status) {
                $statusOrders = $workOrders->where('status', $status);
                $statusTotalQty = $statusOrders->sum('qty');
                $statusScrappedQty = $statusOrders->sum('scrapped_qtys');
                $statusCount = $statusOrders->count();

                $statusScrapRate = $statusTotalQty > 0 ? round(($statusScrappedQty / $statusTotalQty) * 100, 1) : 0;

                $byStatus[strtolower($status)] = [
                    'rate' => $statusScrapRate,
                    'total_qty' => $statusTotalQty,
                    'scrapped_qty' => $statusScrappedQty,
                    'count' => $statusCount,
                ];
            }

            if ($totalQty === 0) {
                return [
                    'rate' => 0,
                    'total_qty' => 0,
                    'scrapped_qty' => 0,
                    'good_qty' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                    'by_status' => $byStatus,
                ];
            }

            $scrapRate = round(($scrappedQty / $totalQty) * 100, 1);
            $status = $this->getScrapStatus($scrapRate);

            return [
                'rate' => $scrapRate,
                'total_qty' => $totalQty,
                'scrapped_qty' => $scrappedQty,
                'good_qty' => $goodQty,
                'trend' => 0, // Trend calculation disabled for custom date ranges
                'status' => $status,
                'by_status' => $byStatus,
            ];
        });
    }

    /**
     * Calculate Scrap Rate for a specific factory
     * Formula: (Total Scrapped Quantity / Total Quantity) × 100%
     * Only considers work orders that are Completed, Hold, or Closed
     */
    public function getScrapRate($factoryId, $period = '30d')
    {
        $cacheKey = "kpi_scrap_rate_factory_{$factoryId}_{$period}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $period) {
            $dateRange = $this->getDateRange($period);

            // Get work orders for this factory in the period that are completed, hold, or closed
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->whereIn('status', ['Completed', 'Hold', 'Closed'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_qty' => 0,
                    'scrapped_qty' => 0,
                    'good_qty' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                    'by_status' => [
                        'completed' => ['rate' => 0, 'total_qty' => 0, 'scrapped_qty' => 0, 'count' => 0],
                        'hold' => ['rate' => 0, 'total_qty' => 0, 'scrapped_qty' => 0, 'count' => 0],
                        'closed' => ['rate' => 0, 'total_qty' => 0, 'scrapped_qty' => 0, 'count' => 0],
                    ],
                ];
            }

            // Overall calculations
            $totalQty = $workOrders->sum('qty');
            $scrappedQty = $workOrders->sum('scrapped_qtys');
            $goodQty = $totalQty - $scrappedQty;

            // Calculate by status
            $byStatus = [];
            foreach (['Completed', 'Hold', 'Closed'] as $status) {
                $statusOrders = $workOrders->where('status', $status);
                $statusTotalQty = $statusOrders->sum('qty');
                $statusScrappedQty = $statusOrders->sum('scrapped_qtys');
                $statusCount = $statusOrders->count();

                $statusScrapRate = $statusTotalQty > 0 ? round(($statusScrappedQty / $statusTotalQty) * 100, 1) : 0;

                $byStatus[strtolower($status)] = [
                    'rate' => $statusScrapRate,
                    'total_qty' => $statusTotalQty,
                    'scrapped_qty' => $statusScrappedQty,
                    'count' => $statusCount,
                ];
            }

            if ($totalQty === 0) {
                return [
                    'rate' => 0,
                    'total_qty' => 0,
                    'scrapped_qty' => 0,
                    'good_qty' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                    'by_status' => $byStatus,
                ];
            }

            $scrapRate = round(($scrappedQty / $totalQty) * 100, 1);
            $trend = $this->calculateTrend('scrap_rate', $scrapRate, $factoryId, $period);
            $status = $this->getScrapStatus($scrapRate);

            return [
                'rate' => $scrapRate,
                'total_qty' => $totalQty,
                'scrapped_qty' => $scrappedQty,
                'good_qty' => $goodQty,
                'trend' => $trend,
                'status' => $status,
                'by_status' => $byStatus,
            ];
        });
    }

    /**
     * Get all KPIs for the executive dashboard using custom date range
     */
    public function getExecutiveKPIsWithDateRange($factoryId, $fromDate, $toDate)
    {
        return [
            'work_order_completion_rate' => $this->getWorkOrderCompletionRateWithDateRange($factoryId, $fromDate, $toDate),
            'work_order_scrapped_qty' => $this->getScrapRateWithDateRange($factoryId, $fromDate, $toDate),
            'production_throughput' => $this->getProductionThroughputWithDateRange($factoryId, $fromDate, $toDate),
            // Placeholder for future KPIs
            'scrap_rate' => $this->getPlaceholderKPI('Scrap Rate', '%'),
            'machine_utilization' => $this->getPlaceholderKPI('Machine Utilization', '%'),
            'quality_rate' => $this->getProductionThroughputByTimeWithDateRange($factoryId, $fromDate, $toDate),
            'on_time_delivery' => $this->getPlaceholderKPI('On-Time Delivery', '%'),
        ];
    }

    /**
     * Get all KPIs for the executive dashboard (legacy period-based method)
     */
    public function getExecutiveKPIs($factoryId, $period = '30d')
    {
        return [
            'work_order_completion_rate' => $this->getWorkOrderCompletionRate($factoryId, $period),
            'work_order_scrapped_qty' => $this->getScrapRate($factoryId, $period),
            'production_throughput' => $this->getProductionThroughput($factoryId, $period),
            // Placeholder for future KPIs
            'scrap_rate' => $this->getPlaceholderKPI('Scrap Rate', '%'),
            'machine_utilization' => $this->getPlaceholderKPI('Machine Utilization', '%'),
            'quality_rate' => $this->getProductionThroughputByTime($factoryId, $period),
            'on_time_delivery' => $this->getPlaceholderKPI('On-Time Delivery', '%'),
        ];
    }

    /**
     * Calculate Production Throughput using individual WO logic - same as ViewWorkOrder
     * Formula: Average of individual WO throughputs (units/hour per WO)
     * Uses custom date range and work orders with status 'Completed' or 'Closed'
     * Matches the exact logic from ViewWorkOrder.php
     */
    public function getProductionThroughputByTimeWithDateRange($factoryId, $fromDate, $toDate)
    {
        $cacheKey = "kpi_production_throughput_time_factory_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get work orders CREATED in the time range
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Closed', 'Hold']) // Include Hold status
                ->with(['workOrderLogs'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_units' => 0,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => 0,
                    'average_throughput' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $individualThroughputs = [];
            $totalUnits = 0;
            $totalHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                $endLog = null;

                // Determine end log based on work order status
                if ($workOrder->status === 'Hold') {
                    // For Hold status: get the LAST Hold log entry
                    $endLog = $workOrder->workOrderLogs()
                        ->where('status', 'Hold')
                        ->orderBy('created_at', 'desc')
                        ->first();
                } else {
                    // For Completed/Closed: get the latest Completed or Closed log
                    $endLog = $workOrder->workOrderLogs()
                        ->whereIn('status', ['Completed', 'Closed'])
                        ->orderBy('created_at', 'desc')
                        ->first();
                }

                if ($endLog) {
                    // Get the first Start log entry for this work order
                    $startLog = $workOrder->workOrderLogs()
                        ->where('status', 'Start')
                        ->orderBy('created_at', 'asc')
                        ->first();

                    // Only calculate throughput if Start log exists
                    if ($startLog) {
                        // Calculate time period (first Start log to end log)
                        $startedAt = Carbon::parse($startLog->created_at);
                        $endedAt = Carbon::parse($endLog->created_at);

                        // Handle edge cases where end time might be before start time
                        $hours = $startedAt->diffInHours($endedAt, false); // false = can be negative

                        // If negative hours, use absolute value (data inconsistency)
                        if ($hours <= 0) {
                            $hours = abs($hours);
                        }

                        // Get units produced from work_orders table
                        $units = $workOrder->ok_qtys ?? 0;

                        // Calculate throughput for this individual WO
                        if ($hours > 0 && $units > 0) {
                            $throughputPerHour = round($units / $hours, 3);
                            $individualThroughputs[] = $throughputPerHour;

                            // Also track totals for summary
                            $totalUnits += $units;
                            $totalHours += $hours;
                            $ordersProcessed++;
                        }
                    }
                }
            }

            if (empty($individualThroughputs)) {
                return [
                    'rate' => 0,
                    'total_units' => 0,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => 0,
                    'average_throughput' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            // Calculate average throughput across all WOs
            $averageThroughput = round(array_sum($individualThroughputs) / count($individualThroughputs), 3);
            $status = $this->getProductionThroughputStatus($averageThroughput);

            return [
                'rate' => $averageThroughput, // This is now the average of individual WO throughputs
                'total_units' => $totalUnits,
                'units_per_hour' => $averageThroughput,
                'total_hours' => round($totalHours, 1),
                'orders_count' => $ordersProcessed,
                'average_throughput' => $averageThroughput,
                'individual_throughputs_count' => count($individualThroughputs),
                'trend' => 0, // Trend calculation disabled for custom date ranges
                'status' => $status,
            ];
        });
    }

    /**
     * Calculate Production Throughput V2 - Excludes Hold Periods (Net Production Time)
     * Formula: Total Units / Net Production Time (sum of Start-to-Hold/Completed/Closed periods)
     * Uses custom date range and work orders with status 'Completed', 'Closed', or 'Hold'
     */
    public function getProductionThroughputByTimeWithDateRange_V2($factoryId, $fromDate, $toDate)
    {
        $cacheKey = "kpi_production_throughput_time_v2_factory_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get work orders CREATED in the time range
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Closed', 'Hold'])
                ->with(['workOrderLogs'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_units' => 0,
                    'units_per_hour' => 0,
                    'total_net_hours' => 0,
                    'orders_count' => 0,
                    'average_throughput' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $individualThroughputs = [];
            $totalUnits = 0;
            $totalNetHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                // Get all Start, Hold, Completed, and Closed logs in chronological order
                $logs = $workOrder->workOrderLogs()
                    ->whereIn('status', ['Start', 'Hold', 'Completed', 'Closed'])
                    ->orderBy('created_at', 'asc')
                    ->get();

                if ($logs->isEmpty()) {
                    continue;
                }

                // Calculate net production time (sum of all Start-to-Hold/Completed/Closed periods)
                $netProductionHours = 0;
                $lastStartTime = null;

                foreach ($logs as $log) {
                    if ($log->status === 'Start') {
                        // Mark the start of a production period
                        $lastStartTime = Carbon::parse($log->created_at);
                    } elseif (in_array($log->status, ['Hold', 'Completed', 'Closed']) && $lastStartTime !== null) {
                        // End of a production period - calculate duration
                        $endTime = Carbon::parse($log->created_at);
                        $periodHours = $lastStartTime->diffInHours($endTime, true);

                        // Add this production period to the total
                        $netProductionHours += $periodHours;

                        // Reset start time (production paused/ended)
                        $lastStartTime = null;
                    }
                }

                // Get units produced from work_orders table
                $units = $workOrder->ok_qtys ?? 0;

                // Calculate throughput for this individual WO
                if ($netProductionHours > 0 && $units > 0) {
                    $throughputPerHour = round($units / $netProductionHours, 3);
                    $individualThroughputs[] = $throughputPerHour;

                    // Also track totals for summary
                    $totalUnits += $units;
                    $totalNetHours += $netProductionHours;
                    $ordersProcessed++;
                }
            }

            if (empty($individualThroughputs)) {
                return [
                    'rate' => 0,
                    'total_units' => 0,
                    'units_per_hour' => 0,
                    'total_net_hours' => 0,
                    'orders_count' => 0,
                    'average_throughput' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            // Calculate average throughput across all WOs
            $averageThroughput = round(array_sum($individualThroughputs) / count($individualThroughputs), 3);
            $status = $this->getProductionThroughputStatus($averageThroughput);

            return [
                'rate' => $averageThroughput,
                'total_units' => $totalUnits,
                'units_per_hour' => $averageThroughput,
                'total_net_hours' => round($totalNetHours, 1),
                'orders_count' => $ordersProcessed,
                'average_throughput' => $averageThroughput,
                'individual_throughputs_count' => count($individualThroughputs),
                'trend' => 0,
                'status' => $status,
            ];
        });
    }

    /**
     * Calculate Production Throughput using time period from work order creation to completion
     * Formula: Total Units Produced / Total Time Period (from created_at to work_order_logs.updated_at)
     * Uses period and work orders with status 'Completed' or 'Closed'
     */
    public function getProductionThroughputByTime($factoryId, $period = '30d')
    {
        $cacheKey = "kpi_production_throughput_time_factory_{$factoryId}_{$period}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $period) {
            $dateRange = $this->getDateRange($period);

            // Get completed/closed/hold work orders with their logs
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->whereIn('status', ['Completed', 'Closed', 'Hold']) // Include Hold status
                ->with(['workOrderLogs'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_units' => 0,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $totalUnits = 0;
            $totalHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                $endLog = null;

                // Determine end log based on work order status
                if ($workOrder->status === 'Hold') {
                    // For Hold status: get the LAST Hold log entry
                    $endLog = $workOrder->workOrderLogs()
                        ->where('status', 'Hold')
                        ->orderBy('created_at', 'desc')
                        ->first();
                } else {
                    // For Completed/Closed: get the latest Completed or Closed log
                    $endLog = $workOrder->workOrderLogs()
                        ->whereIn('status', ['Completed', 'Closed'])
                        ->orderBy('created_at', 'desc')
                        ->first();
                }

                if ($endLog) {
                    // Get the first Start log entry for this work order
                    $startLog = $workOrder->workOrderLogs()
                        ->where('status', 'Start')
                        ->orderBy('created_at', 'asc')
                        ->first();

                    // Only calculate throughput if Start log exists
                    if ($startLog) {
                        $startedAt = Carbon::parse($startLog->created_at);
                        $endedAt = Carbon::parse($endLog->created_at);

                        // Calculate hours between production start and end
                        $hours = $startedAt->diffInHours($endedAt);
                        if ($hours > 0) {
                            $totalUnits += $workOrder->ok_qtys ?? 0;
                            $totalHours += $hours;
                            $ordersProcessed++;
                        }
                    }
                }
            }

            if ($totalHours === 0) {
                return [
                    'rate' => 0,
                    'total_units' => $totalUnits,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => $ordersProcessed,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $unitsPerHour = round($totalUnits / $totalHours, 2);
            $trend = $this->calculateTrend('production_throughput_time', $unitsPerHour, $factoryId, $period);
            $status = $this->getProductionThroughputStatus($unitsPerHour);

            return [
                'rate' => $unitsPerHour,
                'total_units' => $totalUnits,
                'units_per_hour' => $unitsPerHour,
                'total_hours' => round($totalHours, 1),
                'orders_count' => $ordersProcessed,
                'trend' => $trend,
                'status' => $status,
            ];
        });
    }

    /**
     * Calculate Production Throughput V2 - Excludes Hold Periods (Net Production Time)
     * Formula: Total Units / Net Production Time (sum of Start-to-Hold/Completed/Closed periods)
     * Uses period and work orders with status 'Completed', 'Closed', or 'Hold'
     */
    public function getProductionThroughputByTime_V2($factoryId, $period = '30d')
    {
        $cacheKey = "kpi_production_throughput_time_v2_factory_{$factoryId}_{$period}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $period) {
            $dateRange = $this->getDateRange($period);

            // Get completed/closed/hold work orders with their logs
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->whereIn('status', ['Completed', 'Closed', 'Hold'])
                ->with(['workOrderLogs'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_units' => 0,
                    'units_per_hour' => 0,
                    'total_net_hours' => 0,
                    'orders_count' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $totalUnits = 0;
            $totalNetHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                // Get all Start, Hold, Completed, and Closed logs in chronological order
                $logs = $workOrder->workOrderLogs()
                    ->whereIn('status', ['Start', 'Hold', 'Completed', 'Closed'])
                    ->orderBy('created_at', 'asc')
                    ->get();

                if ($logs->isEmpty()) {
                    continue;
                }

                // Calculate net production time (sum of all Start-to-Hold/Completed/Closed periods)
                $netProductionHours = 0;
                $lastStartTime = null;

                foreach ($logs as $log) {
                    if ($log->status === 'Start') {
                        // Mark the start of a production period
                        $lastStartTime = Carbon::parse($log->created_at);
                    } elseif (in_array($log->status, ['Hold', 'Completed', 'Closed']) && $lastStartTime !== null) {
                        // End of a production period - calculate duration
                        $endTime = Carbon::parse($log->created_at);
                        $periodHours = $lastStartTime->diffInHours($endTime, true);

                        // Add this production period to the total
                        $netProductionHours += $periodHours;

                        // Reset start time (production paused/ended)
                        $lastStartTime = null;
                    }
                }

                // Get units produced from work_orders table
                $units = $workOrder->ok_qtys ?? 0;

                // Calculate throughput if we have valid data
                if ($netProductionHours > 0 && $units > 0) {
                    $totalUnits += $units;
                    $totalNetHours += $netProductionHours;
                    $ordersProcessed++;
                }
            }

            if ($totalNetHours === 0) {
                return [
                    'rate' => 0,
                    'total_units' => $totalUnits,
                    'units_per_hour' => 0,
                    'total_net_hours' => 0,
                    'orders_count' => $ordersProcessed,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $unitsPerHour = round($totalUnits / $totalNetHours, 2);
            $trend = $this->calculateTrend('production_throughput_time_v2', $unitsPerHour, $factoryId, $period);
            $status = $this->getProductionThroughputStatus($unitsPerHour);

            return [
                'rate' => $unitsPerHour,
                'total_units' => $totalUnits,
                'units_per_hour' => $unitsPerHour,
                'total_net_hours' => round($totalNetHours, 1),
                'orders_count' => $ordersProcessed,
                'trend' => $trend,
                'status' => $status,
            ];
        });
    }

    /**
     * Calculate Gross Production Throughput (Raw Output-Oriented) 1
     * Formula: Total Gross Units (OK + Scrapped) / Time Period (First Start to End)
     * Uses custom date range and work orders with status 'Completed', 'Closed', or 'Hold'
     */
    public function getGrossProductionThroughputByTimeWithDateRange($factoryId, $fromDate, $toDate)
    {
        $cacheKey = "kpi_gross_production_throughput_factory_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get work orders CREATED in the time range
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Closed', 'Hold'])
                ->with(['workOrderLogs'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_gross_units' => 0,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => 0,
                    'average_throughput' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $individualThroughputs = [];
            $totalGrossUnits = 0;
            $totalHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                $endLog = null;

                // Determine end log based on work order status
                if ($workOrder->status === 'Hold') {
                    // For Hold status: get the LAST Hold log entry
                    $endLog = $workOrder->workOrderLogs()
                        ->where('status', 'Hold')
                        ->orderBy('created_at', 'desc')
                        ->first();
                } else {
                    // For Completed/Closed: get the latest Completed or Closed log
                    $endLog = $workOrder->workOrderLogs()
                        ->whereIn('status', ['Completed', 'Closed'])
                        ->orderBy('created_at', 'desc')
                        ->first();
                }

                if ($endLog) {
                    // Get the first Start log entry for this work order
                    $startLog = $workOrder->workOrderLogs()
                        ->where('status', 'Start')
                        ->orderBy('created_at', 'asc')
                        ->first();

                    // Only calculate throughput if Start log exists
                    if ($startLog) {
                        // Calculate time period (first Start log to end log)
                        $startedAt = Carbon::parse($startLog->created_at);
                        $endedAt = Carbon::parse($endLog->created_at);

                        // Handle edge cases where end time might be before start time
                        $hours = $startedAt->diffInHours($endedAt, true);

                        // If negative hours, use absolute value (data inconsistency)
                        if ($hours <= 0) {
                            $hours = abs($hours);
                        }

                        // Get GROSS units (OK + Scrapped) from work_orders table
                        $grossUnits = ($workOrder->ok_qtys ?? 0) + ($workOrder->scrapped_qtys ?? 0);

                        // Calculate throughput for this individual WO
                        if ($hours > 0 && $grossUnits > 0) {
                            $throughputPerHour = round($grossUnits / $hours, 3);
                            $individualThroughputs[] = $throughputPerHour;

                            // Also track totals for summary
                            $totalGrossUnits += $grossUnits;
                            $totalHours += $hours;
                            $ordersProcessed++;
                        }
                    }
                }
            }

            if (empty($individualThroughputs)) {
                return [
                    'rate' => 0,
                    'total_gross_units' => 0,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => 0,
                    'average_throughput' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            // Calculate average gross throughput across all WOs
            $averageGrossThroughput = round(array_sum($individualThroughputs) / count($individualThroughputs), 3);
            $status = $this->getProductionThroughputStatus($averageGrossThroughput);

            return [
                'rate' => $averageGrossThroughput,
                'total_gross_units' => $totalGrossUnits,
                'units_per_hour' => $averageGrossThroughput,
                'total_hours' => round($totalHours, 1),
                'orders_count' => $ordersProcessed,
                'average_throughput' => $averageGrossThroughput,
                'individual_throughputs_count' => count($individualThroughputs),
                'trend' => 0,
                'status' => $status,
            ];
        });
    }

    /**
     * Calculate Gross Production Throughput (Raw Output-Oriented) 1
     * Formula: Total Gross Units (OK + Scrapped) / Time Period (First Start to End)
     * Uses period and work orders with status 'Completed', 'Closed', or 'Hold'
     */
    public function getGrossProductionThroughputByTime($factoryId, $period = '30d')
    {
        $cacheKey = "kpi_gross_production_throughput_factory_{$factoryId}_{$period}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $period) {
            $dateRange = $this->getDateRange($period);

            // Get completed/closed/hold work orders with their logs
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->whereIn('status', ['Completed', 'Closed', 'Hold'])
                ->with(['workOrderLogs'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_gross_units' => 0,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $totalGrossUnits = 0;
            $totalHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                $endLog = null;

                // Determine end log based on work order status
                if ($workOrder->status === 'Hold') {
                    // For Hold status: get the LAST Hold log entry
                    $endLog = $workOrder->workOrderLogs()
                        ->where('status', 'Hold')
                        ->orderBy('created_at', 'desc')
                        ->first();
                } else {
                    // For Completed/Closed: get the latest Completed or Closed log
                    $endLog = $workOrder->workOrderLogs()
                        ->whereIn('status', ['Completed', 'Closed'])
                        ->orderBy('created_at', 'desc')
                        ->first();
                }

                if ($endLog) {
                    // Get the first Start log entry for this work order
                    $startLog = $workOrder->workOrderLogs()
                        ->where('status', 'Start')
                        ->orderBy('created_at', 'asc')
                        ->first();

                    // Only calculate throughput if Start log exists
                    if ($startLog) {
                        $startedAt = Carbon::parse($startLog->created_at);
                        $endedAt = Carbon::parse($endLog->created_at);

                        // Calculate hours between production start and end
                        $hours = $startedAt->diffInHours($endedAt);
                        if ($hours > 0) {
                            // Get GROSS units (OK + Scrapped) from work_orders table
                            $grossUnits = ($workOrder->ok_qtys ?? 0) + ($workOrder->scrapped_qtys ?? 0);

                            $totalGrossUnits += $grossUnits;
                            $totalHours += $hours;
                            $ordersProcessed++;
                        }
                    }
                }
            }

            if ($totalHours === 0) {
                return [
                    'rate' => 0,
                    'total_gross_units' => $totalGrossUnits,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => $ordersProcessed,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $unitsPerHour = round($totalGrossUnits / $totalHours, 2);
            $trend = $this->calculateTrend('gross_production_throughput', $unitsPerHour, $factoryId, $period);
            $status = $this->getProductionThroughputStatus($unitsPerHour);

            return [
                'rate' => $unitsPerHour,
                'total_gross_units' => $totalGrossUnits,
                'units_per_hour' => $unitsPerHour,
                'total_hours' => round($totalHours, 1),
                'orders_count' => $ordersProcessed,
                'trend' => $trend,
                'status' => $status,
            ];
        });
    }

    /**
     * Calculate Gross Production Throughput V2 - Excludes Hold Periods (Net Production Time)
     * Formula: Total Gross Units (OK + Scrapped) / Net Production Time (sum of Start-to-Hold/Completed/Closed periods)
     * Uses custom date range and work orders with status 'Completed', 'Closed', or 'Hold'
     */
    public function getGrossProductionThroughputByTimeWithDateRange_V2($factoryId, $fromDate, $toDate)
    {
        $cacheKey = "kpi_gross_production_throughput_v2_factory_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get work orders CREATED in the time range
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Closed', 'Hold'])
                ->with(['workOrderLogs'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_gross_units' => 0,
                    'units_per_hour' => 0,
                    'total_net_hours' => 0,
                    'orders_count' => 0,
                    'average_throughput' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $individualThroughputs = [];
            $totalGrossUnits = 0;
            $totalNetHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                // Get all Start, Hold, Completed, and Closed logs in chronological order
                $logs = $workOrder->workOrderLogs()
                    ->whereIn('status', ['Start', 'Hold', 'Completed', 'Closed'])
                    ->orderBy('created_at', 'asc')
                    ->get();

                if ($logs->isEmpty()) {
                    continue;
                }

                // Calculate net production time (sum of all Start-to-Hold/Completed/Closed periods)
                $netProductionHours = 0;
                $lastStartTime = null;

                foreach ($logs as $log) {
                    if ($log->status === 'Start') {
                        // Mark the start of a production period
                        $lastStartTime = Carbon::parse($log->created_at);
                    } elseif (in_array($log->status, ['Hold', 'Completed', 'Closed']) && $lastStartTime !== null) {
                        // End of a production period - calculate duration
                        $endTime = Carbon::parse($log->created_at);
                        $periodHours = $lastStartTime->diffInHours($endTime, true);

                        // Add this production period to the total
                        $netProductionHours += $periodHours;

                        // Reset start time (production paused/ended)
                        $lastStartTime = null;
                    }
                }

                // Get GROSS units (OK + Scrapped) from work_orders table
                $grossUnits = ($workOrder->ok_qtys ?? 0) + ($workOrder->scrapped_qtys ?? 0);

                // Calculate throughput for this individual WO
                if ($netProductionHours > 0 && $grossUnits > 0) {
                    $throughputPerHour = round($grossUnits / $netProductionHours, 3);
                    $individualThroughputs[] = $throughputPerHour;

                    // Also track totals for summary
                    $totalGrossUnits += $grossUnits;
                    $totalNetHours += $netProductionHours;
                    $ordersProcessed++;
                }
            }

            if (empty($individualThroughputs)) {
                return [
                    'rate' => 0,
                    'total_gross_units' => 0,
                    'units_per_hour' => 0,
                    'total_net_hours' => 0,
                    'orders_count' => 0,
                    'average_throughput' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            // Calculate average throughput across all WOs
            $averageThroughput = round(array_sum($individualThroughputs) / count($individualThroughputs), 3);
            $status = $this->getProductionThroughputStatus($averageThroughput);

            return [
                'rate' => $averageThroughput,
                'total_gross_units' => $totalGrossUnits,
                'units_per_hour' => $averageThroughput,
                'total_net_hours' => round($totalNetHours, 1),
                'orders_count' => $ordersProcessed,
                'average_throughput' => $averageThroughput,
                'individual_throughputs_count' => count($individualThroughputs),
                'trend' => 0,
                'status' => $status,
            ];
        });
    }

    /**
     * Calculate Gross Production Throughput V2 - Excludes Hold Periods (Net Production Time)
     * Formula: Total Gross Units (OK + Scrapped) / Net Production Time (sum of Start-to-Hold/Completed/Closed periods)
     * Uses period and work orders with status 'Completed', 'Closed', or 'Hold'
     */
    public function getGrossProductionThroughputByTime_V2($factoryId, $period = '30d')
    {
        $cacheKey = "kpi_gross_production_throughput_v2_factory_{$factoryId}_{$period}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $period) {
            $dateRange = $this->getDateRange($period);

            // Get completed/closed/hold work orders with their logs
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->whereIn('status', ['Completed', 'Closed', 'Hold'])
                ->with(['workOrderLogs'])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_gross_units' => 0,
                    'units_per_hour' => 0,
                    'total_net_hours' => 0,
                    'orders_count' => 0,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $totalGrossUnits = 0;
            $totalNetHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                // Get all Start, Hold, Completed, and Closed logs in chronological order
                $logs = $workOrder->workOrderLogs()
                    ->whereIn('status', ['Start', 'Hold', 'Completed', 'Closed'])
                    ->orderBy('created_at', 'asc')
                    ->get();

                if ($logs->isEmpty()) {
                    continue;
                }

                // Calculate net production time (sum of all Start-to-Hold/Completed/Closed periods)
                $netProductionHours = 0;
                $lastStartTime = null;

                foreach ($logs as $log) {
                    if ($log->status === 'Start') {
                        // Mark the start of a production period
                        $lastStartTime = Carbon::parse($log->created_at);
                    } elseif (in_array($log->status, ['Hold', 'Completed', 'Closed']) && $lastStartTime !== null) {
                        // End of a production period - calculate duration
                        $endTime = Carbon::parse($log->created_at);
                        $periodHours = $lastStartTime->diffInHours($endTime, true);

                        // Add this production period to the total
                        $netProductionHours += $periodHours;

                        // Reset start time (production paused/ended)
                        $lastStartTime = null;
                    }
                }

                // Get GROSS units (OK + Scrapped) from work_orders table
                $grossUnits = ($workOrder->ok_qtys ?? 0) + ($workOrder->scrapped_qtys ?? 0);

                // Calculate throughput if we have valid data
                if ($netProductionHours > 0 && $grossUnits > 0) {
                    $totalGrossUnits += $grossUnits;
                    $totalNetHours += $netProductionHours;
                    $ordersProcessed++;
                }
            }

            if ($totalNetHours === 0) {
                return [
                    'rate' => 0,
                    'total_gross_units' => $totalGrossUnits,
                    'units_per_hour' => 0,
                    'total_net_hours' => 0,
                    'orders_count' => $ordersProcessed,
                    'trend' => 0,
                    'status' => 'neutral',
                ];
            }

            $unitsPerHour = round($totalGrossUnits / $totalNetHours, 2);
            $trend = $this->calculateTrend('gross_production_throughput_v2', $unitsPerHour, $factoryId, $period);
            $status = $this->getProductionThroughputStatus($unitsPerHour);

            return [
                'rate' => $unitsPerHour,
                'total_gross_units' => $totalGrossUnits,
                'units_per_hour' => $unitsPerHour,
                'total_net_hours' => round($totalNetHours, 1),
                'orders_count' => $ordersProcessed,
                'trend' => $trend,
                'status' => $status,
            ];
        });
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
            'coming_soon' => true,
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
     * Get production throughput status based on units per hour
     */
    private function getProductionThroughputStatus($unitsPerHour)
    {
        // Define thresholds based on production targets (units per hour)
        if ($unitsPerHour >= 42) { // ~1000 units/day / 24 hours
            return 'excellent'; // Green
        } elseif ($unitsPerHour >= 31) { // ~750 units/day / 24 hours
            return 'good'; // Light green
        } elseif ($unitsPerHour >= 21) { // ~500 units/day / 24 hours
            return 'warning'; // Yellow
        } else {
            return 'critical'; // Red
        }
    }

    /**
     * Get scrap status based on scrap rate percentage
     * Lower scrap rate is better
     */
    private function getScrapStatus($scrapRate)
    {
        if ($scrapRate <= 2) {
            return 'excellent'; // Green - Very low scrap rate
        } elseif ($scrapRate <= 5) {
            return 'good'; // Light green - Acceptable scrap rate
        } elseif ($scrapRate <= 10) {
            return 'warning'; // Yellow - High scrap rate, needs attention
        } else {
            return 'critical'; // Red - Very high scrap rate, critical issue
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
                } elseif ($kpiType === 'production_throughput_time') {
                    $dateRange = $this->getDateRange($previousPeriod);

                    // Get completed/closed/hold work orders for previous period
                    $workOrders = WorkOrder::where('factory_id', $factoryId)
                        ->whereBetween('created_at', $dateRange)
                        ->whereIn('status', ['Completed', 'Closed', 'Hold']) // Include Hold status
                        ->with(['workOrderLogs'])
                        ->get();

                    if ($workOrders->isEmpty()) {
                        return 0;
                    }

                    $totalUnits = 0;
                    $totalHours = 0;

                    foreach ($workOrders as $workOrder) {
                        $endLog = null;

                        // Determine end log based on work order status
                        if ($workOrder->status === 'Hold') {
                            // For Hold status: get the LAST Hold log entry
                            $endLog = $workOrder->workOrderLogs()
                                ->where('status', 'Hold')
                                ->orderBy('created_at', 'desc')
                                ->first();
                        } else {
                            // For Completed/Closed: get the latest Completed or Closed log
                            $endLog = $workOrder->workOrderLogs()
                                ->whereIn('status', ['Completed', 'Closed'])
                                ->orderBy('created_at', 'desc')
                                ->first();
                        }

                        if ($endLog) {
                            // Get the first Start log entry for this work order
                            $startLog = $workOrder->workOrderLogs()
                                ->where('status', 'Start')
                                ->orderBy('created_at', 'asc')
                                ->first();

                            // Only calculate if Start log exists
                            if ($startLog) {
                                $startedAt = Carbon::parse($startLog->created_at);
                                $endedAt = Carbon::parse($endLog->created_at);

                                $hours = $startedAt->diffInHours($endedAt);
                                if ($hours > 0) {
                                    $totalUnits += $workOrder->ok_qtys ?? 0;
                                    $totalHours += $hours;
                                }
                            }
                        }
                    }

                    if ($totalHours === 0) {
                        return 0;
                    }

                    $previousValue = $totalUnits / $totalHours;

                    return round($currentValue - $previousValue, 1);
                } elseif ($kpiType === 'scrap_rate') {
                    $dateRange = $this->getDateRange($previousPeriod);

                    $workOrders = WorkOrder::where('factory_id', $factoryId)
                        ->whereBetween('created_at', $dateRange)
                        ->whereIn('status', ['Completed', 'Hold', 'Closed'])
                        ->get();

                    if ($workOrders->isEmpty()) {
                        return 0;
                    }

                    $totalQty = $workOrders->sum('qty');
                    $scrappedQty = $workOrders->sum('scrapped_qtys');

                    if ($totalQty === 0) {
                        return 0;
                    }

                    $previousValue = ($scrappedQty / $totalQty) * 100;

                    return round($currentValue - $previousValue, 1);
                }

                return 0;
            });
        } catch (Exception $e) {
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
            "kpi_scrap_rate_factory_{$factoryId}_*",
            "kpi_*_trend_factory_{$factoryId}_*",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
