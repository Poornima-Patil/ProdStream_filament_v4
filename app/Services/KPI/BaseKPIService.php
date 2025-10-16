<?php

namespace App\Services\KPI;

use App\Models\Factory;
use App\Support\TenantKPICache;
use Carbon\Carbon;

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
                'trend' => 'neutral',
            ];
        }

        $change = $current - $previous;
        $changePercentage = round(($change / $previous) * 100, 2);

        return [
            'change' => round($change, 2),
            'change_percentage' => $changePercentage,
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
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
            if ($percentage >= 100) {
                return 'excellent';
            }
            if ($percentage >= 90) {
                return 'good';
            }
            if ($percentage >= 75) {
                return 'warning';
            }

            return 'critical';
        } else {
            // Lower is better (e.g., scrap rate)
            if ($percentage <= 50) {
                return 'excellent';
            }
            if ($percentage <= 75) {
                return 'good';
            }
            if ($percentage <= 100) {
                return 'warning';
            }

            return 'critical';
        }
    }

    /**
     * Format date range for queries with support for custom dates and additional presets
     */
    protected function getDateRange(string $period, ?Carbon $from = null, ?Carbon $to = null): array
    {
        // If custom dates provided, use them
        if ($period === 'custom' && $from && $to) {
            return [$from, $to];
        }

        $endDate = Carbon::now();

        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday()->startOfDay();
                $endDate = Carbon::yesterday()->endOfDay();
                break;
            case 'this_week':
                $startDate = Carbon::now()->startOfWeek();
                break;
            case 'last_week':
                $startDate = Carbon::now()->subWeek()->startOfWeek();
                $endDate = Carbon::now()->subWeek()->endOfWeek();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                break;
            case 'this_quarter':
                $startDate = Carbon::now()->startOfQuarter();
                break;
            case 'this_year':
                $startDate = Carbon::now()->startOfYear();
                break;
            case '7d':
                $startDate = Carbon::now()->subDays(7);
                break;
            case '14d':
                $startDate = Carbon::now()->subDays(14);
                break;
            case '30d':
                $startDate = Carbon::now()->subDays(30);
                break;
            case '60d':
                $startDate = Carbon::now()->subDays(60);
                break;
            case '90d':
                $startDate = Carbon::now()->subDays(90);
                break;
            case 'mtd': // Month to date
                $startDate = Carbon::now()->startOfMonth();
                break;
            case 'ytd': // Year to date
                $startDate = Carbon::now()->startOfYear();
                break;
            default:
                $startDate = Carbon::now()->subDays(30);
        }

        return [$startDate, $endDate];
    }

    /**
     * Calculate comparison date range based on primary period
     */
    protected function getComparisonDateRange(Carbon $start, Carbon $end, string $type): array
    {
        $duration = $start->diffInDays($end);

        return match ($type) {
            'previous_period' => [
                $start->copy()->subDays($duration + 1),
                $start->copy()->subDay(),
            ],
            'previous_week' => [
                $start->copy()->subWeek()->startOfWeek(),
                $start->copy()->subWeek()->endOfWeek(),
            ],
            'previous_month' => [
                $start->copy()->subMonth()->startOfMonth(),
                $start->copy()->subMonth()->endOfMonth(),
            ],
            'previous_quarter' => [
                $start->copy()->subQuarter()->startOfQuarter(),
                $start->copy()->subQuarter()->endOfQuarter(),
            ],
            'previous_year' => [
                $start->copy()->subYear(),
                $end->copy()->subYear(),
            ],
            default => [$start, $end],
        };
    }

    /**
     * Get appropriate cache TTL based on period
     */
    protected function getCacheTTL(string $period): int
    {
        return match ($period) {
            'today' => 300,              // 5 minutes
            'yesterday' => 3600,         // 1 hour (historical)
            'this_week' => 600,          // 10 minutes
            'last_week' => 3600,         // 1 hour (historical)
            'this_month' => 900,         // 15 minutes
            'last_month' => 7200,        // 2 hours (historical)
            '7d' => 900,                 // 15 minutes
            '14d' => 1800,               // 30 minutes
            '30d' => 1800,               // 30 minutes
            '60d' => 3600,               // 1 hour
            '90d', 'mtd' => 3600,        // 1 hour
            'this_quarter' => 3600,      // 1 hour
            'this_year', 'ytd' => 7200,  // 2 hours
            'custom' => 1800,            // 30 minutes (default for custom)
            default => 1800,
        };
    }

    /**
     * Get optimal table to query based on date range
     * Daily table: last 90 days
     * Weekly table: 91 days - 1 year
     * Monthly table: 1+ years
     */
    protected function getOptimalTable(string $baseTable, Carbon $startDate): string
    {
        $daysSinceStart = Carbon::now()->diffInDays($startDate);

        if ($daysSinceStart <= 90) {
            return $baseTable.'_daily';
        } elseif ($daysSinceStart <= 365) {
            return $baseTable.'_weekly'; // Future enhancement
        } else {
            return $baseTable.'_monthly';
        }
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
