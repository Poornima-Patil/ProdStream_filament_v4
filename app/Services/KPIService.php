<?php

namespace App\Services;

use Exception;
use App\Models\WorkOrder;
use Carbon\Carbon;
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
                    'percentage' => $percentage
                ];
            }

            if ($totalOrders === 0) {
                return [
                    'rate' => 0,
                    'total_orders' => $totalOrders,
                    'completed_orders' => $completedOrders,
                    'trend' => 0,
                    'status' => 'neutral',
                    'status_distribution' => $statusPercentages
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
                'status_distribution' => $statusPercentages
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
            $status = $this->getProductionStatus($unitsPerDay);

            return [
                'throughput' => $unitsPerDay,
                'total_units' => $totalUnitsProduced,
                'units_per_day' => $unitsPerDay,
                'days' => $days,
                'trend' => 0, // Trend calculation disabled for custom date ranges
                'status' => $status,
                'production_by_part' => $productionByPart,
                'production_by_machine' => $productionByMachine
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
                ->whereBetween('start_time', [$startDate, $endDate])
                ->whereNotNull('start_time')
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
                        'closed' => ['rate' => 0, 'total_qty' => 0, 'scrapped_qty' => 0, 'count' => 0]
                    ]
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
                    'count' => $statusCount
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
                    'by_status' => $byStatus
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
                'by_status' => $byStatus
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
                        'closed' => ['rate' => 0, 'total_qty' => 0, 'scrapped_qty' => 0, 'count' => 0]
                    ]
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
                    'count' => $statusCount
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
                    'by_status' => $byStatus
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
                'by_status' => $byStatus
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
     * Calculate Production Throughput using time period from work order creation to completion
     * Formula: Total Units Produced / Total Time Period (from created_at to work_order_logs.updated_at)
     * Uses custom date range and work orders with status 'Completed' or 'Closed'
     */
    public function getProductionThroughputByTimeWithDateRange($factoryId, $fromDate, $toDate)
    {
        $cacheKey = "kpi_production_throughput_time_factory_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get completed/closed work orders with their completion logs
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Closed'])
                ->with(['workOrderLogs' => function($query) {
                    $query->whereIn('status', ['Completed', 'Closed'])
                          ->orderBy('updated_at', 'desc')
                          ->limit(1);
                }])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_units' => 0,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => 0,
                    'trend' => 0,
                    'status' => 'neutral'
                ];
            }

            $totalUnits = 0;
            $totalHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                if ($workOrder->workOrderLogs->isNotEmpty()) {
                    $log = $workOrder->workOrderLogs->first();
                    $createdAt = Carbon::parse($workOrder->created_at);
                    $completedAt = Carbon::parse($log->updated_at);
                    
                    // Calculate hours between creation and completion
                    $hours = $createdAt->diffInHours($completedAt);
                    if ($hours > 0) {
                        $totalUnits += $workOrder->ok_qtys ?? 0;
                        $totalHours += $hours;
                        $ordersProcessed++;
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
                    'status' => 'neutral'
                ];
            }

            $unitsPerHour = round($totalUnits / $totalHours, 2);
            $status = $this->getProductionThroughputStatus($unitsPerHour);

            return [
                'rate' => $unitsPerHour,
                'total_units' => $totalUnits,
                'units_per_hour' => $unitsPerHour,
                'total_hours' => round($totalHours, 1),
                'orders_count' => $ordersProcessed,
                'trend' => 0, // Trend calculation disabled for custom date ranges
                'status' => $status
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

            // Get completed/closed work orders with their completion logs
            $workOrders = WorkOrder::where('factory_id', $factoryId)
                ->whereBetween('created_at', $dateRange)
                ->whereIn('status', ['Completed', 'Closed'])
                ->with(['workOrderLogs' => function($query) {
                    $query->whereIn('status', ['Completed', 'Closed'])
                          ->orderBy('updated_at', 'desc')
                          ->limit(1);
                }])
                ->get();

            if ($workOrders->isEmpty()) {
                return [
                    'rate' => 0,
                    'total_units' => 0,
                    'units_per_hour' => 0,
                    'total_hours' => 0,
                    'orders_count' => 0,
                    'trend' => 0,
                    'status' => 'neutral'
                ];
            }

            $totalUnits = 0;
            $totalHours = 0;
            $ordersProcessed = 0;

            foreach ($workOrders as $workOrder) {
                if ($workOrder->workOrderLogs->isNotEmpty()) {
                    $log = $workOrder->workOrderLogs->first();
                    $createdAt = Carbon::parse($workOrder->created_at);
                    $completedAt = Carbon::parse($log->updated_at);
                    
                    // Calculate hours between creation and completion
                    $hours = $createdAt->diffInHours($completedAt);
                    if ($hours > 0) {
                        $totalUnits += $workOrder->ok_qtys ?? 0;
                        $totalHours += $hours;
                        $ordersProcessed++;
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
                    'status' => 'neutral'
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
                'status' => $status
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

                    // Get completed/closed work orders with their completion logs for previous period
                    $workOrders = WorkOrder::where('factory_id', $factoryId)
                        ->whereBetween('created_at', $dateRange)
                        ->whereIn('status', ['Completed', 'Closed'])
                        ->with(['workOrderLogs' => function($query) {
                            $query->whereIn('status', ['Completed', 'Closed'])
                                  ->orderBy('updated_at', 'desc')
                                  ->limit(1);
                        }])
                        ->get();

                    if ($workOrders->isEmpty()) {
                        return 0;
                    }

                    $totalUnits = 0;
                    $totalHours = 0;

                    foreach ($workOrders as $workOrder) {
                        if ($workOrder->workOrderLogs->isNotEmpty()) {
                            $log = $workOrder->workOrderLogs->first();
                            $createdAt = Carbon::parse($workOrder->created_at);
                            $completedAt = Carbon::parse($log->updated_at);
                            
                            $hours = $createdAt->diffInHours($completedAt);
                            if ($hours > 0) {
                                $totalUnits += $workOrder->ok_qtys ?? 0;
                                $totalHours += $hours;
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
            "kpi_*_trend_factory_{$factoryId}_*"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
