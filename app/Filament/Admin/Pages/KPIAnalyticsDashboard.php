<?php

namespace App\Filament\Admin\Pages;

use App\Services\KPI\KPIRegistry;
use App\Services\KPI\OperationalKPIService;
use App\Services\KPI\RealTimeKPIService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class KPIAnalyticsDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'KPI Analytics';

    protected static ?string $title = 'KPI Analytics Dashboard';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'KPI System';

    // State properties for the page
    public string $viewMode = 'hub'; // 'hub', 'category', 'kpi-detail'

    public ?string $selectedCategory = null;

    public ?string $selectedKPI = null;

    public string $kpiMode = 'dashboard'; // 'dashboard' or 'analytics' for KPI detail view

    public string $timePeriod = 'yesterday';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public bool $enableComparison = false;

    public string $comparisonType = 'previous_period';

    public string $searchQuery = '';

    public string $statusFilter = 'all'; // 'all', 'running', 'setup', 'hold', 'scheduled', 'idle'

    // Pagination properties for each status group
    public int $runningPage = 1;

    public int $holdPage = 1;

    public int $setupPage = 1;

    public int $scheduledPage = 1;

    public int $idlePage = 1;

    public int $perPage = 10;

    public int $defectPage = 1;

    // Collapsible section states
    public bool $runningExpanded = true;

    public bool $holdExpanded = true;

    public bool $setupExpanded = true;

    public bool $scheduledExpanded = true;

    public bool $idleExpanded = false;

    // Work Order Status pagination properties
    public int $woHoldPage = 1;

    public int $woSetupPage = 1;

    public int $woStartPage = 1;

    public int $woAssignedPage = 1;

    public int $woCompletedPage = 1;

    public int $woClosedPage = 1;

    // Work Order Status collapsible section states
    public bool $woHoldExpanded = true;

    public bool $woSetupExpanded = true;

    public bool $woStartExpanded = true;

    public bool $woAssignedExpanded = true;

    public bool $woCompletedExpanded = false;

    public bool $woClosedExpanded = false;

    // Production Schedule pagination properties
    public int $onTimePage = 1;

    public int $earlyPage = 1;

    public int $latePage = 1;

    public int $earlyFromFuturePage = 1;

    public int $lateFromPastPage = 1;

    public int $highRiskPage = 1;

    public int $mediumRiskPage = 1;

    public int $onTrackPage = 1;

    // Machine Status Analytics Daily Breakdown pagination
    public int $dailyBreakdownPage = 1;

    public int $dailyBreakdownPerPage = 10;

    // Production Schedule collapsible section states
    public bool $onTimeExpanded = true;

    public bool $earlyExpanded = true;

    public bool $lateExpanded = true;

    public bool $earlyFromFutureExpanded = true;

    public bool $lateFromPastExpanded = true;

    public bool $highRiskExpanded = true;

    public bool $mediumRiskExpanded = true;

    public bool $onTrackExpanded = false;

    /**
     * Apply search and filter to machine data
     */
    public function filterMachines(array $statusGroups): array
    {
        if (empty($this->searchQuery) && $this->statusFilter === 'all') {
            return $statusGroups;
        }

        $filtered = $statusGroups;
        $searchLower = strtolower($this->searchQuery);

        foreach ($filtered as $status => $group) {
            // Apply status filter first
            if ($this->statusFilter !== 'all' && $status !== $this->statusFilter) {
                $filtered[$status]['machines'] = [];
                $filtered[$status]['count'] = 0;

                continue;
            }

            // Apply search filter
            if (! empty($this->searchQuery) && ! empty($group['machines'])) {
                $filtered[$status]['machines'] = array_filter($group['machines'], function ($machine) use ($searchLower) {
                    return str_contains(strtolower($machine['name'] ?? ''), $searchLower) ||
                           str_contains(strtolower($machine['asset_id'] ?? ''), $searchLower) ||
                           str_contains(strtolower($machine['wo_number'] ?? ''), $searchLower) ||
                           str_contains(strtolower($machine['primary_wo_number'] ?? ''), $searchLower) ||
                           str_contains(strtolower($machine['next_wo_number'] ?? ''), $searchLower) ||
                           str_contains(strtolower($machine['part_number'] ?? ''), $searchLower) ||
                           str_contains(strtolower($machine['operator'] ?? ''), $searchLower);
                });

                $filtered[$status]['count'] = count($filtered[$status]['machines']);
            }
        }

        return $filtered;
    }

    /**
     * Reset filters
     */
    public function resetFilters(): void
    {
        $this->searchQuery = '';
        $this->statusFilter = 'all';
        $this->resetPagination();
    }

    /**
     * Reset all pagination
     */
    public function resetPagination(): void
    {
        $this->runningPage = 1;
        $this->holdPage = 1;
        $this->setupPage = 1;
        $this->scheduledPage = 1;
        $this->idlePage = 1;
    }

    /**
     * Toggle collapsible section
     */
    public function toggleSection(string $section): void
    {
        match ($section) {
            'running' => $this->runningExpanded = ! $this->runningExpanded,
            'hold' => $this->holdExpanded = ! $this->holdExpanded,
            'setup' => $this->setupExpanded = ! $this->setupExpanded,
            'scheduled' => $this->scheduledExpanded = ! $this->scheduledExpanded,
            'idle' => $this->idleExpanded = ! $this->idleExpanded,
            default => null,
        };
    }

    /**
     * Toggle work order collapsible section
     */
    public function toggleWOSection(string $section): void
    {
        match ($section) {
            'hold' => $this->woHoldExpanded = ! $this->woHoldExpanded,
            'setup' => $this->woSetupExpanded = ! $this->woSetupExpanded,
            'start' => $this->woStartExpanded = ! $this->woStartExpanded,
            'assigned' => $this->woAssignedExpanded = ! $this->woAssignedExpanded,
            'completed' => $this->woCompletedExpanded = ! $this->woCompletedExpanded,
            'closed' => $this->woClosedExpanded = ! $this->woClosedExpanded,
            default => null,
        };
    }

    /**
     * Navigate to specific page for a status group
     */
    public function gotoPage(string $status, int $page): void
    {
        match ($status) {
            'running' => $this->runningPage = max(1, $page),
            'hold' => $this->holdPage = max(1, $page),
            'setup' => $this->setupPage = max(1, $page),
            'scheduled' => $this->scheduledPage = max(1, $page),
            'idle' => $this->idlePage = max(1, $page),
            default => null,
        };
    }

    /**
     * Navigate to specific page for a work order status group
     */
    public function gotoWOPage(string $status, int $page): void
    {
        match ($status) {
            'hold' => $this->woHoldPage = max(1, $page),
            'setup' => $this->woSetupPage = max(1, $page),
            'start' => $this->woStartPage = max(1, $page),
            'assigned' => $this->woAssignedPage = max(1, $page),
            'completed' => $this->woCompletedPage = max(1, $page),
            'closed' => $this->woClosedPage = max(1, $page),
            default => null,
        };
    }

    /**
     * Navigate defect rate table pagination
     */
    public function gotoDefectPage(string $status, int $page): void
    {
        $this->defectPage = max(1, $page);
    }

    /**
     * Get paginated machines for a specific status
     */
    public function getPaginatedMachines(array $machines, string $status): array
    {
        $page = match ($status) {
            'running' => $this->runningPage,
            'hold' => $this->holdPage,
            'setup' => $this->setupPage,
            'scheduled' => $this->scheduledPage,
            'idle' => $this->idlePage,
            default => 1,
        };

        $total = count($machines);
        $totalPages = max(1, (int) ceil($total / $this->perPage));
        $page = min($page, $totalPages);

        $offset = ($page - 1) * $this->perPage;
        $paginated = array_slice($machines, $offset, $this->perPage);

        return [
            'data' => $paginated,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'per_page' => $this->perPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => $total > 0 ? min($offset + $this->perPage, $total) : 0,
        ];
    }

    /**
     * Get paginated work orders for a specific status
     */
    public function getPaginatedWorkOrders(array $workOrders, string $status): array
    {
        $page = match ($status) {
            'hold' => $this->woHoldPage,
            'setup' => $this->woSetupPage,
            'start' => $this->woStartPage,
            'assigned' => $this->woAssignedPage,
            'completed' => $this->woCompletedPage,
            'closed' => $this->woClosedPage,
            default => 1,
        };

        $total = count($workOrders);
        $totalPages = max(1, (int) ceil($total / $this->perPage));
        $page = min($page, $totalPages);

        $offset = ($page - 1) * $this->perPage;
        $paginated = array_slice($workOrders, $offset, $this->perPage);

        return [
            'data' => $paginated,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'per_page' => $this->perPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => $total > 0 ? min($offset + $this->perPage, $total) : 0,
        ];
    }

    /**
     * Make this visible to authenticated factory users only
     */
    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check();
    }

    /**
     * Check if user can access this page
     */
    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->factory_id !== null;
    }

    /**
     * Get the view for this page
     */
    public function getView(): string
    {
        return 'filament.admin.pages.kpi-analytics-dashboard';
    }

    /**
     * Mount the page with default values
     */
    public function mount(): void
    {
        $this->form->fill([
            'timePeriod' => 'yesterday',  // Changed to yesterday since we have data for it
            'enableComparison' => false,
            'comparisonType' => 'previous_period',
        ]);
    }

    /**
     * Called when time period is updated
     */
    public function updatedTimePeriod(): void
    {
        // Component will auto-refresh when this property changes
    }

    /**
     * Called when comparison toggle is updated
     */
    public function updatedEnableComparison(): void
    {
        // Component will auto-refresh when this property changes
    }

    /**
     * Get all KPI categories
     */
    public function getCategories(): array
    {
        return KPIRegistry::getCategories();
    }

    /**
     * View a specific category
     */
    public function viewCategory(string $category): void
    {
        $this->viewMode = 'category';
        $this->selectedCategory = $category;
    }

    /**
     * View a specific KPI
     */
    public function viewKPI(string $category, string $kpiId): void
    {
        $this->viewMode = 'kpi-detail';
        $this->selectedCategory = $category;
        $this->selectedKPI = $kpiId;
    }

    /**
     * Go back to hub view
     */
    public function backToHub(): void
    {
        $this->viewMode = 'hub';
        $this->selectedCategory = null;
        $this->selectedKPI = null;
    }

    /**
     * Go back to category view
     */
    public function backToCategory(): void
    {
        $this->viewMode = 'category';
        $this->selectedKPI = null;
    }

    /**
     * Switch between dashboard and analytics mode (for KPI detail view)
     */
    public function setKPIMode(string $mode): void
    {
        $this->kpiMode = $mode;
    }

    /**
     * Get KPIs for selected category
     */
    public function getCategoryKPIs(): array
    {
        if (! $this->selectedCategory) {
            return [];
        }

        return KPIRegistry::getKPIsByCategory($this->selectedCategory);
    }

    /**
     * Get selected KPI details
     */
    public function getSelectedKPI(): ?array
    {
        if (! $this->selectedCategory || ! $this->selectedKPI) {
            return null;
        }

        return KPIRegistry::getKPI($this->selectedCategory, $this->selectedKPI);
    }

    // Track if this is a manual refresh
    public bool $skipCache = false;

    /**
     * Refresh dashboard data by skipping cache
     */
    public function refreshData(): void
    {
        $this->skipCache = true;
    }

    /**
     * Get machine status data based on current mode
     */
    public function getMachineStatusData(): array
    {
        $factory = Auth::user()->factory;

        if (! $factory) {
            return $this->getEmptyData();
        }

        if ($this->kpiMode === 'dashboard') {
            $service = new RealTimeKPIService($factory);
            $data = $service->getCurrentMachineStatus($this->skipCache);

            // Reset skip cache flag after fetching data
            $this->skipCache = false;

            // Apply filters to dashboard data
            $data['status_groups'] = $this->filterMachines($data['status_groups']);

            return $data;
        }

        // Analytics mode - read from public properties
        $service = new OperationalKPIService($factory);

        return $service->getMachineStatusAnalytics([
            'time_period' => $this->timePeriod,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'enable_comparison' => $this->enableComparison,
            'comparison_type' => $this->comparisonType,
        ]);
    }

    /**
     * Get empty data structure
     */
    protected function getEmptyData(): array
    {
        return [
            'status_groups' => [
                'running' => ['count' => 0, 'machines' => []],
                'hold' => ['count' => 0, 'machines' => []],
                'setup' => ['count' => 0, 'machines' => []],
                'scheduled' => ['count' => 0, 'machines' => []],
                'idle' => ['count' => 0, 'machines' => []],
            ],
            'total_machines' => 0,
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get work order status data based on current mode
     */
    public function getWorkOrderStatusData(): array
    {
        $factory = Auth::user()->factory;

        if (! $factory) {
            return $this->getEmptyWorkOrderData();
        }

        if ($this->kpiMode === 'dashboard') {
            $service = new RealTimeKPIService($factory);
            $data = $service->getCurrentWorkOrderStatus($this->skipCache);

            // Reset skip cache flag after fetching data
            $this->skipCache = false;

            return $data;
        }

        // Analytics mode - read from public properties
        $service = new OperationalKPIService($factory);

        return $service->getWorkOrderStatusAnalytics([
            'time_period' => $this->timePeriod,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'enable_comparison' => $this->enableComparison,
            'comparison_type' => $this->comparisonType,
        ]);
    }

    /**
     * Get setup time analytics data (analytics mode only)
     */
    public function getSetupTimeAnalyticsData(): array
    {
        $factory = Auth::user()->factory;

        if (! $factory || $this->kpiMode !== 'analytics') {
            return $this->getEmptySetupAnalyticsData();
        }

        $service = new OperationalKPIService($factory);

        return $service->getSetupTimeAnalytics([
            'time_period' => $this->timePeriod,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'enable_comparison' => $this->enableComparison,
            'comparison_type' => $this->comparisonType,
        ]);
    }

    /**
     * Empty setup analytics structure
     */
    protected function getEmptySetupAnalyticsData(): array
    {
        return [
            'primary_period' => null,
            'comparison_period' => null,
            'comparison_analysis' => null,
        ];
    }

    /**
     * Get defect rate data based on current mode
     */
    public function getDefectRateData(): array
    {
        $factory = Auth::user()->factory;

        if (! $factory) {
            return $this->getEmptyDefectDashboardData();
        }

        if ($this->kpiMode === 'dashboard') {
            $service = new RealTimeKPIService($factory);
            $data = $service->getCurrentDefectRate($this->skipCache);

            $this->skipCache = false;

            return $this->applyDefectPagination($data);
        }

        $service = new OperationalKPIService($factory);

        return $service->getDefectRateAnalytics([
            'time_period' => $this->timePeriod,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'enable_comparison' => $this->enableComparison,
            'comparison_type' => $this->comparisonType,
        ]);
    }

    /**
     * Get empty work order data structure
     */
    protected function getEmptyWorkOrderData(): array
    {
        return [
            'status_distribution' => [
                'hold' => ['count' => 0, 'work_orders' => []],
                'setup' => ['count' => 0, 'work_orders' => []],
                'start' => ['count' => 0, 'work_orders' => []],
                'assigned' => ['count' => 0, 'work_orders' => []],
                'completed' => ['count' => 0, 'work_orders' => []],
                'closed' => ['count' => 0, 'work_orders' => []],
            ],
            'total_work_orders' => 0,
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Apply pagination to defect rate dashboard results
     */
    protected function applyDefectPagination(array $data): array
    {
        $workOrders = $data['work_orders'] ?? [];
        $pagination = $this->getPaginatedDefectWorkOrders($workOrders);

        $data['work_orders'] = $workOrders;
        $data['work_orders_paginated'] = $pagination['data'];
        $data['pagination'] = $pagination;

        if (! isset($data['summary'])) {
            $data['summary'] = [
                'defective_work_orders' => 0,
                'total_scrap_today' => 0,
                'total_produced_today' => 0,
                'avg_defect_rate' => 0,
                'worst_defect_rate' => 0,
            ];
        }

        $data['updated_at'] = $data['updated_at'] ?? now()->toDateTimeString();

        return $data;
    }

    /**
     * Paginate defect rate work orders
     */
    protected function getPaginatedDefectWorkOrders(array $workOrders): array
    {
        $total = count($workOrders);
        $perPage = $this->perPage;
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min(max(1, $this->defectPage), $totalPages);

        $offset = ($page - 1) * $perPage;
        $paginated = $total > 0 ? array_slice($workOrders, $offset, $perPage) : [];

        // Ensure current page is valid when data shrinks
        if ($total > 0 && empty($paginated) && $page > 1) {
            $this->defectPage = 1;

            return $this->getPaginatedDefectWorkOrders($workOrders);
        }

        $this->defectPage = $total > 0 ? $page : 1;

        return [
            'data' => $paginated,
            'current_page' => $this->defectPage,
            'total_pages' => $totalPages,
            'total' => $total,
            'per_page' => $perPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
        ];
    }

    /**
     * Empty defect dashboard structure
     */
    protected function getEmptyDefectDashboardData(): array
    {
        return [
            'summary' => [
                'defective_work_orders' => 0,
                'total_scrap_today' => 0,
                'total_produced_today' => 0,
                'avg_defect_rate' => 0,
                'worst_defect_rate' => 0,
            ],
            'work_orders' => [],
            'work_orders_paginated' => [],
            'pagination' => [
                'data' => [],
                'current_page' => 1,
                'total_pages' => 1,
                'total' => 0,
                'per_page' => $this->perPage,
                'from' => 0,
                'to' => 0,
            ],
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get production schedule adherence data
     */
    public function getProductionScheduleData(): array
    {
        $factory = Auth::user()->factory;

        if (! $factory) {
            return $this->getEmptyProductionScheduleData();
        }

        // In Dashboard mode, get real-time data for TODAY only
        if ($this->kpiMode === 'dashboard') {
            $service = new RealTimeKPIService($factory);
            $data = $service->getProductionScheduleAdherence($this->skipCache);

            // Reset skip cache flag after fetching data
            $this->skipCache = false;

            return $data;
        }

        // In Analytics mode, get data for the selected time period
        $service = new OperationalKPIService($factory);

        return $service->getProductionScheduleAdherenceAnalytics([
            'time_period' => $this->timePeriod,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'enable_comparison' => $this->enableComparison,
            'comparison_type' => $this->comparisonType,
        ]);
    }

    /**
     * Get empty production schedule data structure
     */
    protected function getEmptyProductionScheduleData(): array
    {
        return [
            'summary' => [
                'scheduled_today' => 0,
                'on_time_count' => 0,
                'early_count' => 0,
                'late_count' => 0,
                'on_time_rate' => 0,
                'avg_delay_minutes' => 0,
                'early_from_future_count' => 0,
                'late_from_past_count' => 0,
                'total_completions_today' => 0,
            ],
            'scheduled_today' => [
                'on_time' => [],
                'early' => [],
                'late' => [],
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
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get machine utilization data based on current mode
     */
    public function getMachineUtilizationData(): array
    {
        $factory = Auth::user()->factory;

        if (! $factory) {
            return $this->getEmptyMachineUtilizationData();
        }

        if ($this->kpiMode === 'dashboard') {
            $service = new RealTimeKPIService($factory);
            $data = $service->getMachineUtilization($this->skipCache);

            // Reset skip cache flag after fetching data
            $this->skipCache = false;

            return $data;
        }

        // Analytics mode - read from public properties
        $service = new OperationalKPIService($factory);

        return $service->getMachineUtilizationAnalytics([
            'time_period' => $this->timePeriod,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'enable_comparison' => $this->enableComparison,
            'comparison_type' => $this->comparisonType,
        ]);
    }

    /**
     * Get empty machine utilization data structure
     */
    protected function getEmptyMachineUtilizationData(): array
    {
        return [
            'primary_period' => [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'label' => 'No Data',
                'daily_breakdown' => [],
                'summary' => [
                    'avg_scheduled_utilization' => 0,
                    'avg_active_utilization' => 0,
                    'total_uptime_hours' => 0,
                    'total_downtime_hours' => 0,
                    'total_planned_downtime_hours' => 0,
                    'total_unplanned_downtime_hours' => 0,
                    'total_units_produced' => 0,
                    'total_work_orders_completed' => 0,
                    'machines_analyzed' => 0,
                    'days_analyzed' => 0,
                ],
            ],
        ];
    }

    /**
     * Get paginated work orders for a scheduled today category
     */
    public function getPaginatedCompleted(array $workOrders, string $category): array
    {
        // This will be called from the production schedule template
        // Use simple pagination for now
        return [
            'data' => array_slice($workOrders, 0, 10),
            'current_page' => 1,
            'total_pages' => max(1, (int) ceil(count($workOrders) / 10)),
            'total' => count($workOrders),
            'per_page' => 10,
            'from' => count($workOrders) > 0 ? 1 : 0,
            'to' => min(10, count($workOrders)),
        ];
    }

    /**
     * Get paginated work orders for an other completions category
     */
    public function getPaginatedOtherCompletions(array $workOrders, string $category): array
    {
        return $this->getPaginatedCompleted($workOrders, $category);
    }

    /**
     * Get paginated work orders for an at-risk category
     */
    public function getPaginatedAtRisk(array $workOrders, string $category): array
    {
        return $this->getPaginatedCompleted($workOrders, $category);
    }

    /**
     * Toggle collapsible section for scheduled today WOs
     */
    public function toggleScheduledTodaySection(string $section): void
    {
        match ($section) {
            'on_time' => $this->onTimeExpanded = ! $this->onTimeExpanded,
            'early' => $this->earlyExpanded = ! $this->earlyExpanded,
            'late' => $this->lateExpanded = ! $this->lateExpanded,
            default => null,
        };
    }

    /**
     * Toggle collapsible section for other completions
     */
    public function toggleOtherCompletionsSection(string $section): void
    {
        match ($section) {
            'early_from_future' => $this->earlyFromFutureExpanded = ! $this->earlyFromFutureExpanded,
            'late_from_past' => $this->lateFromPastExpanded = ! $this->lateFromPastExpanded,
            default => null,
        };
    }

    /**
     * Toggle collapsible section for at-risk WOs
     */
    public function toggleAtRiskSection(string $section): void
    {
        match ($section) {
            'high_risk' => $this->highRiskExpanded = ! $this->highRiskExpanded,
            'medium_risk' => $this->mediumRiskExpanded = ! $this->mediumRiskExpanded,
            'on_track' => $this->onTrackExpanded = ! $this->onTrackExpanded,
            default => null,
        };
    }

    /**
     * Navigate to specific page for a scheduled today WO category
     */
    public function gotoScheduledTodayPage(string $category, int $page): void
    {
        match ($category) {
            'on_time' => $this->onTimePage = max(1, $page),
            'early' => $this->earlyPage = max(1, $page),
            'late' => $this->latePage = max(1, $page),
            default => null,
        };
    }

    /**
     * Navigate to specific page for an other completions category
     */
    public function gotoOtherCompletionsPage(string $category, int $page): void
    {
        match ($category) {
            'early_from_future' => $this->earlyFromFuturePage = max(1, $page),
            'late_from_past' => $this->lateFromPastPage = max(1, $page),
            default => null,
        };
    }

    /**
     * Navigate to specific page for an at-risk WO category
     */
    public function gotoAtRiskPage(string $category, int $page): void
    {
        match ($category) {
            'high_risk' => $this->highRiskPage = max(1, $page),
            'medium_risk' => $this->mediumRiskPage = max(1, $page),
            'on_track' => $this->onTrackPage = max(1, $page),
            default => null,
        };
    }

    /**
     * Navigate to specific page for daily breakdown table
     */
    public function gotoDailyBreakdownPage(int $page): void
    {
        $this->dailyBreakdownPage = max(1, $page);
    }

    /**
     * Get paginated daily breakdown data
     */
    public function getPaginatedDailyBreakdown(array $dailyData): array
    {
        $total = count($dailyData);
        $totalPages = max(1, (int) ceil($total / $this->dailyBreakdownPerPage));
        $page = min($this->dailyBreakdownPage, $totalPages);

        $offset = ($page - 1) * $this->dailyBreakdownPerPage;
        $paginated = array_slice($dailyData, $offset, $this->dailyBreakdownPerPage);

        return [
            'data' => $paginated,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'per_page' => $this->dailyBreakdownPerPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => $total > 0 ? min($offset + $this->dailyBreakdownPerPage, $total) : 0,
        ];
    }

    /**
     * Form schema for analytics filters
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('timePeriod')
                    ->label('Time Period')
                    ->options([
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
                        'custom' => 'Custom Date Range',
                    ])
                    ->default('yesterday')
                    ->required()
                    ->live(),

                Forms\Components\DatePicker::make('dateFrom')
                    ->label('From Date')
                    ->visible(fn ($get) => $get('timePeriod') === 'custom')
                    ->maxDate(now())
                    ->live(),

                Forms\Components\DatePicker::make('dateTo')
                    ->label('To Date')
                    ->visible(fn ($get) => $get('timePeriod') === 'custom')
                    ->maxDate(now())
                    ->live(),

                Forms\Components\Toggle::make('enableComparison')
                    ->label('Compare with previous period')
                    ->default(false)
                    ->live(),

                Forms\Components\Select::make('comparisonType')
                    ->label('Comparison Type')
                    ->options([
                        'previous_period' => 'Previous Period (same duration)',
                        'previous_week' => 'Previous Week',
                        'previous_month' => 'Previous Month',
                        'previous_quarter' => 'Previous Quarter',
                        'previous_year' => 'Same Period Last Year',
                    ])
                    ->visible(fn ($get) => $get('enableComparison'))
                    ->default('previous_period')
                    ->live(),
            ]);
    }
}
