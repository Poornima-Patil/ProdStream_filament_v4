<?php

namespace App\Filament\Admin\Pages;

use App\Services\KPI\RealTimeKPIService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ProductionSchedulePage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Production Schedule';

    protected static ?string $title = 'Production Schedule Adherence';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'KPI System';

    // Pagination properties for completed WO categories
    public int $onTimePage = 1;

    public int $earlyPage = 1;

    public int $latePage = 1;

    // Pagination properties for at-risk WO categories
    public int $highRiskPage = 1;

    public int $mediumRiskPage = 1;

    public int $onTrackPage = 1;

    public int $perPage = 10;

    // Collapsible section states for completed WOs
    public bool $onTimeExpanded = true;

    public bool $earlyExpanded = true;

    public bool $lateExpanded = true;

    // Collapsible section states for at-risk WOs
    public bool $highRiskExpanded = true;

    public bool $mediumRiskExpanded = true;

    public bool $onTrackExpanded = false;

    // Track if this is a manual refresh
    public bool $skipCache = false;

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
        return 'filament.admin.pages.production-schedule';
    }

    /**
     * Refresh dashboard data by skipping cache
     */
    public function refreshData(): void
    {
        $this->skipCache = true;
    }

    /**
     * Get production schedule adherence data
     */
    public function getProductionScheduleData(): array
    {
        $factory = Auth::user()->factory;

        if (! $factory) {
            return $this->getEmptyData();
        }

        $service = new RealTimeKPIService($factory);
        $data = $service->getProductionScheduleAdherence($this->skipCache);

        // Reset skip cache flag after fetching data
        $this->skipCache = false;

        return $data;
    }

    /**
     * Get empty data structure
     */
    protected function getEmptyData(): array
    {
        return [
            'summary' => [
                'scheduled_today' => 0,
                'on_time_count' => 0,
                'early_count' => 0,
                'late_count' => 0,
                'on_time_rate' => 0,
                'avg_delay_minutes' => 0,
            ],
            'completed' => [
                'on_time' => [],
                'early' => [],
                'late' => [],
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
     * Toggle collapsible section for completed WOs
     */
    public function toggleCompletedSection(string $section): void
    {
        match ($section) {
            'on_time' => $this->onTimeExpanded = ! $this->onTimeExpanded,
            'early' => $this->earlyExpanded = ! $this->earlyExpanded,
            'late' => $this->lateExpanded = ! $this->lateExpanded,
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
     * Navigate to specific page for a completed WO category
     */
    public function gotoCompletedPage(string $category, int $page): void
    {
        match ($category) {
            'on_time' => $this->onTimePage = max(1, $page),
            'early' => $this->earlyPage = max(1, $page),
            'late' => $this->latePage = max(1, $page),
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
     * Get paginated work orders for a completed category
     */
    public function getPaginatedCompleted(array $workOrders, string $category): array
    {
        $page = match ($category) {
            'on_time' => $this->onTimePage,
            'early' => $this->earlyPage,
            'late' => $this->latePage,
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
     * Get paginated work orders for an at-risk category
     */
    public function getPaginatedAtRisk(array $workOrders, string $category): array
    {
        $page = match ($category) {
            'high_risk' => $this->highRiskPage,
            'medium_risk' => $this->mediumRiskPage,
            'on_track' => $this->onTrackPage,
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
     * Reset all pagination
     */
    public function resetPagination(): void
    {
        $this->onTimePage = 1;
        $this->earlyPage = 1;
        $this->latePage = 1;
        $this->highRiskPage = 1;
        $this->mediumRiskPage = 1;
        $this->onTrackPage = 1;
    }
}
