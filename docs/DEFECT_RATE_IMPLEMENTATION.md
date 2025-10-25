# Defect Rate KPI Implementation Guide

**Status:** Active (Dashboard + Analytics)  
**Last Updated:** October 2025

## Table of Contents
1. [Overview](#overview)
2. [KPI Definition](#kpi-definition)
3. [Architecture](#architecture)
4. [Data Sources](#data-sources)
5. [Dashboard Mode](#dashboard-mode)
6. [Analytics Mode](#analytics-mode)
7. [Caching Strategy](#caching-strategy)
8. [User Interface](#user-interface)
9. [Testing](#testing)
10. [Future Enhancements](#future-enhancements)

---

## Overview

- Defect Rate measures the percentage of scrapped (“KO”) units out of the total produced (OK + KO) for each work order.  
- The KPI is registered under the **Quality KPIs** catalog with Tier 2 visibility so it can be accessed from the KPI hub (`app/Services/KPI/KPIRegistry.php:103`).  
- Both **Dashboard** (real-time, today-only) and **Analytics** (historical) modes are implemented, following the same Livewire + service architecture as other Tier 2 KPIs.

## KPI Definition

- **Per Work Order Defect Rate**
  ```
  defect_rate = scrapped_qtys / (ok_qtys + scrapped_qtys)
  ```
  Division-by-zero is avoided by skipping work orders that have not produced any units.
- **Dashboard focus:** spot work orders that are currently running (`status = 'Start'`) and have logged scrap today.
- **Analytics focus:** analyse historical scrap trends by day, machine, and individual work order.

## Architecture

```
KPIAnalyticsDashboard (Livewire)
 ├─ Dashboard mode → RealTimeKPIService::getCurrentDefectRate()
 └─ Analytics mode → OperationalKPIService::getDefectRateAnalytics()
                                       └─ fetchDefectRateDistribution()
```

- `RealTimeKPIService::getCurrentDefectRate()` gathers today-only scrap activity for live monitoring (`app/Services/KPI/RealTimeKPIService.php:520`).  
- `OperationalKPIService::getDefectRateAnalytics()` orchestrates historical aggregation, comparison periods, and caching (`app/Services/KPI/OperationalKPIService.php:1340`).  
- `fetchDefectRateDistribution()` contains the aggregation logic for daily, machine, and work-order breakdowns (`app/Services/KPI/OperationalKPIService.php:1396`).  
- `KPIAnalyticsDashboard` wires the service responses into Livewire state (`app/Filament/Admin/Pages/KPIAnalyticsDashboard.php:605`) and supplies pagination helpers for the dashboard table (`app/Filament/Admin/Pages/KPIAnalyticsDashboard.php:668`).

## Data Sources

- `work_order_logs` is the authoritative record for daily OK/KO quantities. Both services aggregate log rows with `(ok_qtys + scrapped_qtys) > 0`.  
- `work_orders` provides context (machine, operator, running status, cumulative totals).  
- `machines` and `operators` are eager-loaded for display metadata.  
- Dashboard mode filters work orders to the tenant’s factory, currently `status = 'Start'`, and `start_time = today` before considering logs.

## Dashboard Mode

- `getCurrentDefectRate()` queries today’s logs, grouping by work order and summing `ok_qtys` and `scrapped_qtys` for the day (`app/Services/KPI/RealTimeKPIService.php:526`).  
- Grouped results are filtered to running work orders that actually scrapped material today using a `HAVING SUM(scrapped_qtys) > 0` clause.  
- For each qualifying work order, the method:
  - Calculates today’s defect rate, cumulative defect rate, and elapsed runtime (`app/Services/KPI/RealTimeKPIService.php:569`).  
  - Captures the latest scrap timestamp to highlight recent issues (`app/Services/KPI/RealTimeKPIService.php:602`).  
- The summary block aggregates totals (scrap units, produced units, average and worst defect rate) for quick floor insights (`app/Services/KPI/RealTimeKPIService.php:640`).  
- Dashboard results are cached for five minutes under the key `current_defect_rate_dashboard_v1`.

## Analytics Mode

- `getDefectRateAnalytics()` resolves the requested date window, applies caching, and optionally loads a comparison period (`app/Services/KPI/OperationalKPIService.php:1340`).  
- `fetchDefectRateDistribution()` iterates through logs in the window to build:
  - **Daily metrics:** total OK, scrap, produced, number of work orders with scrap, and the worst offending WO for each day (`app/Services/KPI/OperationalKPIService.php:1523`).  
  - **Machine breakdown:** aggregated scrap/OK/produced quantities and defect rate per machine with counts of impacted work orders (`app/Services/KPI/OperationalKPIService.php:1560`).  
  - **Work-order breakdown:** totals and last scrap timestamp per work order, ordered by highest defect rate (`app/Services/KPI/OperationalKPIService.php:1578`).  
- Summary statistics (total scrap, produced, average defect rate, worst defect rate, counts) feed comparison analysis and summary cards (`app/Services/KPI/OperationalKPIService.php:1588`).  
- `calculateDefectRateComparison()` produces delta/trend metadata for scrap quantity, average defect rate, number of defective work orders, and total produced volume (`app/Services/KPI/OperationalKPIService.php:1616`).

## Caching Strategy

- Dashboard mode leverages the existing tiered tenant cache for five-minute freshness.  
- Analytics mode uses the standard `getCacheTTL()` period-specific TTLs with cache keys in the form `defect_rate_analytics_{period}_{hash}`.  
- Manual refresh (`Refresh` button) toggles Livewire’s `skipCache` flag so both dashboard and analytics calls can bypass cached data.

## User Interface

- **Dashboard card** (`resources/views/filament/admin/pages/kpi-analytics-dashboard.blade.php:1990`): summary chips plus a paginated table listing each active work order’s scrap activity, defect rate, and last scrap time. Pagination reuses the shared `machine-table-pagination` component via `gotoDefectPage`.  
- **Analytics view** (`resources/views/filament/admin/pages/defect-rate-analytics.blade.php:1`):  
  - Summary cards for scrap, production, average/worst rates, and affected work orders.  
  - Optional comparison grid when `enable_comparison` is set.  
  - Daily, machine, and work-order tables mirroring the data slices returned by the service.

## Testing

- Automated feature tests should cover:
  - Dashboard filtering (only running work orders with scrap logged today).  
  - Daily and machine aggregations for analytics mode.  
  - Comparison math accuracy.  
  - Zero-data scenarios.  
- Tests are being added under `tests/Feature/DefectRateKPITest.php` (pending in the implementation plan).

## Future Enhancements

- Support additional filters (product, operator, shift) in analytics requests.  
- Surface scrap reasons by joining `scrapped_reason_id` for richer diagnostics.  
- Expose trend charts (line/bar) in the analytics UI to complement tabular views.  
- Integrate alerts or thresholds to flag high defect rate work orders in dashboard mode.
