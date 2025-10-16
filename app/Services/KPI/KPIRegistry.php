<?php

namespace App\Services\KPI;

class KPIRegistry
{
    /**
     * Get all KPI categories with their metadata
     */
    public static function getCategories(): array
    {
        return [
            'operational' => [
                'name' => 'Operational KPIs',
                'description' => 'Real-time operational metrics including machine status, work orders, and production flow',
                'icon' => 'heroicon-o-cog-6-tooth',
                'color' => 'primary',
                'kpi_count' => 15,
            ],
            'quality' => [
                'name' => 'Quality KPIs',
                'description' => 'Quality control metrics, defect rates, and first pass yield analysis',
                'icon' => 'heroicon-o-shield-check',
                'color' => 'success',
                'kpi_count' => 12,
            ],
            'production' => [
                'name' => 'Production KPIs',
                'description' => 'Production throughput, OEE, downtime analysis, and efficiency metrics',
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'info',
                'kpi_count' => 18,
            ],
            'workforce' => [
                'name' => 'Workforce KPIs',
                'description' => 'Operator performance, labor efficiency, and workforce utilization',
                'icon' => 'heroicon-o-user-group',
                'color' => 'warning',
                'kpi_count' => 10,
            ],
            'inventory' => [
                'name' => 'Inventory KPIs',
                'description' => 'Material usage, stock levels, WIP tracking, and inventory turnover',
                'icon' => 'heroicon-o-cube',
                'color' => 'danger',
                'kpi_count' => 14,
            ],
            'financial' => [
                'name' => 'Financial KPIs',
                'description' => 'Production costs, revenue per unit, cost analysis, and ROI metrics',
                'icon' => 'heroicon-o-currency-dollar',
                'color' => 'gray',
                'kpi_count' => 8,
            ],
        ];
    }

