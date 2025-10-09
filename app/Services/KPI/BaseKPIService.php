<?php

namespace App\Services\KPI;

use App\Models\Factory;
use App\Support\TenantKPICache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

abstract class BaseKPIService
{
    protected Factory $factory;
    protected TenantKPICache $cache;
    protected string $tier;

    public function __construct(Factory $factory, string $tier = 'tier_2')
    {
        $this->factory = $factory;
        $this->cache = new TenantKPICache($factory);
        $this->tier = $tier;
    }

    /**
     * Get cached KPI value
     */
    protected function getCachedKPI(string $key, callable $callback, int $ttl): mixed
    {
        return $this->cache->get($key, $this->tier, $callback, $ttl);
    }

    /**
     * Calculate comparison with previous period
     */
    protected function calculateComparison(float $current, float $previous): array
    {
        if ($previous == 0) {
            return [
                'change' => 0,
                'change_percentage' => 0,
                'trend' => 'neutral'
            ];
        }

        $change = $current - $previous;
        $changePercentage = round(($change / $previous) * 100, 2);

        return [
            'change' => round($change, 2),
            'change_percentage' => $changePercentage,
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral')
        ];
    }

    /**
     * Get status based on value vs target
     */
    protected function getStatus(float $value, float $target, bool $higherIsBetter = true): string
    {
        if ($target == 0) {
            return 'neutral';
        }

        $percentage = ($value / $target) * 100;

        if ($higherIsBetter) {
            if ($percentage >= 100) return 'excellent';
            if ($percentage >= 90) return 'good';
            if ($percentage >= 75) return 'warning';
            return 'critical';
        } else {
            // Lower is better (e.g., scrap rate)
            if ($percentage <= 50) return 'excellent';
            if ($percentage <= 75) return 'good';
            if ($percentage <= 100) return 'warning';
            return 'critical';
        }
    }

    /**
     * Format date range for queries
     */
    protected function getDateRange(string $period): array
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
            default:
                $startDate = Carbon::now()->subDays(30);
        }

        return [$startDate, $endDate];
    }

    /**
     * Clear cache for this service
     */
    public function clearCache(): bool
    {
        return $this->cache->flushTier($this->tier);
    }

    /**
     * Abstract methods to be implemented by child classes
     */
    abstract public function getKPIs(array $options = []): array;
}
