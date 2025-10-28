# Machine Status Dashboard & Analytics

This page explains how the **Machine Status** KPI in the Filament admin panel is assembled, the difference between _Dashboard_ and _Analytics_ modes, and where to extend behaviour.

## Where to find it

The UI lives on the KPI Analytics dashboard (`KPIAnalyticsDashboard`), reachable from the Filament navigation under **KPI System → KPI Analytics**. When you open the **Machine Status** KPI you can toggle between:

- **Dashboard mode** – a real-time snapshot of what is happening _today_.
- **Analytics mode** – historical rollups with trend comparisons over a selected period.

The page class is `app/Filament/Admin/Pages/KPIAnalyticsDashboard.php` and the Blade view is `resources/views/filament/admin/pages/kpi-analytics-dashboard.blade.php`. Analytics mode renders an additional partial at `resources/views/filament/admin/pages/machine-status-analytics.blade.php`.

## Data flow in dashboard mode

When `kpiMode === 'dashboard'`, the page calls `getMachineStatusData()`, which in turn uses the `RealTimeKPIService`:

```php
// app/Filament/Admin/Pages/KPIAnalyticsDashboard.php:446-478
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

    // Analytics mode omitted…
}
```

Key points:

- **Query scope** – `RealTimeKPIService::getCurrentMachineStatus()` loads every machine for the signed-in user’s factory together with the work orders that matter today (`Start`, `Setup`, `Hold`, plus `Assigned` jobs scheduled to begin before end-of-day).
- **Status grouping** – machines are normalised into five status buckets (`running`, `hold`, `setup`, `scheduled`, `idle`). The latest relevant work order per machine decides the bucket.
- **Metadata** – each bucket carries rich details: operator names, primary/next work orders, production progress, hold reason, scheduled start time, etc., so the Blade template can render tables, gauges, and progress bars without additional queries.
- **Caching** – results are cached for five minutes (`getCachedKPI('current_machine_status_v2', …, 300)`), unless the user presses the _Refresh_ button, which flips `$skipCache` and forces a live recalculation.
- **Filtering & pagination** – once the service returns, the page applies search and status filters (`filterMachines`) and slices the groups into per-status pages (`getPaginatedMachines`) before handing them to the Blade sections that render each status table inside `kpi-analytics-dashboard.blade.php`.

## Data flow in analytics mode

Switching to analytics mode tells the page to reuse the same entry point (`getMachineStatusData`) but defer to `OperationalKPIService::getMachineStatusAnalytics()`, which reads historic aggregates:

```php
// app/Services/KPI/OperationalKPIService.php:20-70
public function getMachineStatusAnalytics(array $options): array
{
    $period = $options['time_period'] ?? 'yesterday';
    $enableComparison = $options['enable_comparison'] ?? false;
    $comparisonType = $options['comparison_type'] ?? 'previous_period';

    $dateFrom = isset($options['date_from']) ? Carbon::parse($options['date_from']) : null;
    $dateTo = isset($options['date_to']) ? Carbon::parse($options['date_to']) : null;

    [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

    $cacheKey = "machine_status_analytics_v2_{$period}_".md5(json_encode($options));
    $cacheTTL = $this->getCacheTTL($period);

    return $this->getCachedKPI($cacheKey, function () use ($startDate, $endDate, $enableComparison, $comparisonType, $options) {
        // 1. Pull the primary-period distribution from MachineStatusDaily
        $primaryData = $this->fetchMachineStatusDistribution($startDate, $endDate);

        $result = [
            'primary_period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'label' => $this->getPeriodLabel($options['time_period'] ?? 'yesterday', $startDate, $endDate),
                'daily_breakdown' => $primaryData['daily'],
                'summary' => $primaryData['summary'],
            ],
        ];

        // 2. Optionally load a comparison period and compute deltas
        if ($enableComparison) {
            [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);
            $comparisonData = $this->fetchMachineStatusDistribution($compStart, $compEnd);

            $result['comparison_period'] = [
                'start_date' => $compStart->toDateString(),
                'end_date' => $compEnd->toDateString(),
                'label' => $this->getPeriodLabel($comparisonType, $compStart, $compEnd),
                'daily_breakdown' => $comparisonData['daily'],
                'summary' => $comparisonData['summary'],
            ];

            $result['comparison_analysis'] = $this->calculateStatusDistributionComparison(
                $primaryData['summary'],
                $comparisonData['summary']
            );
        }

        return $result;
    }, $cacheTTL);
}
```

Highlights:

- **Primary source** – analytics mode prefers pre-aggregated records from `kpi_machine_status_daily` (through the `MachineStatusDaily` model). If no row exists for a day, it falls back to `calculateMachineStatusForDate()` which replays `WorkOrder` and `WorkOrderLog` history to reconstruct the counts.
- **Summaries** – for each day the service stores raw counts and keeps a rolling average across the period (average machines running, average % running, etc.).
- **Comparisons** – enabling _Compare against previous period_ adds a second dataset and a diff block so the Blade partial can show green/red deltas.
- **Caching** – analytics queries are cached with a TTL that matches the selected range (`getCacheTTL` shortens the window for shorter periods).

## Rendering & interaction

- The dashboard view (`kpi-analytics-dashboard.blade.php`) builds the search/filter bar, Chart.js donut (immediately after the filters), summary cards, and detailed tables for live data. All of those widgets consume the same filtered `status_groups` payload, so applying a filter/search instantly changes the chart and the tables.
- Analytics mode includes `machine-status-analytics.blade.php`, which:
  - Shows the selected period header and average counts.
  - Renders comparison callouts when `comparison_analysis` is present.
  - Displays the daily breakdown with stacked bar visualisations and pagination driven from `gotoDailyBreakdownPage`.

In both modes pagination and search are handled by Livewire properties on the `KPIAnalyticsDashboard` page class, so UI state persists while the user drills into data.

## Extending or debugging

1. **Change what “today” includes** – tweak the query inside `RealTimeKPIService::getCurrentMachineStatus()`. For example, adjust the `whereBetween('start_time', [$today, $endOfToday])` clause if you need to include yesterday’s late shifts.
2. **Add new status metadata** – extend the machine arrays returned inside the switch statement in the same method; the Blade templates automatically surface whatever keys you add.
3. **Persist new analytics metrics** – extend the `MachineStatusDaily` table or the `fetchMachineStatusDistribution()` method to calculate extra fields, then render them inside `machine-status-analytics.blade.php`.
4. **Troubleshoot stale data** – use the _Refresh_ button in dashboard mode (bypasses cache) or clear the cached key (`current_machine_status_v2`) via `php artisan cache:forget`.

With this flow in mind you can safely adjust or build upon the Machine Status KPI without breaking either real-time dashboards or historical analytics.***