    /**
     * Get all KPIs organized by category
     */
    public static function getAllKPIs(): array
    {
        return [
            'operational' => [
                [
                    'id' => 'machine_status',
                    'name' => 'Machine Status',
                    'description' => 'Real-time machine status dashboard showing running, hold, scheduled, and idle machines',
                    'tier' => 1,
                    'status' => 'active',
                    'icon' => 'heroicon-o-cpu-chip',
                    'route' => 'filament.admin.pages.kpi.machine-status',
                ],
                [
                    'id' => 'work_order_status',
                    'name' => 'Work Order Status',
                    'description' => 'Current work order status distribution across all statuses (Hold, Start, Assigned, Completed, Closed)',
                    'tier' => 1,
                    'status' => 'active',
                    'icon' => 'heroicon-o-clipboard-document-list',
                ],
                [
                    'id' => 'production_schedule',
                    'name' => 'Production Schedule Adherence',
                    'description' => 'Track schedule compliance and on-time production',
                    'tier' => 1,
                    'status' => 'active',
                    'icon' => 'heroicon-o-calendar',
                    'route' => 'filament.admin.pages.production-schedule',
                ],
                [
                    'id' => 'machine_utilization',
                    'name' => 'Machine Utilization Rate',
                    'description' => 'Percentage of time machines are actively producing',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-chart-pie',
                ],
                [
                    'id' => 'setup_time',
                    'name' => 'Setup Time per Machine',
                    'description' => 'Average time taken for machine setup and changeover',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-wrench',
                ],
                // Add more operational KPIs here...
            ],
            'quality' => [
                [
                    'id' => 'defect_rate',
                    'name' => 'Defect Rate',
                    'description' => 'Percentage of defective units produced',
                    'tier' => 1,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-exclamation-triangle',
                ],
                [
                    'id' => 'first_pass_yield',
                    'name' => 'First Pass Yield',
                    'description' => 'Percentage of products made correctly without rework',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-check-circle',
                ],
                [
                    'id' => 'quality_control_pass_rate',
                    'name' => 'QC Pass Rate',
                    'description' => 'Percentage passing quality control inspections',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-clipboard-document-check',
                ],
                // Add more quality KPIs here...
            ],
            'production' => [
                [
                    'id' => 'throughput_per_machine',
                    'name' => 'Throughput per Machine',
                    'description' => 'Units produced per machine per time period',
                    'tier' => 1,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-arrow-trending-up',
                ],
                [
                    'id' => 'oee',
                    'name' => 'Overall Equipment Effectiveness (OEE)',
                    'description' => 'Comprehensive efficiency metric combining availability, performance, and quality',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-chart-bar-square',
                ],
                [
                    'id' => 'downtime_analysis',
                    'name' => 'Downtime Analysis',
                    'description' => 'Breakdown of downtime reasons and duration',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-pause-circle',
                ],
                [
                    'id' => 'cycle_time',
                    'name' => 'Average Cycle Time',
                    'description' => 'Time taken to complete one production cycle',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-clock',
                ],
                // Add more production KPIs here...
            ],
            'workforce' => [
                [
                    'id' => 'operator_efficiency',
                    'name' => 'Operator Efficiency',
                    'description' => 'Productivity metrics per operator',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-user',
                ],
                [
                    'id' => 'labor_utilization',
                    'name' => 'Labor Utilization Rate',
                    'description' => 'Percentage of labor hours spent on productive work',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-users',
                ],
                [
                    'id' => 'units_per_operator',
                    'name' => 'Units per Operator',
                    'description' => 'Production output per operator',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-user-circle',
                ],
                // Add more workforce KPIs here...
            ],
            'inventory' => [
                [
                    'id' => 'material_usage',
                    'name' => 'Material Usage Rate',
                    'description' => 'Rate of material consumption in production',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-squares-2x2',
                ],
                [
                    'id' => 'wip_levels',
                    'name' => 'Work in Progress (WIP) Levels',
                    'description' => 'Current WIP inventory across production',
                    'tier' => 2,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-queue-list',
                ],
                [
                    'id' => 'inventory_turnover',
                    'name' => 'Inventory Turnover',
                    'description' => 'Rate at which inventory is used and replaced',
                    'tier' => 3,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-arrow-path',
                ],
                // Add more inventory KPIs here...
            ],
            'financial' => [
                [
                    'id' => 'production_cost_per_unit',
                    'name' => 'Production Cost per Unit',
                    'description' => 'Average cost to produce one unit',
                    'tier' => 3,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-banknotes',
                ],
                [
                    'id' => 'labor_cost_percentage',
                    'name' => 'Labor Cost Percentage',
                    'description' => 'Labor costs as percentage of total production cost',
                    'tier' => 3,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-calculator',
                ],
                [
                    'id' => 'material_cost_variance',
                    'name' => 'Material Cost Variance',
                    'description' => 'Difference between expected and actual material costs',
                    'tier' => 3,
                    'status' => 'planned',
                    'icon' => 'heroicon-o-chart-bar',
                ],
                // Add more financial KPIs here...
            ],
        ];
    }

    /**
     * Get KPIs by category
     */
    public static function getKPIsByCategory(string $category): array
    {
        $allKPIs = self::getAllKPIs();

        return $allKPIs[$category] ?? [];
    }

    /**
     * Get single KPI by ID
     */
    public static function getKPI(string $category, string $kpiId): ?array
    {
        $kpis = self::getKPIsByCategory($category);
        foreach ($kpis as $kpi) {
            if ($kpi['id'] === $kpiId) {
                return $kpi;
            }
        }

        return null;
    }

    /**
     * Get total KPI count
     */
    public static function getTotalKPICount(): int
    {
        $total = 0;
        foreach (self::getAllKPIs() as $categoryKPIs) {
            $total += count($categoryKPIs);
        }

        return $total;
    }

    /**
     * Get KPI count by category
     */
    public static function getKPICountByCategory(string $category): int
    {
        return count(self::getKPIsByCategory($category));
    }

    /**
     * Get active KPI count
     */
    public static function getActiveKPICount(): int
    {
        $count = 0;
        foreach (self::getAllKPIs() as $categoryKPIs) {
            foreach ($categoryKPIs as $kpi) {
                if (($kpi['status'] ?? '') === 'active') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Search KPIs by name or description
     */
    public static function searchKPIs(string $query): array
    {
        $results = [];
        $query = strtolower($query);

        foreach (self::getAllKPIs() as $category => $kpis) {
            foreach ($kpis as $kpi) {
                if (
                    str_contains(strtolower($kpi['name']), $query) ||
                    str_contains(strtolower($kpi['description']), $query)
                ) {
                    $kpi['category'] = $category;
                    $results[] = $kpi;
                }
            }
        }

        return $results;
    }
}
