# Setup Time KPI Implementation Guide

**Status:** Active (Dashboard + Analytics)  
**Last Updated:** October 2025

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Data Sources](#data-sources)
4. [Calculation Flow](#calculation-flow)
5. [Filters & Comparison Options](#filters--comparison-options)
6. [Dashboard Mode (Real-Time)](#dashboard-mode-real-time)
7. [Analytics UI Rendering](#analytics-ui-rendering)
8. [Caching Strategy](#caching-strategy)
9. [Testing & Validation](#testing--validation)
10. [Key Considerations](#key-considerations)

---

## Overview

- Setup Time per Machine is registered as a Tier 2 KPI in `app/Services/KPI/KPIRegistry.php:100`, which exposes the KPI inside the Operational catalog in Filament.
- The KPI supports both **Dashboard** (real-time monitoring) and **Analytics** (historical analysis) modes, driven by the page-level toggle on `app/Filament/Admin/Pages/KPIAnalyticsDashboard.php:561`.
- Dashboard mode surfaces active setups derived from the general work-order status feed, while analytics mode performs historical aggregation of setup-to-start gaps.

## Architecture

- **KPIAnalyticsDashboard (Livewire page)** routes requests to the correct layer based on the UI mode. Analytics calls `getSetupTimeAnalyticsData()` (`app/Filament/Admin/Pages/KPIAnalyticsDashboard.php:561`), while dashboard mode reuses the real-time work-order payload.
- **OperationalKPIService** owns the historical computation via `getSetupTimeAnalytics()` (`app/Services/KPI/OperationalKPIService.php:1050`) and its helper `fetchSetupTimeDistribution()` (`app/Services/KPI/OperationalKPIService.php:1109`).
- **RealTimeKPIService** enriches the dashboard feed with setup-specific metadata so the card can render live metrics without re-querying logs (`app/Services/KPI/RealTimeKPIService.php:384`).
- The page renders analytics mode by loading the dedicated Blade partial `resources/views/filament/admin/pages/setup-time-analytics.blade.php:1`.

```
KPIAnalyticsDashboard (Livewire)
 ├─ Dashboard mode → RealTimeKPIService::getCurrentWorkOrderStatus()
 └─ Analytics mode → OperationalKPIService::getSetupTimeAnalytics()
                                       └─ fetchSetupTimeDistribution()
```

## Data Sources

- `work_order_logs` provides the chronological status history used to measure setup duration. Start entries inside the selected window are the anchor (`status = 'Start'`), and the preceding setup candidate is resolved inside `fetchSetupTimeDistribution()` (`app/Services/KPI/OperationalKPIService.php:1122`).
- When no explicit `Setup` status exists before a start, the code falls back to the last `Assigned` log for backward compatibility with legacy data (`app/Services/KPI/OperationalKPIService.php:1130`).
- `work_orders` supplies machine and factory scope as well as start-time metadata (`app/Services/KPI/OperationalKPIService.php:1168`).
- `machines` enrich the machine breakdown with names and asset identifiers (`app/Services/KPI/OperationalKPIService.php:1192`).

## Calculation Flow

1. **Determine the date window** – `getSetupTimeAnalytics()` normalises the requested period (predefined, comparison or custom) using `getDateRange()` and builds a cache key (`app/Services/KPI/OperationalKPIService.php:1055`).
2. **Collect candidate start logs** – All `Start` transitions within the window are pulled once and processed in chronological order (`app/Services/KPI/OperationalKPIService.php:1112`).
3. **Resolve setup anchor** – For each start, the service locates the most recent `Setup` log; if the work order has never logged a `Setup`, it reuses the prior `Assigned` event (`app/Services/KPI/OperationalKPIService.php:1124`).
4. **Guard rails** – Entries are dropped when the setup timestamp falls outside the window, when a second `Start` occurs before a new setup (preventing double-counting), or when duration becomes negative (`app/Services/KPI/OperationalKPIService.php:1144`).
5. **Duration & aggregation** – Valid pairs compute minutes between setup and start. Aggregates update both the daily bucket and machine bucket, along with running totals (`app/Services/KPI/OperationalKPIService.php:1188`).
6. **Summaries** – After iteration, the service converts totals into hours, averages, min/max, and the % of an eight-hour shift (`app/Services/KPI/OperationalKPIService.php:1256`).
7. **Comparison (optional)** – When enabled, a matching distribution is generated for the comparator window and diffed via `calculateSetupTimeComparison()` (`app/Services/KPI/OperationalKPIService.php:1285`).

## Filters & Comparison Options

- `time_period`, `date_from`, and `date_to` drive the primary window selection (`app/Services/KPI/OperationalKPIService.php:1051`).
- `machine_id` limits the aggregation to a single machine and is applied just before roll-up (`app/Services/KPI/OperationalKPIService.php:1168`).
- `enable_comparison` plus `comparison_type` trigger the secondary window and structured comparison payload (`app/Services/KPI/OperationalKPIService.php:1079`).
- Downstream consumers receive a consistent shape: `primary_period`, optional `comparison_period`, and `comparison_analysis`, each carrying summaries plus daily and machine breakdowns.

## Dashboard Mode (Real-Time)

- When the KPI is viewed in dashboard mode, the card pulls from the existing work-order status feed returned by `RealTimeKPIService::getCurrentWorkOrderStatus()` (`app/Services/KPI/RealTimeKPIService.php:384`), where setup entries include `setup_since` and derived durations (`app/Services/KPI/RealTimeKPIService.php:434`).
- `KPIAnalyticsDashboard` reuses that payload to calculate active setup counts and durations on the fly before rendering (`resources/views/filament/admin/pages/kpi-analytics-dashboard.blade.php:1741`).
- This keeps live monitoring lightweight: no historical queries are executed; the card simply summarises current setups and lists affected work orders.

## Analytics UI Rendering

- The analytics partial displays summary cards, a daily table, and a machine leaderboard using the hydrated dataset (`resources/views/filament/admin/pages/setup-time-analytics.blade.php:49`).
- Helper formatting converts minute counts into human-readable strings at render time (`resources/views/filament/admin/pages/setup-time-analytics.blade.php:16`).
- The table honours the primary period selection while optionally rendering comparison insights alongside the summary block.

## Caching Strategy

- Every analytics request is memoised under the key `setup_time_analytics_v2_{period}_{hash}` (`app/Services/KPI/OperationalKPIService.php:1061`).
- TTL is determined by `BaseKPIService::getCacheTTL()` (e.g., yesterday = 1 hour, 30d = 30 minutes) to balance freshness and query cost (`app/Services/KPI/BaseKPIService.php:197`).
- Manual refresh from the UI triggers cache invalidation through the standard KPI dashboard refresh action.

## Testing & Validation

- **Baseline aggregation** – Verifies totals, averages, and machine ordering for a mixed dataset (`tests/Feature/SetupTimeKPITest.php:38`).
- **Machine filter** – Confirms that `machine_id` scopes both counts and summaries correctly (`tests/Feature/SetupTimeKPITest.php:160`).
- **Comparison windows** – Ensures previous-period diffs and status flags reflect lower-is-better semantics (`tests/Feature/SetupTimeKPITest.php:229`).
- **No data & guard rails** – Covers empty datasets, missing `Start` logs, and percentage derivation (`tests/Feature/SetupTimeKPITest.php:307`).

## Key Considerations

- Only the first `Start` after a setup is counted; subsequent restarts without a new setup are ignored to prevent duplicated durations (`app/Services/KPI/OperationalKPIService.php:1147`).
- The eight-hour shift denominator used for `avg_setup_percentage` can be revisited if factories provide custom calendars (`app/Services/KPI/OperationalKPIService.php:1268`).
- Legacy work orders that never logged a `Setup` state still participate through the `Assigned` fallback, but more granular insights rely on factories adopting the explicit `Setup` status.
