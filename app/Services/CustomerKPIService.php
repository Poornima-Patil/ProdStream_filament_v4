<?php

namespace App\Services;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class CustomerKPIService
{
    /**
     * Get work order status distribution for a customer within date range
     */
    public function getCustomerWorkOrderStatusDistribution($customerId, $factoryId, $fromDate, $toDate)
    {
        $cacheKey = "customer_kpi_status_distribution_{$customerId}_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($customerId, $factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get work orders for this customer through the relationship chain
            $statusDistribution = WorkOrder::whereHas('bom.purchaseOrder', function ($query) use ($customerId) {
                $query->where('cust_id', $customerId);
            })
                ->where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            return $statusDistribution;
        });
    }

    /**
     * Get quality data for a customer within date range
     * Only considers completed and closed orders
     */
    public function getCustomerQualityData($customerId, $factoryId, $fromDate, $toDate)
    {
        $cacheKey = "customer_kpi_quality_data_{$customerId}_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($customerId, $factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get quality metrics for completed/closed work orders for this customer
            $qualityData = WorkOrder::whereHas('bom.purchaseOrder', function ($query) use ($customerId) {
                $query->where('cust_id', $customerId);
            })
                ->where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Closed'])
                ->selectRaw('
                    SUM(qty) as total_produced,
                    SUM(ok_qtys) as total_ok_qtys,
                    SUM(scrapped_qtys) as total_scrapped_qtys
                ')
                ->first();

            return $qualityData;
        });
    }

    /**
     * Get customer work order analytics summary
     */
    public function getCustomerWorkOrderAnalytics($customerId, $factoryId, $fromDate, $toDate)
    {
        $cacheKey = "customer_kpi_analytics_{$customerId}_{$factoryId}_{$fromDate}_{$toDate}";

        return Cache::remember($cacheKey, 300, function () use ($customerId, $factoryId, $fromDate, $toDate) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get total work orders for this customer
            $totalOrders = WorkOrder::whereHas('bom.purchaseOrder', function ($query) use ($customerId) {
                $query->where('cust_id', $customerId);
            })
                ->where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Get completed work orders
            $completedOrders = WorkOrder::whereHas('bom.purchaseOrder', function ($query) use ($customerId) {
                $query->where('cust_id', $customerId);
            })
                ->where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Closed'])
                ->count();

            // Calculate completion rate
            $completionRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0;

            return [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'completion_rate' => $completionRate,
            ];
        });
    }

    /**
     * Get top parts produced for a customer within date range
     */
    public function getCustomerTopParts($customerId, $factoryId, $fromDate, $toDate, $limit = 5)
    {
        $cacheKey = "customer_kpi_top_parts_{$customerId}_{$factoryId}_{$fromDate}_{$toDate}_{$limit}";

        return Cache::remember($cacheKey, 300, function () use ($customerId, $factoryId, $fromDate, $toDate, $limit) {
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();

            // Get top parts produced for this customer
            $topParts = WorkOrder::whereHas('bom.purchaseOrder', function ($query) use ($customerId) {
                $query->where('cust_id', $customerId);
            })
                ->where('factory_id', $factoryId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['Completed', 'Closed'])
                ->with(['bom.purchaseOrder.partNumber'])
                ->selectRaw('bom_id, SUM(ok_qtys) as total_units, COUNT(*) as order_count')
                ->groupBy('bom_id')
                ->orderBy('total_units', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
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
                        'orders' => $item->order_count,
                    ];
                });

            return $topParts;
        });
    }

    /**
     * Clear KPI cache for a specific customer
     */
    public function clearCustomerKPICache($customerId, $factoryId)
    {
        $patterns = [
            "customer_kpi_status_distribution_{$customerId}_{$factoryId}_*",
            "customer_kpi_quality_data_{$customerId}_{$factoryId}_*",
            "customer_kpi_analytics_{$customerId}_{$factoryId}_*",
            "customer_kpi_top_parts_{$customerId}_{$factoryId}_*",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
