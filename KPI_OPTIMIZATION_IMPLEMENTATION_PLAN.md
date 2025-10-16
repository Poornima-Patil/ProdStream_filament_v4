# KPI Performance Optimization - Complete Implementation Plan

**Project:** ProdStream Manufacturing KPI System
**Version:** 3.0
**Date:** October 13, 2025
**Target:** 90+ KPIs with Multi-Tenancy Support
**Architecture:** Flexible (Shared DB → Database-Per-Factory as you scale)

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current State Analysis](#current-state-analysis)
3. [Proposed Architecture](#proposed-architecture)
4. [Scalability Strategy: Shared DB to Database-Per-Factory](#scalability-strategy)
5. [Database Schema Design](#database-schema-design)
6. [Redis Cache Configuration](#redis-cache-configuration)
7. [Implementation Roadmap](#implementation-roadmap)
8. [Code Structure & Design Patterns](#code-structure--design-patterns)
9. [Performance Optimization Techniques](#performance-optimization-techniques)
10. [Multi-Tenancy Strategy](#multi-tenancy-strategy)
11. [Report Generation System](#report-generation-system)
12. [Testing Strategy](#testing-strategy)
13. [Deployment Plan](#deployment-plan)
14. [Monitoring & Maintenance](#monitoring--maintenance)
15. [Performance Projections](#performance-projections)
16. [Risk Mitigation](#risk-mitigation)
17. [Migration Path: Shared to Separate Databases](#migration-path)

---

## Executive Summary

### Project Goals

Transform the current KPI system to support **90+ manufacturing KPIs** across multiple factories with optimal performance for 20-30 concurrent manager/admin users per factory.

### Key Objectives

1. **Performance:** Reduce dashboard load time from 4-8s to <1s
2. **Scalability:** Support 100+ concurrent users per factory
3. **Efficiency:** Reduce database queries by 95% (600 → 30 queries)
4. **User Experience:** Role-based KPI delivery with progressive loading
5. **Automation:** Scheduled reports (daily/weekly/monthly)

### Approach

**3-Tier Hybrid Architecture:**
- **Tier 1:** 18 real-time KPIs (manual refresh on-demand, with cache bypass)
- **Tier 2:** 28 shift-based KPIs (calculated after each shift)
- **Tier 3:** 44 report-based KPIs (scheduled generation)

### Technology Stack

- **Cache:** Redis (free with Laravel Cloud) - 10-100x faster than database cache
- **Data:** Summary tables for pre-aggregated metrics
- **Jobs:** Laravel queue system for background processing
- **Reports:** PDF/Excel generation with email delivery

### Expected Outcomes

| Metric | Current | Target | Improvement |
|--------|---------|--------|-------------|
| Dashboard load | 4-8s | <1s | **8x faster** |
| Queries per page | 400-600 | 10-30 | **95% reduction** |
| Cache hit rate | 60-70% | 95-99% | **40% improvement** |
| Concurrent users | 10-15 | 100+ | **10x capacity** |
| Server load | Very High | Low | **80% reduction** |
| Storage growth (5yr) | 32M rows | 2.4M rows | **92% reduction** |

---

## Current State Analysis

### Existing KPI Implementation

**Location:** `app/Services/KPIService.php`, `app/Services/CustomerKPIService.php`

**Current Features:**
- ✅ 3 active KPIs (Work Order Completion, Production Throughput, Scrap Rate)
- ✅ Database indexing implemented
- ✅ Basic caching (5-minute TTL, database driver)
- ✅ Multi-tenancy via factory_id
- ✅ Date range filtering
- ✅ Trend calculations

**Current Issues:**
- ❌ Database cache (slow, 10ms reads vs 0.3ms with Redis)
- ❌ No cache tags (difficult to invalidate by factory)
- ❌ No summary tables (complex queries on every request)
- ❌ Synchronous calculations (blocks UI)
- ❌ Limited to 3 KPIs (scaling to 90+ would be 15-30s load times)
- ❌ No report generation system
- ❌ Cache fragmentation (separate keys per date range)

### Performance Bottlenecks

1. **N+1 Query Problem**
   - Multiple eager loads: `work_orders -> bom -> purchaseOrder -> partNumber`
   - Each level adds 50-200ms

2. **Complex Aggregations**
   - GROUP BY with multiple joins
   - Calculating trends requires 2x queries (current + previous period)

3. **Cache Inefficiency**
   - Database cache requires DB connection
   - No tag-based invalidation
   - Per-user/per-date-range caching causes fragmentation

4. **No Pre-Aggregation**
   - Every KPI calculated from raw data
   - Historical comparisons scan millions of rows

### Database Structure

**Current Tables:**
- `work_orders` (~500K+ rows, growing)
- `work_order_logs` (~2M+ rows)
- `boms`, `purchase_orders`, `machines`, `operators`
- Existing indexes on `factory_id`, `status`, `created_at`

---

## Proposed Architecture

### High-Level System Design

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER INTERFACE                           │
│  Operators Dashboard │ Manager Dashboard │ Executive Dashboard  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                   TIER 1: REAL-TIME KPIs (18)                   │
│  • Update: 1-5 min cache                                        │
│  • Source: work_orders + indexes                                │
│  • Storage: Redis                                               │
│  • Users: Operators, Supervisors                                │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                 TIER 2: SHIFT-BASED KPIs (28)                   │
│  • Update: After each shift (3x daily)                          │
│  • Source: kpi_shift_summaries table                            │
│  • Storage: Redis + MySQL                                       │
│  • Users: Managers, Planners                                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                TIER 3: SCHEDULED REPORTS (44)                   │
│  • Update: Daily/Weekly/Monthly                                 │
│  • Source: kpi_daily/weekly/monthly_summaries                   │
│  • Storage: PDF/Excel files + email                             │
│  • Users: Executives, Owners                                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                      DATA LAYER                                 │
│  Redis Cache │ Summary Tables │ Source Tables │ File Storage    │
└─────────────────────────────────────────────────────────────────┘
```

### Data Flow

```
Raw Data (work_orders, machines, operators)
    ↓
[Shift End Jobs] → kpi_shift_summaries → Redis Cache → Tier 2 Dashboard
    ↓
[Daily Jobs] → kpi_daily_summaries → Redis Cache → Reports
    ↓
[Weekly Jobs] → kpi_weekly_summaries → PDF/Excel → Email + Storage
    ↓
[Monthly Jobs] → kpi_monthly_aggregates → Strategic Reports → Executives
```

### Cache Strategy

```
┌─── Factory 1 ────────────────────────────────────────┐
│  Redis Tags: ['factory_1', 'tier_1', 'realtime']    │
│  TTL: 1-5 minutes                                    │
│  Keys: factory_1::tier_1::machine_status            │
│        factory_1::tier_1::work_order_status         │
└──────────────────────────────────────────────────────┘

┌─── Factory 1 ────────────────────────────────────────┐
│  Redis Tags: ['factory_1', 'tier_2', 'shift']       │
│  TTL: Until next shift                               │
│  Keys: factory_1::tier_2::shift_2025-10-08_day      │
└──────────────────────────────────────────────────────┘

┌─── Factory 1 ────────────────────────────────────────┐
│  Redis Tags: ['factory_1', 'tier_3', 'historical']  │
│  TTL: 24 hours                                       │
│  Keys: factory_1::tier_3::monthly_2025-10           │
└──────────────────────────────────────────────────────┘
```

---

## Scalability Strategy: Shared DB to Database-Per-Factory

### Overview

**Start Simple, Scale Smart:** Begin with a shared database for initial factories (1-10), then migrate to database-per-factory as you grow. This approach balances simplicity and scalability.

### Phase 1: Shared Database (1-10 Factories)

**Architecture:**
```
┌─────────────────────────────────────────┐
│     Single Shared Database              │
│     (prodstream_main)                   │
├─────────────────────────────────────────┤
│  Tables with factory_id column:         │
│  • work_orders (factory_id)             │
│  • machines (factory_id)                │
│  • operators (factory_id)               │
│  • kpi_machine_daily (factory_id)       │
│  • kpi_operator_daily (factory_id)      │
│  • kpi_part_daily (factory_id)          │
└─────────────────────────────────────────┘
```

**Benefits:**
- ✅ Simple initial setup
- ✅ Easy cross-factory queries
- ✅ Single migration/backup process
- ✅ Lower hosting costs

**Trade-offs:**
- ⚠️ All factories share DB resources
- ⚠️ Must always filter by factory_id
- ⚠️ Risk of data leaks if scope forgotten

**When to use:**
- Starting out with 1-10 factories
- All factories roughly same size
- Limited DevOps resources
- Need cross-factory reporting

### Phase 2: Database-Per-Factory (10+ Factories)

**Architecture:**
```
┌──────────────────────────────────────────┐
│   Central Database (prodstream_central)  │
│   • users, factories, roles, permissions │
└──────────────────────────────────────────┘
                ↓
    ┌───────────┼───────────┐
    ↓           ↓           ↓
┌─────────┐ ┌─────────┐ ┌─────────┐
│Factory 1│ │Factory 2│ │Factory 3│
│Database │ │Database │ │Database │
├─────────┤ ├─────────┤ ├─────────┤
│• work_  │ │• work_  │ │• work_  │
│  orders │ │  orders │ │  orders │
│• KPIs   │ │• KPIs   │ │• KPIs   │
│(NO      │ │(NO      │ │(NO      │
│factory  │ │factory  │ │factory  │
│_id!)    │ │_id!)    │ │_id!)    │
└─────────┘ └─────────┘ └─────────┘

Same code, different DB connection per request
```

**Benefits:**
- ✅ Complete data isolation
- ✅ No factory_id columns needed
- ✅ Smaller, faster queries
- ✅ Can scale individual factories
- ✅ Better security

**Trade-offs:**
- ⚠️ More complex migrations
- ⚠️ No easy cross-factory queries
- ⚠️ More backup/maintenance

**When to migrate:**
- Growing beyond 10 factories
- Individual factories have different sizes
- Need maximum data isolation
- Performance issues in shared DB

### Decision Matrix

| Factor | Shared DB (Phase 1) | Database-Per-Factory (Phase 2) |
|--------|---------------------|----------------------------------|
| **Factories** | 1-10 | 10+ |
| **Setup Complexity** | Low ✅ | Medium |
| **Query Performance** | Good (with indexes) | Excellent ✅ |
| **Data Isolation** | Requires careful scoping | Perfect ✅ |
| **Maintenance** | Simple ✅ | Complex |
| **Scalability** | Limited | Excellent ✅ |
| **Cost** | Lower ✅ | Higher |

### Code Abstraction Strategy

**Write code that works for BOTH architectures:**

```php
// app/Support/DatabaseManager.php
class DatabaseManager
{
    /**
     * Check if using database-per-factory
     */
    public static function usingTenantDatabases(): bool
    {
        return config('app.tenant_mode') === 'database_per_factory';
    }

    /**
     * Set connection for current factory
     */
    public static function setFactoryConnection(?int $factoryId = null): void
    {
        $factoryId = $factoryId ?? Auth::user()?->factory_id;

        if (!$factoryId) {
            return;
        }

        if (static::usingTenantDatabases()) {
            // Phase 2: Switch to factory-specific database
            Config::set('database.default', "factory_{$factoryId}");
            DB::purge("factory_{$factoryId}");
            DB::reconnect("factory_{$factoryId}");
        } else {
            // Phase 1: Use shared database (global scope handles factory_id)
            // No action needed
        }
    }
}

// Models work in BOTH modes
class WorkOrder extends Model
{
    // Phase 1: Global scope filters by factory_id
    protected static function booted()
    {
        if (!DatabaseManager::usingTenantDatabases()) {
            static::addGlobalScope('factory', function (Builder $builder) {
                if (Auth::check() && Auth::user()->factory_id) {
                    $builder->where('factory_id', Auth::user()->factory_id);
                }
            });
        }
        // Phase 2: No scope needed, DB connection IS the boundary
    }
}
```

### Migration Timeline

**When you hit 10-15 factories:**

```
Week 1: Preparation
├─ Audit code for factory_id dependencies
├─ Create migration scripts
└─ Test on staging environment

Week 2: Pilot Migration (1-2 factories)
├─ Migrate 2 smaller factories
├─ Test thoroughly
└─ Monitor performance

Week 3-4: Gradual Migration
├─ Migrate 3-5 factories per week
├─ Keep shared DB as fallback
└─ Monitor stability

Week 5: Complete Migration
├─ Migrate remaining factories
├─ Update config: tenant_mode = 'database_per_factory'
└─ Decommission shared operational DB
```

### Storage Growth Comparison

**Shared DB (10 Factories):**
```
After 1 year:
- kpi_machine_daily: 180,000 rows (10 factories × 90 days × 200 machines)
- kpi_operator_daily: 360,000 rows
- Total: ~1.3M rows in shared DB
```

**Database-Per-Factory (10 Factories):**
```
After 1 year:
- Factory 1 DB: ~65,000 rows
- Factory 2 DB: ~65,000 rows
- ...
- Factory 10 DB: ~65,000 rows
- Total: Still 650K rows, but isolated! ✅
```

### Configuration Management

**.env configuration:**
```env
# Phase 1: Shared Database
TENANT_MODE=shared_database
DB_CONNECTION=mysql
DB_DATABASE=prodstream_main

# Phase 2: Database-Per-Factory
TENANT_MODE=database_per_factory
DB_CONNECTION=mysql
# Central DB for users/factories
DB_CENTRAL_DATABASE=prodstream_central
# Factory DBs created dynamically: prodstream_factory_1, etc.
```

**config/app.php:**
```php
return [
    // ...
    'tenant_mode' => env('TENANT_MODE', 'shared_database'),
    // Options: 'shared_database' or 'database_per_factory'
];
```

### Recommended Thresholds for Migration

| Trigger | Threshold | Action |
|---------|-----------|--------|
| **Factory Count** | 10-15 factories | Start planning migration |
| **DB Size** | >10 GB | Consider splitting |
| **Query Time** | >500ms average | Investigate migration |
| **Concurrent Users** | >100 per factory | Definitely migrate |
| **Different Factory Sizes** | 10x variance | Migrate large ones first |

---

## Database Schema Design

### Storage Strategy: Hybrid Granularity with Data Lifecycle

**Key Principle:** Store granular data for recent periods only, then automatically aggregate to coarser granularities for long-term storage.

#### Storage Tiers

| Tier | Granularity | Retention Period | Purpose | Growth Rate |
|------|-------------|------------------|---------|-------------|
| **Daily** | Machine/Operator/Part per day | Last 90 days | Detailed analysis | 354 rows/day/factory |
| **Weekly** | Machine/Operator/Part per week | 91 days - 1 year | Medium-term trends | 52 rows/year/machine |
| **Monthly** | Machine/Operator/Part per month | 1+ years | Long-term archive | 12 rows/year/machine |

#### Storage Growth Projection (1 Factory, 5 Years)

| Approach | Year 1 | Year 3 | Year 5 | Total (50 factories) |
|----------|--------|--------|--------|----------------------|
| **All Daily Forever** | 129K | 387K | 645K | **32M rows** ❌ |
| **Hybrid Lifecycle** | 35K | 42K | 48K | **2.4M rows** ✅ |
| **Savings** | 73% | 89% | 92% | **92% reduction** |

#### Why This Approach Works

1. **Recent data is detailed** - Last 90 days at daily granularity for precise analysis
2. **Historical data is summarized** - Older data aggregated to weekly/monthly
3. **Automatic archival** - Scheduled jobs handle lifecycle management
4. **Flexible queries** - Users can request any time period with user-selectable date ranges
5. **Manageable storage** - Linear growth instead of exponential

### Summary Tables Architecture

#### 1. kpi_shift_summaries

**Purpose:** Store pre-calculated KPIs for each shift

```sql
CREATE TABLE kpi_shift_summaries (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    shift_id BIGINT UNSIGNED NOT NULL,
    shift_date DATE NOT NULL,
    shift_name VARCHAR(50) NOT NULL, -- 'Day', 'Night', 'Evening'
    shift_start_time DATETIME NOT NULL,
    shift_end_time DATETIME NOT NULL,

    -- Work Order Metrics
    total_orders INT UNSIGNED DEFAULT 0,
    completed_orders INT UNSIGNED DEFAULT 0,
    in_progress_orders INT UNSIGNED DEFAULT 0,
    assigned_orders INT UNSIGNED DEFAULT 0,
    hold_orders INT UNSIGNED DEFAULT 0,
    closed_orders INT UNSIGNED DEFAULT 0,
    completion_rate DECIMAL(5,2) DEFAULT 0,

    -- Production Metrics
    total_units_produced INT UNSIGNED DEFAULT 0,
    ok_units INT UNSIGNED DEFAULT 0,
    scrapped_units INT UNSIGNED DEFAULT 0,
    scrap_rate DECIMAL(5,2) DEFAULT 0,
    throughput_per_hour DECIMAL(10,2) DEFAULT 0,

    -- Time Metrics
    total_production_hours DECIMAL(10,2) DEFAULT 0,
    total_downtime_hours DECIMAL(10,2) DEFAULT 0,
    average_cycle_time DECIMAL(10,2) DEFAULT 0,

    -- Quality Metrics
    first_pass_yield DECIMAL(5,2) DEFAULT 0,
    quality_rate DECIMAL(5,2) DEFAULT 0,
    defect_count INT UNSIGNED DEFAULT 0,

    -- Efficiency Metrics
    operator_efficiency DECIMAL(5,2) DEFAULT 0,
    machine_utilization DECIMAL(5,2) DEFAULT 0,
    schedule_adherence DECIMAL(5,2) DEFAULT 0,

    -- Planning Metrics
    setup_time_hours DECIMAL(10,2) DEFAULT 0,
    changeover_count INT UNSIGNED DEFAULT 0,
    changeover_efficiency DECIMAL(5,2) DEFAULT 0,

    -- Metadata
    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_factory_shift (factory_id, shift_id),
    INDEX idx_factory_shift_date (factory_id, shift_date),
    INDEX idx_shift_date (shift_date),
    INDEX idx_calculated (calculated_at),

    -- Foreign Keys
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. kpi_daily_summaries

**Purpose:** Store daily aggregated KPIs across all shifts

```sql
CREATE TABLE kpi_daily_summaries (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    summary_date DATE NOT NULL,

    -- Work Order Metrics
    total_orders INT UNSIGNED DEFAULT 0,
    completed_orders INT UNSIGNED DEFAULT 0,
    in_progress_orders INT UNSIGNED DEFAULT 0,
    assigned_orders INT UNSIGNED DEFAULT 0,
    hold_orders INT UNSIGNED DEFAULT 0,
    closed_orders INT UNSIGNED DEFAULT 0,
    completion_rate DECIMAL(5,2) DEFAULT 0,

    -- Production Metrics
    total_units_produced INT UNSIGNED DEFAULT 0,
    ok_units INT UNSIGNED DEFAULT 0,
    scrapped_units INT UNSIGNED DEFAULT 0,
    scrap_rate DECIMAL(5,2) DEFAULT 0,
    throughput_per_day DECIMAL(10,2) DEFAULT 0,

    -- Time Metrics
    total_production_hours DECIMAL(10,2) DEFAULT 0,
    total_downtime_hours DECIMAL(10,2) DEFAULT 0,
    average_cycle_time DECIMAL(10,2) DEFAULT 0,

    -- Quality Metrics
    first_pass_yield DECIMAL(5,2) DEFAULT 0,
    quality_rate DECIMAL(5,2) DEFAULT 0,
    defect_count INT UNSIGNED DEFAULT 0,

    -- Efficiency Metrics
    oee DECIMAL(5,2) DEFAULT 0, -- Overall Equipment Effectiveness
    operator_efficiency DECIMAL(5,2) DEFAULT 0,
    machine_utilization DECIMAL(5,2) DEFAULT 0,
    capacity_utilization DECIMAL(5,2) DEFAULT 0,

    -- Delivery Metrics
    on_time_delivery_rate DECIMAL(5,2) DEFAULT 0,
    orders_delivered INT UNSIGNED DEFAULT 0,
    orders_delayed INT UNSIGNED DEFAULT 0,

    -- Planning Metrics
    bom_utilization_rate DECIMAL(5,2) DEFAULT 0,
    so_to_wo_conversion_rate DECIMAL(5,2) DEFAULT 0,
    planning_accuracy DECIMAL(5,2) DEFAULT 0,

    -- Cost Metrics (optional for future)
    scrap_cost DECIMAL(12,2) DEFAULT 0,
    downtime_cost DECIMAL(12,2) DEFAULT 0,
    labor_cost DECIMAL(12,2) DEFAULT 0,

    -- Metadata
    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_factory_date (factory_id, summary_date),
    INDEX idx_factory_date (factory_id, summary_date),
    INDEX idx_date (summary_date),
    INDEX idx_calculated (calculated_at),

    -- Foreign Keys
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. kpi_machine_daily

**Purpose:** Machine-specific daily metrics

```sql
CREATE TABLE kpi_machine_daily (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    machine_id BIGINT UNSIGNED NOT NULL,
    summary_date DATE NOT NULL,

    -- Utilization Metrics
    utilization_rate DECIMAL(5,2) DEFAULT 0,
    uptime_hours DECIMAL(10,2) DEFAULT 0,
    downtime_hours DECIMAL(10,2) DEFAULT 0,
    planned_downtime_hours DECIMAL(10,2) DEFAULT 0,
    unplanned_downtime_hours DECIMAL(10,2) DEFAULT 0,

    -- Production Metrics
    units_produced INT UNSIGNED DEFAULT 0,
    work_orders_completed INT UNSIGNED DEFAULT 0,
    average_cycle_time DECIMAL(10,2) DEFAULT 0,

    -- Quality Metrics
    quality_rate DECIMAL(5,2) DEFAULT 0,
    scrap_rate DECIMAL(5,2) DEFAULT 0,
    first_pass_yield DECIMAL(5,2) DEFAULT 0,

    -- Performance Metrics
    machine_performance_index DECIMAL(5,2) DEFAULT 0,
    machine_reliability_score DECIMAL(5,2) DEFAULT 0,
    availability_rate DECIMAL(5,2) DEFAULT 0,

    -- Maintenance Metrics
    mtbf DECIMAL(10,2) DEFAULT 0, -- Mean Time Between Failures (hours)
    mttr DECIMAL(10,2) DEFAULT 0, -- Mean Time To Repair (hours)
    failure_count INT UNSIGNED DEFAULT 0,

    -- Metadata
    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_factory_machine_date (factory_id, machine_id, summary_date),
    INDEX idx_factory_date (factory_id, summary_date),
    INDEX idx_machine_date (machine_id, summary_date),
    INDEX idx_date (summary_date),

    -- Foreign Keys
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 4. kpi_operator_daily

**Purpose:** Operator-specific daily metrics

```sql
CREATE TABLE kpi_operator_daily (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    operator_id BIGINT UNSIGNED NOT NULL,
    summary_date DATE NOT NULL,

    -- Production Metrics
    work_orders_completed INT UNSIGNED DEFAULT 0,
    work_orders_assigned INT UNSIGNED DEFAULT 0,
    units_produced INT UNSIGNED DEFAULT 0,
    hours_worked DECIMAL(10,2) DEFAULT 0,

    -- Efficiency Metrics
    efficiency_rate DECIMAL(5,2) DEFAULT 0,
    productivity_score DECIMAL(5,2) DEFAULT 0,
    average_cycle_time DECIMAL(10,2) DEFAULT 0,

    -- Quality Metrics
    quality_rate DECIMAL(5,2) DEFAULT 0,
    first_pass_yield DECIMAL(5,2) DEFAULT 0,
    defect_count INT UNSIGNED DEFAULT 0,
    scrap_units INT UNSIGNED DEFAULT 0,

    -- Skill Metrics
    skill_level VARCHAR(50),
    proficiency_score DECIMAL(5,2) DEFAULT 0,
    training_hours DECIMAL(10,2) DEFAULT 0,

    -- Workload Metrics
    workload_balance_score DECIMAL(5,2) DEFAULT 0,
    overtime_hours DECIMAL(10,2) DEFAULT 0,

    -- Metadata
    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_factory_operator_date (factory_id, operator_id, summary_date),
    INDEX idx_factory_date (factory_id, summary_date),
    INDEX idx_operator_date (operator_id, summary_date),
    INDEX idx_date (summary_date),

    -- Foreign Keys
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 5. kpi_part_daily

**Purpose:** Part number performance tracking

```sql
CREATE TABLE kpi_part_daily (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    part_number_id BIGINT UNSIGNED NOT NULL,
    summary_date DATE NOT NULL,

    -- Production Metrics
    units_produced INT UNSIGNED DEFAULT 0,
    work_orders_count INT UNSIGNED DEFAULT 0,
    production_volume_percentage DECIMAL(5,2) DEFAULT 0,

    -- Quality Metrics
    quality_rate DECIMAL(5,2) DEFAULT 0,
    scrap_rate DECIMAL(5,2) DEFAULT 0,
    first_pass_yield DECIMAL(5,2) DEFAULT 0,
    defect_count INT UNSIGNED DEFAULT 0,

    -- Time Metrics
    average_cycle_time DECIMAL(10,2) DEFAULT 0,
    average_lead_time DECIMAL(10,2) DEFAULT 0,

    -- Fulfillment Metrics
    fulfillment_rate DECIMAL(5,2) DEFAULT 0,
    on_time_completion_rate DECIMAL(5,2) DEFAULT 0,

    -- Metadata
    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_factory_part_date (factory_id, part_number_id, summary_date),
    INDEX idx_factory_date (factory_id, summary_date),
    INDEX idx_part_date (part_number_id, summary_date),
    INDEX idx_date (summary_date),

    -- Foreign Keys
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (part_number_id) REFERENCES part_numbers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 6. kpi_machine_weekly

**Purpose:** Weekly aggregated machine metrics (for data 91 days - 1 year old)

```sql
CREATE TABLE kpi_machine_weekly (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    machine_id BIGINT UNSIGNED NOT NULL,
    week_start_date DATE NOT NULL,  -- Monday of the week
    week_end_date DATE NOT NULL,    -- Sunday of the week

    -- Aggregated Metrics (averaged or summed from daily)
    total_units_produced INT UNSIGNED DEFAULT 0,
    avg_utilization_rate DECIMAL(5,2) DEFAULT 0,
    avg_quality_rate DECIMAL(5,2) DEFAULT 0,
    total_uptime_hours DECIMAL(10,2) DEFAULT 0,
    total_downtime_hours DECIMAL(10,2) DEFAULT 0,
    days_in_week TINYINT UNSIGNED DEFAULT 7,

    -- Metadata
    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_factory_machine_week (factory_id, machine_id, week_start_date),
    INDEX idx_factory_week (factory_id, week_start_date),
    INDEX idx_week_start (week_start_date),

    -- Foreign Keys
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. kpi_operator_weekly

**Purpose:** Weekly aggregated operator metrics

```sql
CREATE TABLE kpi_operator_weekly (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    operator_id BIGINT UNSIGNED NOT NULL,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,

    total_work_orders_completed INT UNSIGNED DEFAULT 0,
    total_units_produced INT UNSIGNED DEFAULT 0,
    avg_efficiency_rate DECIMAL(5,2) DEFAULT 0,
    avg_quality_rate DECIMAL(5,2) DEFAULT 0,
    total_hours_worked DECIMAL(10,2) DEFAULT 0,
    days_worked TINYINT UNSIGNED DEFAULT 0,

    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_factory_operator_week (factory_id, operator_id, week_start_date),
    INDEX idx_factory_week (factory_id, week_start_date),

    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 8. kpi_part_weekly

**Purpose:** Weekly aggregated part metrics

```sql
CREATE TABLE kpi_part_weekly (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    part_number_id BIGINT UNSIGNED NOT NULL,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,

    total_units_produced INT UNSIGNED DEFAULT 0,
    avg_quality_rate DECIMAL(5,2) DEFAULT 0,
    avg_scrap_rate DECIMAL(5,2) DEFAULT 0,
    total_work_orders INT UNSIGNED DEFAULT 0,
    days_in_week TINYINT UNSIGNED DEFAULT 0,

    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_factory_part_week (factory_id, part_number_id, week_start_date),
    INDEX idx_factory_week (factory_id, week_start_date),

    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (part_number_id) REFERENCES part_numbers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 9. kpi_machine_monthly

**Purpose:** Monthly aggregated machine metrics (for data 1+ year old)

```sql
CREATE TABLE kpi_machine_monthly (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    machine_id BIGINT UNSIGNED NOT NULL,
    year INT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,

    total_units_produced INT UNSIGNED DEFAULT 0,
    avg_utilization_rate DECIMAL(5,2) DEFAULT 0,
    avg_quality_rate DECIMAL(5,2) DEFAULT 0,
    total_uptime_hours DECIMAL(10,2) DEFAULT 0,
    total_downtime_hours DECIMAL(10,2) DEFAULT 0,
    days_in_month TINYINT UNSIGNED DEFAULT 0,

    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_factory_machine_month (factory_id, machine_id, year, month),
    INDEX idx_factory_month (factory_id, year, month),

    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 10. kpi_operator_monthly

**Purpose:** Monthly aggregated operator metrics

```sql
CREATE TABLE kpi_operator_monthly (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    operator_id BIGINT UNSIGNED NOT NULL,
    year INT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,

    total_work_orders_completed INT UNSIGNED DEFAULT 0,
    total_units_produced INT UNSIGNED DEFAULT 0,
    avg_efficiency_rate DECIMAL(5,2) DEFAULT 0,
    avg_quality_rate DECIMAL(5,2) DEFAULT 0,
    total_hours_worked DECIMAL(10,2) DEFAULT 0,
    days_worked TINYINT UNSIGNED DEFAULT 0,

    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_factory_operator_month (factory_id, operator_id, year, month),
    INDEX idx_factory_month (factory_id, year, month),

    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 11. kpi_part_monthly

**Purpose:** Monthly aggregated part metrics

```sql
CREATE TABLE kpi_part_monthly (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    part_number_id BIGINT UNSIGNED NOT NULL,
    year INT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,

    total_units_produced INT UNSIGNED DEFAULT 0,
    avg_quality_rate DECIMAL(5,2) DEFAULT 0,
    avg_scrap_rate DECIMAL(5,2) DEFAULT 0,
    total_work_orders INT UNSIGNED DEFAULT 0,
    days_in_month TINYINT UNSIGNED DEFAULT 0,

    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_factory_part_month (factory_id, part_number_id, year, month),
    INDEX idx_factory_month (factory_id, year, month),

    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (part_number_id) REFERENCES part_numbers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 12. kpi_monthly_aggregates

**Purpose:** Factory-level monthly aggregates (immutable after month ends)

```sql
CREATE TABLE kpi_monthly_aggregates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    year INT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,

    -- Aggregated from daily summaries
    avg_completion_rate DECIMAL(5,2) DEFAULT 0,
    avg_throughput DECIMAL(10,2) DEFAULT 0,
    avg_quality_rate DECIMAL(5,2) DEFAULT 0,
    avg_oee DECIMAL(5,2) DEFAULT 0,

    -- Totals
    total_units_produced INT UNSIGNED DEFAULT 0,
    total_work_orders INT UNSIGNED DEFAULT 0,
    total_production_hours DECIMAL(12,2) DEFAULT 0,
    total_downtime_hours DECIMAL(12,2) DEFAULT 0,

    -- Strategic Metrics
    capacity_utilization DECIMAL(5,2) DEFAULT 0,
    planning_efficiency_score DECIMAL(5,2) DEFAULT 0,
    customer_satisfaction_score DECIMAL(5,2) DEFAULT 0,

    -- Financial (optional)
    total_scrap_cost DECIMAL(12,2) DEFAULT 0,
    total_labor_cost DECIMAL(12,2) DEFAULT 0,
    revenue_per_hour DECIMAL(12,2) DEFAULT 0,

    -- Metadata
    is_finalized BOOLEAN DEFAULT FALSE,
    finalized_at TIMESTAMP NULL,
    calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_factory_year_month (factory_id, year, month),
    INDEX idx_factory_date (factory_id, year, month),
    INDEX idx_year_month (year, month),
    INDEX idx_finalized (is_finalized),

    -- Foreign Keys
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. kpi_reports

**Purpose:** Track generated reports

```sql
CREATE TABLE kpi_reports (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    factory_id BIGINT UNSIGNED NOT NULL,
    report_type VARCHAR(50) NOT NULL, -- 'daily', 'weekly', 'monthly'
    report_date DATE NOT NULL,

    -- File Information
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    file_size INT UNSIGNED,
    file_format VARCHAR(20), -- 'pdf', 'excel', 'csv'

    -- Report Content
    kpi_count INT UNSIGNED DEFAULT 0,
    page_count INT UNSIGNED DEFAULT 0,

    -- Generation Info
    generated_by BIGINT UNSIGNED,
    generation_started_at TIMESTAMP NULL,
    generation_completed_at TIMESTAMP NULL,
    generation_duration_seconds INT UNSIGNED,

    -- Delivery Info
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    recipients JSON,

    -- Status
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'generating', 'completed', 'failed'
    error_message TEXT,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_factory_type_date (factory_id, report_type, report_date),
    INDEX idx_factory_status (factory_id, status),
    INDEX idx_report_date (report_date),
    INDEX idx_generated_at (generation_completed_at),

    -- Foreign Keys
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Migration Strategy

Create migrations in order:

```bash
# Daily granularity tables (recent data - 90 days)
php artisan make:migration create_kpi_shift_summaries_table
php artisan make:migration create_kpi_daily_summaries_table
php artisan make:migration create_kpi_machine_daily_table
php artisan make:migration create_kpi_operator_daily_table
php artisan make:migration create_kpi_part_daily_table

# Weekly granularity tables (91 days - 1 year)
php artisan make:migration create_kpi_machine_weekly_table
php artisan make:migration create_kpi_operator_weekly_table
php artisan make:migration create_kpi_part_weekly_table

# Monthly granularity tables (1+ years)
php artisan make:migration create_kpi_machine_monthly_table
php artisan make:migration create_kpi_operator_monthly_table
php artisan make:migration create_kpi_part_monthly_table
php artisan make:migration create_kpi_monthly_aggregates_table

# Reports metadata
php artisan make:migration create_kpi_reports_table
```

### Data Lifecycle Management Strategy

#### Automatic Data Archival Flow

```
┌─────────────────────────────────────────────────────────────┐
│                   DATA LIFECYCLE                             │
└─────────────────────────────────────────────────────────────┘

Day 1-90: DAILY tables (detailed granularity)
  ├─ kpi_machine_daily
  ├─ kpi_operator_daily
  └─ kpi_part_daily
           ↓
    [Monthly Job: Aggregate to Weekly]
           ↓
Day 91-365: WEEKLY tables (medium granularity)
  ├─ kpi_machine_weekly
  ├─ kpi_operator_weekly
  └─ kpi_part_weekly
           ↓
    [Monthly Job: Aggregate to Monthly]
           ↓
Year 1+: MONTHLY tables (coarse granularity)
  ├─ kpi_machine_monthly
  ├─ kpi_operator_monthly
  └─ kpi_part_monthly
```

#### Archive Job Implementation

Create `app/Console/Commands/KPI/ArchiveKPIDataCommand.php`:

```php
<?php

namespace App\Console\Commands\KPI;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArchiveKPIDataCommand extends Command
{
    protected $signature = 'kpi:archive {--factory=}';
    protected $description = 'Archive old KPI data to weekly/monthly tables';

    public function handle(): int
    {
        $this->info('Starting KPI data archival...');

        // Step 1: Aggregate daily → weekly (data older than 90 days)
        $this->aggregateDailyToWeekly();

        // Step 2: Aggregate weekly → monthly (data older than 1 year)
        $this->aggregateWeeklyToMonthly();

        // Step 3: Delete archived daily data
        $this->deleteDailyOlderThan90Days();

        // Step 4: Delete archived weekly data
        $this->deleteWeeklyOlderThan1Year();

        $this->info('✅ KPI data archival completed successfully');

        return Command::SUCCESS;
    }

    protected function aggregateDailyToWeekly(): void
    {
        $cutoffDate = Carbon::now()->subDays(90);

        $this->info("Aggregating daily data to weekly (older than {$cutoffDate->toDateString()})...");

        // Aggregate machine daily → weekly
        DB::statement("
            INSERT INTO kpi_machine_weekly (
                factory_id, machine_id, week_start_date, week_end_date,
                total_units_produced, avg_utilization_rate, avg_quality_rate,
                total_uptime_hours, total_downtime_hours, days_in_week, calculated_at
            )
            SELECT
                factory_id,
                machine_id,
                DATE(DATE_SUB(summary_date, INTERVAL WEEKDAY(summary_date) DAY)) as week_start,
                DATE(DATE_ADD(DATE_SUB(summary_date, INTERVAL WEEKDAY(summary_date) DAY), INTERVAL 6 DAY)) as week_end,
                SUM(units_produced) as total_units,
                AVG(utilization_rate) as avg_utilization,
                AVG(quality_rate) as avg_quality,
                SUM(uptime_hours) as total_uptime,
                SUM(downtime_hours) as total_downtime,
                COUNT(*) as days_in_week,
                NOW()
            FROM kpi_machine_daily
            WHERE summary_date < ?
            GROUP BY factory_id, machine_id, week_start
            ON DUPLICATE KEY UPDATE
                total_units_produced = VALUES(total_units_produced),
                avg_utilization_rate = VALUES(avg_utilization_rate),
                avg_quality_rate = VALUES(avg_quality_rate),
                total_uptime_hours = VALUES(total_uptime_hours),
                total_downtime_hours = VALUES(total_downtime_hours),
                days_in_week = VALUES(days_in_week),
                calculated_at = VALUES(calculated_at)
        ", [$cutoffDate]);

        // Repeat for operator and part tables...
        $this->info('✓ Daily → Weekly aggregation complete');
    }

    protected function aggregateWeeklyToMonthly(): void
    {
        $cutoffDate = Carbon::now()->subYear();

        $this->info("Aggregating weekly data to monthly (older than {$cutoffDate->toDateString()})...");

        DB::statement("
            INSERT INTO kpi_machine_monthly (
                factory_id, machine_id, year, month,
                total_units_produced, avg_utilization_rate, avg_quality_rate,
                total_uptime_hours, total_downtime_hours, days_in_month, calculated_at
            )
            SELECT
                factory_id,
                machine_id,
                YEAR(week_start_date) as year,
                MONTH(week_start_date) as month,
                SUM(total_units_produced) as total_units,
                AVG(avg_utilization_rate) as avg_utilization,
                AVG(avg_quality_rate) as avg_quality,
                SUM(total_uptime_hours) as total_uptime,
                SUM(total_downtime_hours) as total_downtime,
                SUM(days_in_week) as days_in_month,
                NOW()
            FROM kpi_machine_weekly
            WHERE week_start_date < ?
            GROUP BY factory_id, machine_id, year, month
            ON DUPLICATE KEY UPDATE
                total_units_produced = VALUES(total_units_produced),
                avg_utilization_rate = VALUES(avg_utilization_rate),
                avg_quality_rate = VALUES(avg_quality_rate),
                total_uptime_hours = VALUES(total_uptime_hours),
                total_downtime_hours = VALUES(total_downtime_hours),
                days_in_month = VALUES(days_in_month),
                calculated_at = VALUES(calculated_at)
        ", [$cutoffDate]);

        $this->info('✓ Weekly → Monthly aggregation complete');
    }

    protected function deleteDailyOlderThan90Days(): void
    {
        $cutoffDate = Carbon::now()->subDays(90);

        $deleted = DB::table('kpi_machine_daily')
            ->where('summary_date', '<', $cutoffDate)
            ->delete();

        $this->info("✓ Deleted {$deleted} old machine daily records");

        // Repeat for operator and part tables...
    }

    protected function deleteWeeklyOlderThan1Year(): void
    {
        $cutoffDate = Carbon::now()->subYear();

        $deleted = DB::table('kpi_machine_weekly')
            ->where('week_start_date', '<', $cutoffDate)
            ->delete();

        $this->info("✓ Deleted {$deleted} old machine weekly records");
    }
}
```

Schedule the archival job monthly:

```php
// In routes/console.php or bootstrap/app.php
Schedule::command('kpi:archive')->monthlyOn(1, '01:00');
```

---

## Redis Cache Configuration

### Setup Redis with Laravel Cloud

**Step 1: Update `.env`**

```env
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Use separate Redis databases for different cache types
REDIS_CACHE_DB=0
REDIS_KPI_DB=1
REDIS_REALTIME_DB=2
REDIS_QUEUE_DB=3
```

**Step 2: Configure `config/cache.php`**

```php
<?php

return [
    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [
        // Default Redis cache
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        // KPI-specific cache
        'kpi_cache' => [
            'driver' => 'redis',
            'connection' => 'kpi',
            'lock_connection' => 'default',
        ],

        // Real-time data cache
        'realtime_cache' => [
            'driver' => 'redis',
            'connection' => 'realtime',
            'lock_connection' => 'default',
        ],

        // Database fallback (for development)
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
            'lock_connection' => null,
        ],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),
];
```

**Step 3: Configure `config/database.php` Redis connections**

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '0'),
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '0'),
    ],

    // KPI cache connection
    'kpi' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_KPI_DB', '1'),
    ],

    // Real-time data connection
    'realtime' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_REALTIME_DB', '2'),
    ],

    // Queue connection
    'queue' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_QUEUE_DB', '3'),
    ],
],
```

### Multi-Tenant Cache Helper

Create `app/Support/TenantKPICache.php`:

```php
<?php

namespace App\Support;

use App\Models\Factory;
use Illuminate\Support\Facades\Cache;

class TenantKPICache
{
    protected Factory $factory;
    protected string $store;

    public function __construct(Factory $factory, string $store = 'kpi_cache')
    {
        $this->factory = $factory;
        $this->store = $store;
    }

    /**
     * Get cached value with multi-tenant isolation
     */
    public function get(string $key, string $tier, callable $callback, int $ttl): mixed
    {
        $fullKey = $this->buildKey($key, $tier);
        $tags = $this->buildTags($tier);

        return Cache::store($this->store)
            ->tags($tags)
            ->remember($fullKey, $ttl, $callback);
    }

    /**
     * Put value in cache
     */
    public function put(string $key, string $tier, mixed $value, int $ttl): bool
    {
        $fullKey = $this->buildKey($key, $tier);
        $tags = $this->buildTags($tier);

        return Cache::store($this->store)
            ->tags($tags)
            ->put($fullKey, $value, $ttl);
    }

    /**
     * Forget specific cache key
     */
    public function forget(string $key, string $tier): bool
    {
        $fullKey = $this->buildKey($key, $tier);
        return Cache::store($this->store)->forget($fullKey);
    }

    /**
     * Flush all KPIs for this factory
     */
    public function flush(): bool
    {
        return Cache::store($this->store)
            ->tags(["factory_{$this->factory->id}"])
            ->flush();
    }

    /**
     * Flush specific tier for this factory
     */
    public function flushTier(string $tier): bool
    {
        return Cache::store($this->store)
            ->tags(["factory_{$this->factory->id}", "tier_{$tier}"])
            ->flush();
    }

    /**
     * Build cache key with factory namespace
     */
    protected function buildKey(string $key, string $tier): string
    {
        return "factory_{$this->factory->id}::tier_{$tier}::{$key}";
    }

    /**
     * Build cache tags for multi-tenant isolation
     */
    protected function buildTags(string $tier): array
    {
        return [
            "factory_{$this->factory->id}",
            "tier_{$tier}",
            "kpi"
        ];
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        // Implementation depends on Redis commands
        return [
            'factory_id' => $this->factory->id,
            'keys_count' => 0, // Count keys for this factory
            'memory_usage' => 0, // Memory usage in bytes
        ];
    }
}
```

---

## Implementation Roadmap

### Phase 1: Foundation (Weeks 1-2)

#### Week 1: Database Infrastructure

**Day 1-2: Create Summary Tables**
- [ ] Create all 7 migration files
- [ ] Run migrations in development
- [ ] Verify indexes are created
- [ ] Create Eloquent models for summary tables

**Day 3-4: Redis Configuration**
- [ ] Update `.env` with Redis settings
- [ ] Configure `config/cache.php`
- [ ] Configure `config/database.php` Redis connections
- [ ] Test Redis connectivity
- [ ] Create TenantKPICache helper class

**Day 5: Base Infrastructure**
- [ ] Create `app/Services/KPI/` directory structure
- [ ] Create BaseKPIService abstract class
- [ ] Create KPIServiceProvider
- [ ] Setup queue configuration for jobs
- [ ] Create report storage directories

#### Week 2: Core Services

**Day 1-2: Aggregation Job Framework**
- [ ] Create `app/Jobs/KPI/` directory
- [ ] Create `AggregateShiftKPIs` job
- [ ] Create `AggregateDailyKPIs` job
- [ ] Create `AggregateMonthlyKPIs` job
- [ ] Setup Laravel scheduler commands

**Day 3-4: KPI Service Classes**
- [ ] Create `OperationalKPIService`
- [ ] Create `QualityKPIService`
- [ ] Create `MachineKPIService`
- [ ] Create `OperatorKPIService`
- [ ] Create `PlanningKPIService`
- [ ] Create `RealTimeKPIService`

**Day 5: Testing Infrastructure**
- [ ] Create test factories for summary tables
- [ ] Setup test Redis database
- [ ] Create base KPI test class
- [ ] Write initial unit tests

### Phase 2: Tier 1 - Real-Time KPIs (Week 3)

**Day 1: Core Real-Time KPIs (1-6)**
- [ ] Current Machine Status Dashboard
- [ ] Real-Time Production Status
- [ ] Downtime Alert System
- [ ] Machine Utilization Rate (Current)
- [ ] Work Order Status Distribution
- [ ] Work Order Aging

**Day 2: Operational Real-Time KPIs (7-12)**
- [ ] Operator Workload Distribution
- [ ] Schedule Adherence
- [ ] Production Volume (Today)
- [ ] Quality Issues (Active)
- [ ] Part Fulfillment Progress
- [ ] Resource Demand Forecasting (Current)

**Day 3: Planning Real-Time KPIs (13-18)**
- [ ] Planning Pipeline Health
- [ ] SO to WO Rate (Today)
- [ ] Assignment Status (Current)
- [ ] Work Progress (Active Orders)
- [ ] Timeline Pipeline (Current)
- [ ] Bottleneck Analysis (Real-Time)

**Day 4: Dashboard Integration**
- [ ] Update KPIDashboard Livewire component
- [ ] Add role-based filtering
- [ ] Implement auto-refresh (30s polling)
- [ ] Add skeleton loaders

**Day 5: Testing & Optimization**
- [ ] Write feature tests for all Tier 1 KPIs
- [ ] Performance testing (measure query times)
- [ ] Optimize slow queries
- [ ] Cache tuning

### Phase 3: Tier 2 - Shift-Based KPIs (Week 4)

**Day 1: Shift Calculation Job**
- [ ] Implement shift aggregation logic
- [ ] Calculate all 28 Tier 2 KPIs
- [ ] Store in `kpi_shift_summaries`
- [ ] Store machine-specific data in `kpi_machine_daily`
- [ ] Store operator-specific data in `kpi_operator_daily`

**Day 2: Work Order & Production KPIs (1-8)**
- [ ] Work Order Completion Rate
- [ ] Production Throughput
- [ ] Production Throughput Per Machine Group
- [ ] Scrap Rate
- [ ] Quality Rate by Part Number
- [ ] Quality Rate by Machine
- [ ] Quality Rate by Operator
- [ ] Quality Rate by Operator Proficiency

**Day 3: Machine & Operator KPIs (9-20)**
- [ ] Quality Rate by Machine Group
- [ ] Machine Group Utilization
- [ ] Machine WO Status Distribution
- [ ] Skill Level Distribution
- [ ] Operator Efficiency
- [ ] Part Number Performance
- [ ] Cycle Time Efficiency
- [ ] Lead Time Performance
- [ ] Shift Performance
- [ ] First Pass Yield
- [ ] Setup Time Analysis
- [ ] Changeover Efficiency

**Day 4: Downtime & Maintenance KPIs (21-28)**
- [ ] Downtime Analysis
- [ ] DownTime by Root Cause
- [ ] Machine Utilization by Time Period
- [ ] Machine Availability Rate
- [ ] Planned vs. Unplanned Downtime Ratio
- [ ] Current Machine Performance Index
- [ ] Part Performance (Volume and Yield)
- [ ] Production Volume by Part Number

**Day 5: Testing & Scheduler Setup**
- [ ] Schedule shift jobs (7 AM, 3 PM, 11 PM)
- [ ] Test shift aggregation end-to-end
- [ ] Write tests for all Tier 2 KPIs
- [ ] Create shift comparison dashboard

### Phase 4: Tier 3 - Reports (Weeks 5-6)

#### Week 5: Daily & Weekly Reports

**Day 1: Daily Report Framework**
- [ ] Create `GenerateDailyReport` job
- [ ] Setup PDF generation (Laravel Snappy or DomPDF)
- [ ] Setup Excel generation (Laravel Excel)
- [ ] Create report templates

**Day 2: Daily Report KPIs (12 KPIs)**
- [ ] Implement all 12 daily report KPIs
- [ ] Create aggregation from shift summaries
- [ ] Generate comparison data (vs yesterday)
- [ ] Format report layouts

**Day 3: Weekly Report Framework**
- [ ] Create `GenerateWeeklyReport` job
- [ ] Setup week-over-week comparisons
- [ ] Create weekly summary calculations

**Day 4: Weekly Report KPIs (16 KPIs)**
- [ ] Implement planning efficiency KPIs
- [ ] Implement customer delivery KPIs
- [ ] Create trend charts
- [ ] Format weekly report layouts

**Day 5: Email & Storage**
- [ ] Setup email delivery system
- [ ] Create report archive storage
- [ ] Build report download UI
- [ ] Schedule daily (2 AM) and weekly (Monday 3 AM) jobs

#### Week 6: Monthly Reports & Testing

**Day 1: Monthly Report Framework**
- [ ] Create `GenerateMonthlyReport` job
- [ ] Implement monthly aggregation logic
- [ ] Create month-over-month comparisons
- [ ] Setup report finalization process

**Day 2: Monthly Report KPIs (16 KPIs)**
- [ ] Implement strategic KPIs
- [ ] Implement forecasting accuracy KPIs
- [ ] Create executive dashboard summary
- [ ] Format monthly report layouts

**Day 3: Advanced Analytics**
- [ ] Bottleneck prediction accuracy
- [ ] Planning hub learning curve
- [ ] Resource optimization scores
- [ ] Capacity planning metrics

**Day 4: Report Archive & UI**
- [ ] Create reports archive page
- [ ] Add report filtering/search
- [ ] Add report regeneration feature
- [ ] Setup role-based access to reports

**Day 5: Comprehensive Testing**
- [ ] Test all report generation
- [ ] Test email delivery
- [ ] Performance testing (report generation time)
- [ ] User acceptance testing

### Phase 5: Optimization & Launch (Week 7)

**Day 1: Performance Tuning**
- [ ] Measure all KPI calculation times
- [ ] Optimize slow queries
- [ ] Tune Redis cache TTLs
- [ ] Database query optimization

**Day 2: Cache Warming**
- [ ] Implement cache warming jobs
- [ ] Schedule cache pre-population
- [ ] Test cache hit rates
- [ ] Monitor cache memory usage

**Day 3: Monitoring Setup**
- [ ] Setup Laravel Horizon for queue monitoring
- [ ] Add logging for KPI calculations
- [ ] Create performance dashboards
- [ ] Setup error alerts

**Day 4: Documentation**
- [ ] Update technical documentation
- [ ] Create user guides
- [ ] Document API endpoints
- [ ] Create troubleshooting guide

**Day 5: Production Deployment**
- [ ] Deploy to staging
- [ ] Run full test suite
- [ ] Performance validation
- [ ] Deploy to production

---

## Code Structure & Design Patterns

### Directory Structure

```
app/
├── Services/
│   └── KPI/
│       ├── BaseKPIService.php
│       ├── OperationalKPIService.php
│       ├── QualityKPIService.php
│       ├── MachineKPIService.php
│       ├── OperatorKPIService.php
│       ├── PlanningKPIService.php
│       ├── CustomerKPIService.php
│       └── RealTimeKPIService.php
├── Jobs/
│   └── KPI/
│       ├── AggregateShiftKPIs.php
│       ├── AggregateDailyKPIs.php
│       ├── AggregateWeeklyKPIs.php
│       ├── AggregateMonthlyKPIs.php
│       ├── GenerateDailyReport.php
│       ├── GenerateWeeklyReport.php
│       ├── GenerateMonthlyReport.php
│       └── WarmKPICache.php
├── Models/
│   └── KPI/
│       ├── ShiftSummary.php
│       ├── DailySummary.php
│       ├── MachineDaily.php
│       ├── OperatorDaily.php
│       ├── PartDaily.php
│       ├── MonthlyAggregate.php
│       └── Report.php
├── Support/
│   ├── TenantKPICache.php
│   └── KPI/
│       ├── KPICalculator.php
│       └── KPIFormatter.php
├── Console/
│   └── Commands/
│       └── KPI/
│           ├── AggregateShiftCommand.php
│           ├── AggregateDailyCommand.php
│           ├── WarmCacheCommand.php
│           └── GenerateReportCommand.php
└── Http/
    └── Controllers/
        └── KPI/
            ├── KPIDashboardController.php
            └── KPIReportController.php
```

### BaseKPIService Abstract Class

```php
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
     * Format date range for queries with smart table selection
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
            case 'mtd': // Month to date
                $startDate = Carbon::now()->startOfMonth();
                break;
            case 'ytd': // Year to date
                $startDate = Carbon::now()->startOfYear();
                break;
            default:
                $startDate = Carbon::now()->subDays(7);
        }

        return [$startDate, $endDate];
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
            return $baseTable . '_daily';
        } elseif ($daysSinceStart <= 365) {
            return $baseTable . '_weekly';
        } else {
            return $baseTable . '_monthly';
        }
    }

    /**
     * Get appropriate cache TTL based on period
     */
    protected function getCacheTTL(string $period): int
    {
        return match($period) {
            'today' => 300,        // 5 minutes
            '7d' => 900,          // 15 minutes
            '30d' => 1800,        // 30 minutes
            '90d', 'mtd' => 3600, // 1 hour
            'ytd' => 7200,        // 2 hours
            default => 1800
        };
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
```

### Universal Dual-Mode KPI Architecture

**Design Philosophy:** Every KPI has two complementary views to serve different user needs.

#### The Two Modes

**Dashboard Mode (Real-Time Tier 1)**
- Shows current snapshot of KPI "right now"
- No date filtering - current state only
- 60-second cache (1-minute refresh)
- Lightweight queries (active/current data)
- Auto-refresh with polling
- Use case: "What's happening NOW?"

**Analytics Mode (Historical Tier 2/3)**
- Shows historical trends and comparisons
- Rich date range filtering (presets + custom)
- 5-15 minute cache
- Queries pre-aggregated summary tables
- Comparison with previous periods
- Use case: "How has this changed over time?"

#### User Experience Flow

```
┌─────────────────────────────────────────────────────────────┐
│  KPI Name                       [Dashboard] [Analytics]      │
├─────────────────────────────────────────────────────────────┤
│  Dashboard Mode: Real-time snapshot                          │
│  - Auto-refreshes every minute                               │
│  - Shows current status/values                               │
│  - No date controls                                          │
│                                                               │
│  Analytics Mode: Historical analysis                         │
│  - Date range selector (Today, 7d, 30d, 90d, Custom)        │
│  - Comparison toggle (vs previous period)                    │
│  - Charts, trends, breakdown tables                          │
└─────────────────────────────────────────────────────────────┘
```

#### Analytics Mode Features

**1. Time Period Selector**
```php
// Preset options + custom date range
$timePeriodOptions = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'this_week' => 'This Week',
    'last_week' => 'Last Week',
    'this_month' => 'This Month',
    'last_month' => 'Last Month',
    '7d' => 'Last 7 Days',
    '14d' => 'Last 14 Days',
    '30d' => 'Last 30 Days (Default)',
    '60d' => 'Last 60 Days',
    '90d' => 'Last 90 Days',
    'this_quarter' => 'This Quarter',
    'this_year' => 'This Year',
    'custom' => 'Custom Date Range',
];
```

**2. Comparison Options**
```php
$comparisonTypes = [
    'previous_period' => 'Previous Period (same duration)',
    'previous_week' => 'Previous Week',
    'previous_month' => 'Previous Month',
    'previous_quarter' => 'Previous Quarter',
    'previous_year' => 'Same Period Last Year',
    'custom' => 'Custom Comparison Period',
];
```

**3. Visualization Components**
- Time-series line/area charts
- Summary cards with comparison indicators
- Side-by-side bar charts
- Heatmap calendar views
- Per-entity breakdown tables (machine/operator/part)

**4. Data Structure for Analytics**
```php
[
    'primary_period' => [
        'start_date' => '2025-09-13',
        'end_date' => '2025-10-13',
        'label' => 'Last 30 Days',
        'daily_breakdown' => [
            '2025-10-13' => ['metric_value' => 85.5, ...],
            // ... daily data points
        ],
        'summary' => [
            'average' => 82.3,
            'peak' => 95.2,
            'lowest' => 68.4,
            // ... aggregated metrics
        ],
    ],
    'comparison_period' => [
        // Same structure for comparison period
    ],
    'comparison_analysis' => [
        'metric_name' => [
            'current' => 82.3,
            'previous' => 75.8,
            'difference' => +6.5,
            'percentage_change' => +8.6,
            'trend' => 'up',
            'status' => 'improved',
        ],
    ],
]
```

#### BaseKPIWidget Abstract Class

All KPI widgets extend this base class for consistent UX:

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Forms;

abstract class BaseKPIWidget extends Widget
{
    // Mode toggle
    public string $mode = 'dashboard'; // or 'analytics'

    // Analytics filters (state variables)
    public string $timePeriod = '30d';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public bool $enableComparison = false;
    public string $comparisonType = 'previous_period';

    // Widget title
    protected string $title = 'KPI Widget';

    /**
     * Switch between dashboard and analytics mode
     */
    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->dispatch('modeChanged', mode: $mode);
    }

    /**
     * Get data based on current mode
     */
    public function getKPIData(): array
    {
        if ($this->mode === 'dashboard') {
            return $this->getDashboardData();
        }

        return $this->getAnalyticsData();
    }

    /**
     * Child classes must implement these methods
     */
    abstract protected function getDashboardData(): array;
    abstract protected function getAnalyticsData(): array;

    /**
     * Shared form schema for analytics filters
     */
    protected function getAnalyticsFiltersSchema(): array
    {
        return [
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
                ->default('30d')
                ->live()
                ->reactive(),

            Forms\Components\DatePicker::make('dateFrom')
                ->label('From Date')
                ->visible(fn($get) => $get('timePeriod') === 'custom')
                ->maxDate(now()),

            Forms\Components\DatePicker::make('dateTo')
                ->label('To Date')
                ->visible(fn($get) => $get('timePeriod') === 'custom')
                ->maxDate(now()),

            Forms\Components\Toggle::make('enableComparison')
                ->label('Compare with previous period')
                ->default(false)
                ->live()
                ->reactive(),

            Forms\Components\Select::make('comparisonType')
                ->label('Comparison Type')
                ->options([
                    'previous_period' => 'Previous Period (same duration)',
                    'previous_week' => 'Previous Week',
                    'previous_month' => 'Previous Month',
                    'previous_quarter' => 'Previous Quarter',
                    'previous_year' => 'Same Period Last Year',
                ])
                ->visible(fn($get) => $get('enableComparison'))
                ->default('previous_period')
                ->live(),
        ];
    }

    /**
     * Form for analytics filters
     */
    protected function getFormSchema(): array
    {
        if ($this->mode === 'analytics') {
            return $this->getAnalyticsFiltersSchema();
        }

        return [];
    }
}
```

#### Shared Blade Template

```blade
<!-- resources/views/filament/widgets/base-kpi-widget.blade.php -->

<x-filament-widgets::widget>
    <x-filament::card>
        {{-- Header with mode toggle --}}
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold dark:text-white">{{ $title }}</h2>

            <x-filament::tabs>
                <x-filament::tabs.item
                    wire:click="setMode('dashboard')"
                    :active="$mode === 'dashboard'"
                    icon="heroicon-o-chart-bar">
                    Dashboard
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    wire:click="setMode('analytics')"
                    :active="$mode === 'analytics'"
                    icon="heroicon-o-chart-pie">
                    Analytics
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        {{-- Dashboard Mode Content --}}
        @if($mode === 'dashboard')
            <div wire:poll.60s>
                {{ $dashboardContent }}
            </div>
        @endif

        {{-- Analytics Mode Content --}}
        @if($mode === 'analytics')
            {{-- Analytics Filters --}}
            <div class="mb-6">
                <x-filament::form wire:submit.prevent="$refresh">
                    {{ $this->form }}

                    <div class="mt-4">
                        <x-filament::button type="submit" wire:loading.attr="disabled">
                            <x-filament::loading-indicator class="h-4 w-4 mr-2" wire:loading wire:target="$refresh" />
                            Apply Filters
                        </x-filament::button>
                    </div>
                </x-filament::form>
            </div>

            {{-- Analytics Content --}}
            <div wire:loading.remove wire:target="$refresh">
                {{ $analyticsContent }}
            </div>

            {{-- Loading State --}}
            <div wire:loading wire:target="$refresh" class="flex justify-center py-12">
                <x-filament::loading-indicator class="h-12 w-12" />
                <span class="ml-3 text-gray-500 dark:text-gray-400">Loading analytics...</span>
            </div>
        @endif
    </x-filament::card>
</x-filament-widgets::widget>
```

#### Service Layer Structure

**RealTimeKPIService** - All dashboard mode methods:
```php
<?php

namespace App\Services\KPI;

use App\Models\Factory;

class RealTimeKPIService extends BaseKPIService
{
    public function __construct(Factory $factory)
    {
        parent::__construct($factory, 'tier_1');
    }

    // Dashboard mode methods (60s cache)
    public function getCurrentMachineStatus(): array { }
    public function getCurrentThroughputRate(): array { }
    public function getCurrentOperatorPerformance(): array { }
    public function getCurrentQualityRate(): array { }
    public function getCurrentOEE(): array { }
    // ... all 18 Tier 1 KPIs
}
```

**OperationalKPIService** - All analytics mode methods:
```php
<?php

namespace App\Services\KPI;

use App\Models\Factory;
use Carbon\Carbon;

class OperationalKPIService extends BaseKPIService
{
    public function __construct(Factory $factory)
    {
        parent::__construct($factory, 'tier_2');
    }

    // Analytics mode methods (5-15min cache)
    public function getMachineStatusAnalytics(array $options): array
    {
        $period = $options['time_period'] ?? '30d';
        $enableComparison = $options['enable_comparison'] ?? false;

        [$startDate, $endDate] = $this->getDateRange(
            $period,
            $options['date_from'] ?? null,
            $options['date_to'] ?? null
        );

        $cacheKey = "machine_status_analytics_{$period}_" . md5(json_encode($options));

        return $this->getCachedKPI($cacheKey, function() use ($startDate, $endDate, $enableComparison, $options) {
            // Fetch primary period data
            $primaryData = $this->fetchMachineStatusHistory($startDate, $endDate);

            $result = [
                'primary_period' => $primaryData,
            ];

            // Add comparison if enabled
            if ($enableComparison) {
                [$compStart, $compEnd] = $this->getComparisonDateRange(
                    $startDate,
                    $endDate,
                    $options['comparison_type'] ?? 'previous_period'
                );

                $comparisonData = $this->fetchMachineStatusHistory($compStart, $compEnd);
                $result['comparison_period'] = $comparisonData;
                $result['comparison_analysis'] = $this->calculateComparison(
                    $primaryData['summary'],
                    $comparisonData['summary']
                );
            }

            return $result;
        }, $this->getCacheTTL($period));
    }

    public function getProductionThroughputAnalytics(array $options): array { }
    public function getOperatorPerformanceAnalytics(array $options): array { }
    public function getQualityRateAnalytics(array $options): array { }
    // ... all KPIs with historical analysis

    /**
     * Calculate comparison date range
     */
    protected function getComparisonDateRange(Carbon $start, Carbon $end, string $type): array
    {
        $duration = $start->diffInDays($end);

        return match($type) {
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
}
```

#### Example Implementation: Machine Status KPI

**MachineStatusWidget.php:**
```php
<?php

namespace App\Filament\Widgets;

use App\Services\KPI\RealTimeKPIService;
use App\Services\KPI\OperationalKPIService;
use Illuminate\Support\Facades\Auth;

class MachineStatusWidget extends BaseKPIWidget
{
    protected static string $view = 'filament.widgets.machine-status-widget';
    protected string $title = 'Machine Status';

    /**
     * Dashboard mode: Current machine status snapshot
     */
    protected function getDashboardData(): array
    {
        $factory = Auth::user()->factory;
        $service = new RealTimeKPIService($factory);

        return $service->getCurrentMachineStatus();

        // Returns:
        // [
        //     'running' => ['count' => 5, 'machines' => [...]],
        //     'hold' => ['count' => 2, 'machines' => [...]],
        //     'scheduled' => ['count' => 3, 'machines' => [...]],
        //     'idle' => ['count' => 1, 'machines' => [...]],
        //     'total_machines' => 11,
        //     'updated_at' => '2025-10-13 10:30:00'
        // ]
    }

    /**
     * Analytics mode: Historical machine status trends
     */
    protected function getAnalyticsData(): array
    {
        $factory = Auth::user()->factory;
        $service = new OperationalKPIService($factory);

        return $service->getMachineStatusAnalytics([
            'time_period' => $this->timePeriod,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'enable_comparison' => $this->enableComparison,
            'comparison_type' => $this->comparisonType,
        ]);

        // Returns time-series data with comparisons
    }
}
```

#### KPI Coverage Matrix

| KPI Category | Dashboard View | Analytics View | Data Source |
|--------------|---------------|----------------|-------------|
| Machine Status | Current state (Running/Idle/Hold) | Utilization trends, downtime analysis | kpi_machine_daily |
| Production Throughput | Today's rate (units/hour) | Daily/weekly trends, capacity planning | kpi_daily_summaries |
| Operator Performance | Current shift efficiency | Performance over time, training impact | kpi_operator_daily |
| Quality Rate | Today's quality % | Defect trends, root cause patterns | kpi_daily_summaries |
| OEE | Current OEE score | OEE trends, breakdown by component | kpi_machine_daily |
| Work Order Status | Current WO distribution | Completion rate trends, bottleneck analysis | kpi_shift_summaries |
| Scrap Rate | Today's scrap % | Scrap trends by part/machine/operator | kpi_part_daily |
| Downtime | Active downtime events | Downtime patterns, MTBF/MTTR trends | kpi_machine_daily |
| Inventory Levels | Current stock levels | Inventory turnover, stockout analysis | Custom queries |
| Cost Metrics | Today's costs | Cost trends, budget variance analysis | kpi_daily_summaries |

**All 90 KPIs follow this dual-mode pattern for consistency!**

#### Performance Optimization

**Query Strategy:**
```php
protected function fetchMachineStatusHistory(Carbon $start, Carbon $end): array
{
    $days = $start->diffInDays($end);

    // Smart table selection based on date range
    if ($days <= 90) {
        // Use daily table (fast, detailed)
        $data = KpiMachineDaily::where('factory_id', $this->factory->id)
            ->whereBetween('summary_date', [$start, $end])
            ->orderBy('summary_date')
            ->get();
    } elseif ($days <= 365) {
        // Use weekly aggregation (future)
        $data = KpiMachineWeekly::where('factory_id', $this->factory->id)
            ->whereBetween('week_start_date', [$start, $end])
            ->get();
    } else {
        // Use monthly aggregation
        $data = KpiMonthlyAggregates::where('factory_id', $this->factory->id)
            ->where('year', '>=', $start->year)
            ->where('year', '<=', $end->year)
            ->get();
    }

    return $this->transformToAnalyticsFormat($data);
}
```

**Performance Benchmarks:**

| Scenario | Query Time | Cache Hit | Total Load |
|----------|-----------|-----------|------------|
| Dashboard mode (real-time) | 50-100ms | 5-10ms | **60-160ms** ✅ |
| Analytics 7d (no comparison) | 100-200ms | 10ms | **200-300ms** ✅ |
| Analytics 30d (no comparison) | 150-300ms | 15ms | **250-400ms** ✅ |
| Analytics 90d (no comparison) | 200-400ms | 20ms | **300-500ms** ✅ |
| Analytics 30d + comparison | 300-600ms | 30ms | **400-700ms** ✅ |
| Analytics 90d + comparison | 400-800ms | 40ms | **500-900ms** ⚠️ |
| Analytics 1yr + comparison | 500-1000ms | 50ms | **600-1.1s** ⚠️ |

**Optimization Techniques:**
1. Aggressive caching (60s dashboard, 5-15min analytics)
2. Pre-aggregated summary tables
3. Smart table selection based on date range
4. Lazy loading for charts and heavy components
5. Downsampling for large date ranges (show weekly instead of daily for 90+ days)
6. Progressive enhancement (load summary first, details later)

#### Benefits of Universal Dual-Mode

✅ **Consistency** - Same UX pattern across all 90 KPIs
✅ **Code Reusability** - BaseKPIWidget handles mode switching
✅ **Maintainability** - Update filters once, affects all KPIs
✅ **Performance** - Shared caching logic, optimized queries
✅ **Flexibility** - Easy to customize per KPI
✅ **Scalability** - Minimal performance impact at scale

#### Implementation Rollout

**Phase 1: Foundation**
- Create BaseKPIWidget
- Create shared Blade template
- Update BaseKPIService with analytics helpers
- Add comparison calculation methods

**Phase 2: First Complete KPI**
- Implement Machine Status KPI with both modes
- Test thoroughly
- Use as template for remaining KPIs

**Phase 3: Incremental Rollout**
- Implement 3-5 KPIs at a time
- Follow the established pattern
- Reuse components and logic

### Example: OperationalKPIService

```php
<?php

namespace App\Services\KPI;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class OperationalKPIService extends BaseKPIService
{
    /**
     * Get all operational KPIs
     */
    public function getKPIs(array $options = []): array
    {
        $dateRange = $options['date_range'] ?? $this->getDateRange('30d');

        return [
            'work_order_completion_rate' => $this->getWorkOrderCompletionRate($dateRange),
            'production_throughput' => $this->getProductionThroughput($dateRange),
            'scrap_rate' => $this->getScrapRate($dateRange),
            'work_order_status_distribution' => $this->getWorkOrderStatusDistribution($dateRange),
        ];
    }

    /**
     * Work Order Completion Rate
     */
    public function getWorkOrderCompletionRate(array $dateRange): array
    {
        $cacheKey = "work_order_completion_rate_" . md5(json_encode($dateRange));

        return $this->getCachedKPI($cacheKey, function () use ($dateRange) {
            // Use summary table for better performance
            $summary = DB::table('kpi_daily_summaries')
                ->where('factory_id', $this->factory->id)
                ->whereBetween('summary_date', [
                    $dateRange[0]->format('Y-m-d'),
                    $dateRange[1]->format('Y-m-d')
                ])
                ->selectRaw('
                    SUM(completed_orders) as completed,
                    SUM(total_orders) as total
                ')
                ->first();

            $total = $summary->total ?? 0;
            $completed = $summary->completed ?? 0;

            $rate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

            return [
                'rate' => $rate,
                'completed' => $completed,
                'total' => $total,
                'status' => $this->getStatus($rate, 85),
                'target' => 85,
            ];
        }, 1800); // 30 min cache
    }

    /**
     * Production Throughput
     */
    public function getProductionThroughput(array $dateRange): array
    {
        $cacheKey = "production_throughput_" . md5(json_encode($dateRange));

        return $this->getCachedKPI($cacheKey, function () use ($dateRange) {
            $summary = DB::table('kpi_daily_summaries')
                ->where('factory_id', $this->factory->id)
                ->whereBetween('summary_date', [
                    $dateRange[0]->format('Y-m-d'),
                    $dateRange[1]->format('Y-m-d')
                ])
                ->selectRaw('
                    SUM(total_units_produced) as total_units,
                    AVG(throughput_per_day) as avg_throughput
                ')
                ->first();

            return [
                'total_units' => $summary->total_units ?? 0,
                'avg_throughput' => round($summary->avg_throughput ?? 0, 2),
                'status' => $this->getStatus($summary->avg_throughput ?? 0, 1000),
                'target' => 1000,
            ];
        }, 1800);
    }

    /**
     * Scrap Rate
     */
    public function getScrapRate(array $dateRange): array
    {
        $cacheKey = "scrap_rate_" . md5(json_encode($dateRange));

        return $this->getCachedKPI($cacheKey, function () use ($dateRange) {
            $summary = DB::table('kpi_daily_summaries')
                ->where('factory_id', $this->factory->id)
                ->whereBetween('summary_date', [
                    $dateRange[0]->format('Y-m-d'),
                    $dateRange[1]->format('Y-m-d')
                ])
                ->selectRaw('AVG(scrap_rate) as avg_scrap_rate')
                ->first();

            $scrapRate = round($summary->avg_scrap_rate ?? 0, 2);

            return [
                'rate' => $scrapRate,
                'status' => $this->getStatus($scrapRate, 5, false), // Lower is better
                'target' => 5,
            ];
        }, 1800);
    }

    /**
     * Work Order Status Distribution
     */
    public function getWorkOrderStatusDistribution(array $dateRange): array
    {
        $cacheKey = "work_order_status_distribution_" . md5(json_encode($dateRange));

        return $this->getCachedKPI($cacheKey, function () use ($dateRange) {
            $statuses = WorkOrder::where('factory_id', $this->factory->id)
                ->whereBetween('created_at', $dateRange)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray();

            $total = array_sum($statuses);

            return [
                'distribution' => $statuses,
                'total' => $total,
                'percentages' => collect($statuses)->map(function ($count) use ($total) {
                    return $total > 0 ? round(($count / $total) * 100, 2) : 0;
                })->toArray(),
            ];
        }, 300); // 5 min cache (more real-time)
    }
}
```

### Shift Aggregation Job

```php
<?php

namespace App\Jobs\KPI;

use App\Models\Factory;
use App\Models\Shift;
use App\Models\KPI\ShiftSummary;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AggregateShiftKPIs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Factory $factory;
    protected Shift $shift;
    protected Carbon $shiftDate;

    public function __construct(Factory $factory, Shift $shift, Carbon $shiftDate)
    {
        $this->factory = $factory;
        $this->shift = $shift;
        $this->shiftDate = $shiftDate;
    }

    public function handle(): void
    {
        Log::info("Starting shift KPI aggregation", [
            'factory_id' => $this->factory->id,
            'shift_id' => $this->shift->id,
            'shift_date' => $this->shiftDate->format('Y-m-d')
        ]);

        DB::beginTransaction();

        try {
            $summary = $this->calculateShiftSummary();

            ShiftSummary::updateOrCreate(
                [
                    'factory_id' => $this->factory->id,
                    'shift_id' => $this->shift->id,
                    'shift_date' => $this->shiftDate->format('Y-m-d'),
                ],
                $summary
            );

            // Also aggregate machine-specific metrics
            $this->aggregateMachineMetrics();

            // Aggregate operator-specific metrics
            $this->aggregateOperatorMetrics();

            // Aggregate part-specific metrics
            $this->aggregatePartMetrics();

            DB::commit();

            Log::info("Completed shift KPI aggregation", [
                'factory_id' => $this->factory->id,
                'shift_id' => $this->shift->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to aggregate shift KPIs", [
                'factory_id' => $this->factory->id,
                'shift_id' => $this->shift->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function calculateShiftSummary(): array
    {
        $shiftStart = $this->shiftDate->copy()->setTimeFromTimeString($this->shift->start_time);
        $shiftEnd = $this->shiftDate->copy()->setTimeFromTimeString($this->shift->end_time);

        // Adjust for overnight shifts
        if ($shiftEnd->lt($shiftStart)) {
            $shiftEnd->addDay();
        }

        // Get work orders for this shift
        $workOrders = DB::table('work_orders')
            ->where('factory_id', $this->factory->id)
            ->whereBetween('start_time', [$shiftStart, $shiftEnd])
            ->get();

        // Calculate metrics
        $totalOrders = $workOrders->count();
        $completedOrders = $workOrders->whereIn('status', ['Completed', 'Closed'])->count();
        $inProgressOrders = $workOrders->where('status', 'Start')->count();
        $assignedOrders = $workOrders->where('status', 'Assigned')->count();
        $holdOrders = $workOrders->where('status', 'Hold')->count();

        $totalUnits = $workOrders->sum('qty');
        $okUnits = $workOrders->sum('ok_qtys');
        $scrappedUnits = $workOrders->sum('scrapped_qtys');

        $completionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;
        $scrapRate = $totalUnits > 0 ? ($scrappedUnits / $totalUnits) * 100 : 0;

        // Calculate production hours and throughput
        $productionHours = $shiftStart->diffInHours($shiftEnd);
        $throughputPerHour = $productionHours > 0 ? $okUnits / $productionHours : 0;

        return [
            'shift_name' => $this->shift->name,
            'shift_start_time' => $shiftStart,
            'shift_end_time' => $shiftEnd,

            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'in_progress_orders' => $inProgressOrders,
            'assigned_orders' => $assignedOrders,
            'hold_orders' => $holdOrders,
            'closed_orders' => $workOrders->where('status', 'Closed')->count(),
            'completion_rate' => round($completionRate, 2),

            'total_units_produced' => $totalUnits,
            'ok_units' => $okUnits,
            'scrapped_units' => $scrappedUnits,
            'scrap_rate' => round($scrapRate, 2),
            'throughput_per_hour' => round($throughputPerHour, 2),

            'total_production_hours' => $productionHours,

            'calculated_at' => now(),
        ];
    }

    protected function aggregateMachineMetrics(): void
    {
        // TODO: Implement machine-specific aggregation
    }

    protected function aggregateOperatorMetrics(): void
    {
        // TODO: Implement operator-specific aggregation
    }

    protected function aggregatePartMetrics(): void
    {
        // TODO: Implement part-specific aggregation
    }
}
```

---

## Smart Query Strategy: Time-Bounded with User-Selectable Ranges

### The Problem with Querying All Historical Data

After 1 year of operation, querying all work orders becomes:
- **Slow:** 5-10+ seconds per KPI
- **Not actionable:** Too much historical data overwhelms users
- **Expensive:** Millions of rows scanned unnecessarily

### Solution: Default Time Windows + User Selection

Instead of storing multiple period values for each KPI, we:
1. **Store atomic daily metrics** (once per entity per day)
2. **Let users select time ranges** via UI dropdowns
3. **Cache the calculated results** for each period
4. **Use optimal table granularity** based on date range

### Default Time Windows by KPI Tier

| Tier | Default Window | Rationale |
|------|----------------|-----------|
| **Tier 1 (Real-Time)** | Today or Current Shift | Immediate, actionable data |
| **Tier 2 (Shift-Based)** | Last 7 days | Weekly performance trends |
| **Tier 3 (Reports)** | Last 30 days or MTD/YTD | Strategic insights |

### User-Selectable Time Range UI Example

```blade
{{-- Dashboard Time Range Selector --}}
<div class="kpi-dashboard">
    <div class="time-range-selector">
        <label>Time Period:</label>
        <select wire:model.live="timeRange">
            <option value="today">Today</option>
            <option value="7d" selected>Last 7 Days</option>
            <option value="30d">Last 30 Days</option>
            <option value="90d">Last 90 Days</option>
            <option value="mtd">Month to Date</option>
            <option value="ytd">Year to Date</option>
        </select>
    </div>

    {{-- KPI Widgets update automatically when timeRange changes --}}
    <div class="kpi-grid">
        @foreach($kpis as $kpi)
            <x-kpi-widget :kpi="$kpi" :period="$timeRange" />
        @endforeach
    </div>
</div>
```

### Smart Table Selection Logic

The system automatically selects the optimal table based on the requested date range:

```php
public function getMachineThroughput(int $machineId, string $period = '7d'): array
{
    $dateRange = $this->getDateRange($period);
    $startDate = $dateRange[0];
    $cacheKey = "machine_{$machineId}_throughput_{$period}";
    $cacheTTL = $this->getCacheTTL($period);

    return Cache::remember($cacheKey, $cacheTTL, function () use ($machineId, $startDate, $dateRange) {
        // Automatically select optimal table
        $table = $this->getOptimalTable('kpi_machine', $startDate);

        // Query the appropriate table
        if ($table === 'kpi_machine_daily') {
            // Last 90 days - use daily table
            return $this->queryDailyTable($machineId, $dateRange);
        } elseif ($table === 'kpi_machine_weekly') {
            // 91 days - 1 year - use weekly table
            return $this->queryWeeklyTable($machineId, $dateRange);
        } else {
            // 1+ years - use monthly table
            return $this->queryMonthlyTable($machineId, $dateRange);
        }
    });
}

protected function queryDailyTable(int $machineId, array $dateRange): array
{
    $summary = DB::table('kpi_machine_daily')
        ->where('machine_id', $machineId)
        ->where('factory_id', $this->factory->id)
        ->whereBetween('summary_date', [
            $dateRange[0]->format('Y-m-d'),
            $dateRange[1]->format('Y-m-d')
        ])
        ->selectRaw('
            SUM(units_produced) as total_units,
            SUM(uptime_hours) as total_hours,
            AVG(utilization_rate) as avg_utilization
        ')
        ->first();

    $throughput = $summary->total_hours > 0
        ? round($summary->total_units / $summary->total_hours, 2)
        : 0;

    return [
        'throughput_per_hour' => $throughput,
        'total_units' => $summary->total_units ?? 0,
        'avg_utilization' => round($summary->avg_utilization ?? 0, 2),
        'data_source' => 'daily',
    ];
}

protected function queryWeeklyTable(int $machineId, array $dateRange): array
{
    $summary = DB::table('kpi_machine_weekly')
        ->where('machine_id', $machineId)
        ->where('factory_id', $this->factory->id)
        ->whereBetween('week_start_date', [
            $dateRange[0]->format('Y-m-d'),
            $dateRange[1]->format('Y-m-d')
        ])
        ->selectRaw('
            SUM(total_units_produced) as total_units,
            SUM(total_uptime_hours) as total_hours,
            AVG(avg_utilization_rate) as avg_utilization
        ')
        ->first();

    $throughput = $summary->total_hours > 0
        ? round($summary->total_units / $summary->total_hours, 2)
        : 0;

    return [
        'throughput_per_hour' => $throughput,
        'total_units' => $summary->total_units ?? 0,
        'avg_utilization' => round($summary->avg_utilization ?? 0, 2),
        'data_source' => 'weekly',
    ];
}
```

### Cross-Dimensional KPIs: On-Demand Calculation

For rarely-used cross-dimensional queries (e.g., "Throughput by Machine for Part X"), calculate on-demand from raw work_orders with short time windows and aggressive caching:

```php
public function getThroughputByMachineForPart(int $partId, string $period = '7d'): array
{
    $dateRange = $this->getDateRange($period);
    $cacheKey = "throughput_machine_part_{$partId}_{$period}";

    // Cache for 30 minutes since this is a rare query
    return Cache::remember($cacheKey, 1800, function () use ($partId, $dateRange) {
        // Query raw work_orders for this specific combination
        // Limit to recent data only (last 30 days max)
        $maxRange = Carbon::now()->subDays(30);
        $startDate = $dateRange[0]->gt($maxRange) ? $dateRange[0] : $maxRange;

        return DB::table('work_orders')
            ->join('machines', 'work_orders.machine_id', '=', 'machines.id')
            ->join('boms', 'work_orders.bom_id', '=', 'boms.id')
            ->where('boms.part_number_id', $partId)
            ->where('work_orders.factory_id', $this->factory->id)
            ->whereBetween('work_orders.start_time', [$startDate, $dateRange[1]])
            ->groupBy('work_orders.machine_id', 'machines.name')
            ->selectRaw('
                work_orders.machine_id,
                machines.name as machine_name,
                SUM(work_orders.ok_qtys) as total_units,
                SUM(TIMESTAMPDIFF(HOUR, start_time, end_time)) as total_hours
            ')
            ->get()
            ->map(function ($row) {
                return [
                    'machine_id' => $row->machine_id,
                    'machine_name' => $row->machine_name,
                    'throughput' => $row->total_hours > 0
                        ? round($row->total_units / $row->total_hours, 2)
                        : 0,
                ];
            })
            ->toArray();
    });
}
```

### Performance Comparison

| Query Type | Approach | Query Time | Storage Overhead |
|------------|----------|-----------|------------------|
| **Machine throughput (7d)** | Daily summary table | 10ms | Minimal |
| **Machine throughput (6 months)** | Weekly summary table | 15ms | Minimal |
| **Machine throughput (2 years)** | Monthly summary table | 20ms | Minimal |
| **Machine × Part (7d)** | On-demand from work_orders | 50-100ms | None |
| **All historical (naive)** | Work orders | 5000ms+ ❌ | None |

### Key Benefits

1. ✅ **No redundant storage** - Store atomic metrics once, calculate any period on-demand
2. ✅ **Fast queries** - Optimal table selection ensures fast response times
3. ✅ **Flexible** - Users can request any time period
4. ✅ **Cache-friendly** - Popular periods (7d, 30d) cached for instant access
5. ✅ **Scalable** - Storage grows linearly, not exponentially

---

## Performance Optimization Techniques

### 1. Query Optimization with Combined Periods

**Before (2 queries):**
```php
$current = DB::table('work_orders')
    ->whereBetween('created_at', $currentRange)
    ->count();

$previous = DB::table('work_orders')
    ->whereBetween('created_at', $previousRange)
    ->count();
```

**After (1 query):**
```php
$result = DB::table('work_orders')
    ->selectRaw('
        COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as current_count,
        COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as previous_count
    ', [...$currentRange, ...$previousRange])
    ->first();
```

### 2. Summary Table Queries

**Before (complex joins):**
```php
$data = WorkOrder::with(['bom.purchaseOrder.partNumber', 'machine', 'operator'])
    ->where('factory_id', $factoryId)
    ->whereBetween('created_at', $dateRange)
    ->where('status', 'Completed')
    ->get();
// Query time: 500-800ms
```

**After (summary table):**
```php
$data = DB::table('kpi_daily_summaries')
    ->where('factory_id', $factoryId)
    ->whereBetween('summary_date', $dateRange)
    ->get();
// Query time: 10-50ms
```

### 3. Redis Cache Layering

```php
// Layer 1: Check Redis (0.3ms)
$cached = Cache::store('kpi_cache')->tags(['factory_1'])->get($key);
if ($cached) return $cached;

// Layer 2: Query summary table (50ms)
$data = DB::table('kpi_daily_summaries')->where(...)->get();

// Cache for next request
Cache::store('kpi_cache')->tags(['factory_1'])->put($key, $data, 1800);
```

### 4. Eager Loading Optimization

```php
// Instead of N+1
$workOrders = WorkOrder::all();
foreach ($workOrders as $wo) {
    $partNumber = $wo->bom->purchaseOrder->partNumber; // N+1
}

// Use eager loading
$workOrders = WorkOrder::with(['bom.purchaseOrder.partNumber'])->get();
```

### 5. Chunking Large Datasets

```php
// Process large datasets in chunks
WorkOrder::where('factory_id', $factoryId)
    ->chunk(1000, function ($workOrders) {
        foreach ($workOrders as $wo) {
            // Process each work order
        }
    });
```

---

## Multi-Tenancy Strategy

### Factory Isolation

**1. Cache Isolation**
```php
// Each factory has isolated cache namespace
Cache::tags(["factory_{$factoryId}"])->put($key, $value);

// Clear cache for single factory
Cache::tags(["factory_{$factoryId}"])->flush();
```

**2. Database Queries**
```php
// Always scope queries by factory_id
$data = WorkOrder::where('factory_id', Auth::user()->factory_id)->get();

// Use global scope in models
protected static function booted()
{
    static::addGlobalScope('factory', function (Builder $builder) {
        if (Auth::check()) {
            $builder->where('factory_id', Auth::user()->factory_id);
        }
    });
}
```

**3. Job Processing**
```php
// Process KPIs for all factories
foreach (Factory::all() as $factory) {
    dispatch(new AggregateShiftKPIs($factory, $shift, $date))
        ->onQueue("factory_{$factory->id}");
}

// Dedicated queue per factory for isolation
```

**4. Report Generation**
```php
// Store reports in factory-specific directories
storage/app/reports/
├── factory_1/
│   ├── daily/
│   ├── weekly/
│   └── monthly/
└── factory_2/
    ├── daily/
    ├── weekly/
    └── monthly/
```

---

## Report Generation System

### Report Structure

#### Daily Report Template

```php
<?php

namespace App\Services\Reports;

use App\Models\Factory;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class DailyReportGenerator
{
    protected Factory $factory;
    protected Carbon $date;

    public function __construct(Factory $factory, Carbon $date)
    {
        $this->factory = $factory;
        $this->date = $date;
    }

    public function generate(): string
    {
        $data = $this->collectData();

        $pdf = Pdf::loadView('reports.daily', [
            'factory' => $this->factory,
            'date' => $this->date,
            'data' => $data,
        ]);

        $filename = $this->getFilename();
        $path = storage_path("app/reports/factory_{$this->factory->id}/daily/{$filename}");

        $pdf->save($path);

        return $path;
    }

    protected function collectData(): array
    {
        // Get data from kpi_daily_summaries
        $summary = DB::table('kpi_daily_summaries')
            ->where('factory_id', $this->factory->id)
            ->where('summary_date', $this->date->format('Y-m-d'))
            ->first();

        // Get comparison with previous day
        $previousSummary = DB::table('kpi_daily_summaries')
            ->where('factory_id', $this->factory->id)
            ->where('summary_date', $this->date->copy()->subDay()->format('Y-m-d'))
            ->first();

        return [
            'summary' => $summary,
            'previous' => $previousSummary,
            'comparison' => $this->calculateComparison($summary, $previousSummary),
            'machines' => $this->getMachineMetrics(),
            'operators' => $this->getOperatorMetrics(),
            'parts' => $this->getPartMetrics(),
        ];
    }

    protected function getFilename(): string
    {
        return "daily_report_{$this->date->format('Y-m-d')}.pdf";
    }
}
```

### Report Blade Template

```blade
{{-- resources/views/reports/daily.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Daily Production Report - {{ $date->format('F d, Y') }}</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .metric { display: inline-block; width: 30%; margin: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Daily Production Report</h1>
        <h2>{{ $factory->name }}</h2>
        <p>{{ $date->format('F d, Y') }}</p>
        <p>Generated: {{ now()->format('F d, Y H:i:s') }}</p>
    </div>

    <div class="section">
        <h3>Executive Summary</h3>
        <div class="metric">
            <strong>Production Output:</strong><br>
            {{ number_format($data['summary']->total_units_produced) }} units
            ({{ $data['comparison']['production_change'] > 0 ? '+' : '' }}{{ $data['comparison']['production_change'] }}%)
        </div>
        <div class="metric">
            <strong>Work Orders Completed:</strong><br>
            {{ $data['summary']->completed_orders }}/{{ $data['summary']->total_orders }}
            ({{ round($data['summary']->completion_rate, 1) }}%)
        </div>
        <div class="metric">
            <strong>Quality Rate:</strong><br>
            {{ round($data['summary']->quality_rate, 1) }}%
        </div>
    </div>

    <div class="section">
        <h3>Production Metrics</h3>
        <table>
            <tr>
                <th>Metric</th>
                <th>Today</th>
                <th>Yesterday</th>
                <th>Change</th>
            </tr>
            <tr>
                <td>Units Produced</td>
                <td>{{ number_format($data['summary']->total_units_produced) }}</td>
                <td>{{ number_format($data['previous']->total_units_produced ?? 0) }}</td>
                <td>{{ $data['comparison']['production_change'] }}%</td>
            </tr>
            <tr>
                <td>Scrap Rate</td>
                <td>{{ round($data['summary']->scrap_rate, 2) }}%</td>
                <td>{{ round($data['previous']->scrap_rate ?? 0, 2) }}%</td>
                <td>{{ $data['comparison']['scrap_change'] }}%</td>
            </tr>
            <tr>
                <td>OEE</td>
                <td>{{ round($data['summary']->oee, 1) }}%</td>
                <td>{{ round($data['previous']->oee ?? 0, 1) }}%</td>
                <td>{{ $data['comparison']['oee_change'] }}%</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Top 5 Machines by Production</h3>
        <table>
            <tr>
                <th>Machine</th>
                <th>Units Produced</th>
                <th>Utilization</th>
                <th>Quality Rate</th>
            </tr>
            @foreach($data['machines'] as $machine)
            <tr>
                <td>{{ $machine->name }}</td>
                <td>{{ number_format($machine->units_produced) }}</td>
                <td>{{ round($machine->utilization_rate, 1) }}%</td>
                <td>{{ round($machine->quality_rate, 1) }}%</td>
            </tr>
            @endforeach
        </table>
    </div>

    <div class="section">
        <h3>Recommendations</h3>
        <ul>
            @if($data['summary']->scrap_rate > 5)
            <li>⚠️ Scrap rate is above target (5%). Review quality processes.</li>
            @endif
            @if($data['summary']->oee < 75)
            <li>⚠️ OEE is below target (75%). Focus on reducing downtime.</li>
            @endif
            @if($data['summary']->completion_rate < 85)
            <li>⚠️ Work order completion below target (85%). Review scheduling.</li>
            @endif
        </ul>
    </div>
</body>
</html>
```

---

## Testing Strategy

### Test Structure

```
tests/
├── Feature/
│   └── KPI/
│       ├── Tier1RealTimeKPITest.php
│       ├── Tier2ShiftBasedKPITest.php
│       ├── ShiftAggregationTest.php
│       ├── DailyReportTest.php
│       └── CachePerformanceTest.php
└── Unit/
    └── Services/
        └── KPI/
            ├── OperationalKPIServiceTest.php
            ├── QualityKPIServiceTest.php
            └── TenantKPICacheTest.php
```

### Example Test

```php
<?php

namespace Tests\Feature\KPI;

use App\Models\Factory;
use App\Models\WorkOrder;
use App\Services\KPI\OperationalKPIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalKPIServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Factory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = Factory::factory()->create();
    }

    public function test_work_order_completion_rate_calculation()
    {
        // Create 10 work orders, 8 completed
        WorkOrder::factory()->count(8)->create([
            'factory_id' => $this->factory->id,
            'status' => 'Completed'
        ]);

        WorkOrder::factory()->count(2)->create([
            'factory_id' => $this->factory->id,
            'status' => 'Start'
        ]);

        $service = new OperationalKPIService($this->factory);
        $result = $service->getWorkOrderCompletionRate([now()->subDays(7), now()]);

        $this->assertEquals(80.0, $result['rate']);
        $this->assertEquals(8, $result['completed']);
        $this->assertEquals(10, $result['total']);
    }

    public function test_kpi_caching_works()
    {
        WorkOrder::factory()->count(10)->create([
            'factory_id' => $this->factory->id
        ]);

        $service = new OperationalKPIService($this->factory);

        // First call - should query database
        $start = microtime(true);
        $result1 = $service->getWorkOrderCompletionRate([now()->subDays(7), now()]);
        $time1 = microtime(true) - $start;

        // Second call - should use cache
        $start = microtime(true);
        $result2 = $service->getWorkOrderCompletionRate([now()->subDays(7), now()]);
        $time2 = microtime(true) - $start;

        $this->assertEquals($result1, $result2);
        $this->assertLessThan($time1, $time2); // Cached should be faster
    }
}
```

---

## Deployment Plan

### Pre-Deployment Checklist

- [ ] All tests passing
- [ ] Database migrations tested on staging
- [ ] Redis configuration verified
- [ ] Queue workers configured
- [ ] Scheduler jobs configured
- [ ] Report storage directories created
- [ ] Email configuration tested
- [ ] Performance benchmarks met
- [ ] Documentation updated
- [ ] User acceptance testing completed

### Deployment Steps

**1. Database Migration**
```bash
php artisan migrate --force
```

**2. Clear Caches**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

**3. Start Queue Workers**
```bash
php artisan queue:work --queue=kpi,reports,default --tries=3
```

**4. Verify Scheduler**
```bash
php artisan schedule:list
```

**5. Warm Cache**
```bash
php artisan kpi:warm-cache
```

**6. Generate Initial Reports**
```bash
php artisan reports:generate daily --date=yesterday
```

### Rollback Plan

1. Revert code deployment
2. Rollback database migrations if needed
3. Clear Redis cache
4. Restart queue workers
5. Monitor for errors

---

## Monitoring & Maintenance

### Key Metrics to Monitor

**1. Performance Metrics**
- Dashboard load time (target: <1s)
- KPI calculation time (target: <100ms)
- Cache hit rate (target: >95%)
- Report generation time (target: <5 min)

**2. Queue Metrics**
- Job processing time
- Failed job count
- Queue backlog size

**3. Cache Metrics**
- Redis memory usage
- Cache eviction rate
- Cache key count per factory

**4. Database Metrics**
- Summary table size growth
- Query execution times
- Index usage

### Laravel Horizon

Install and configure Horizon for queue monitoring:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

### Logging

```php
// Log KPI calculation times
Log::info('KPI calculation completed', [
    'kpi' => 'work_order_completion_rate',
    'factory_id' => $factoryId,
    'calculation_time_ms' => $duration,
    'cache_hit' => $cacheHit
]);
```

### Alerts

Setup alerts for:
- Failed KPI aggregation jobs
- Report generation failures
- Cache hit rate drops below 90%
- Dashboard load time exceeds 2s
- Queue backlog exceeds 1000 jobs

---

## Performance Projections

### Current State (3 KPIs)

| Metric | Value |
|--------|-------|
| Dashboard load time | 2-4s |
| Queries per page | 20-40 |
| Cache hit rate | 60-70% |
| Concurrent users | 20-30 |

### Target State (90 KPIs)

| Metric | Target | Improvement |
|--------|--------|-------------|
| Dashboard load time | <1s | **4x faster** |
| Queries per page | 10-30 | **50% reduction** |
| Cache hit rate | 95-99% | **35% improvement** |
| Concurrent users | 100+ | **4x capacity** |

### Performance by Tier

| Tier | KPI Count | Query Method | Avg Response | Cache TTL |
|------|-----------|--------------|--------------|-----------|
| Tier 1 | 18 | Live + indexes | 50-100ms | 1-5 min |
| Tier 2 | 28 | Summary tables | 10-50ms | 30 min |
| Tier 3 | 44 | Pre-generated | 0ms (file) | N/A |

---

## Risk Mitigation

### Identified Risks

**1. Redis Memory Exhaustion**
- **Risk:** Too many cache keys consume all memory
- **Mitigation:** Set Redis maxmemory policy, monitor usage, implement cache key expiration

**2. Summary Table Growth**
- **Risk:** Summary tables grow too large (without lifecycle management: 32M rows in 5 years for 50 factories)
- **Mitigation:**
  - Implement hybrid granularity strategy (daily/weekly/monthly)
  - Automatic archival via monthly scheduled job
  - Keep only 90 days of daily data
  - Aggregate to weekly (91 days - 1 year) and monthly (1+ years)
  - Expected: 92% storage reduction (2.4M rows vs 32M rows)

**3. Report Generation Failures**
- **Risk:** PDF/Excel generation times out or fails
- **Mitigation:** Queue-based generation, increase timeout, implement retry logic

**4. Cache Invalidation Issues**
- **Risk:** Stale data shown to users
- **Mitigation:** Proper cache tagging, automatic invalidation on data changes, manual refresh button

**5. Queue Backlog**
- **Risk:** Jobs pile up during peak times
- **Mitigation:** Multiple queue workers, priority queues, monitoring alerts

**6. Multi-Tenancy Data Leaks**
- **Risk:** Factory A sees Factory B data
- **Mitigation:** Strict factory_id scoping, automated tests, cache namespace isolation

---

## Appendix

### A. Laravel Scheduler Configuration

Add to `routes/console.php` or `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Shift aggregations
Schedule::command('kpi:aggregate shift')
    ->dailyAt('07:00')->name('aggregate-night-shift');
Schedule::command('kpi:aggregate shift')
    ->dailyAt('15:00')->name('aggregate-day-shift');
Schedule::command('kpi:aggregate shift')
    ->dailyAt('23:00')->name('aggregate-evening-shift');

// Daily aggregations
Schedule::command('kpi:aggregate daily')
    ->dailyAt('02:00')->name('aggregate-daily-kpis');

// Weekly aggregations
Schedule::command('kpi:aggregate weekly')
    ->weeklyOn(1, '03:00')->name('aggregate-weekly-kpis');

// Monthly aggregations
Schedule::command('kpi:aggregate monthly')
    ->monthlyOn(1, '04:00')->name('aggregate-monthly-kpis');

// Daily report
Schedule::command('reports:generate daily')
    ->dailyAt('02:30')->name('generate-daily-report');

// Weekly report
Schedule::command('reports:generate weekly')
    ->weeklyOn(1, '04:00')->name('generate-weekly-report');

// Monthly report
Schedule::command('reports:generate monthly')
    ->monthlyOn(1, '05:00')->name('generate-monthly-report');

// Cache warming
Schedule::command('kpi:warm-cache')
    ->everyFifteenMinutes()->name('warm-kpi-cache');
```

### B. Environment Variables

```env
# Redis Configuration
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CACHE_DB=0
REDIS_KPI_DB=1
REDIS_REALTIME_DB=2
REDIS_QUEUE_DB=3

# Queue Configuration
QUEUE_CONNECTION=redis

# Report Storage
REPORT_STORAGE_DISK=local
REPORT_RETENTION_DAYS=365

# Email Configuration (for reports)
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=reports@yourcompany.com
MAIL_FROM_NAME="${APP_NAME} Reports"
```

### C. Useful Commands

```bash
# Run shift aggregation manually
php artisan kpi:aggregate shift --factory=1 --shift=1 --date=2025-10-08

# Generate report manually
php artisan reports:generate daily --factory=1 --date=2025-10-08

# Warm cache for specific factory
php artisan kpi:warm-cache --factory=1

# Clear KPI cache
php artisan cache:clear --tags=factory_1,kpi

# Monitor queue
php artisan queue:work --queue=kpi,reports --tries=3 --timeout=300

# Check scheduler
php artisan schedule:list
php artisan schedule:run

# Database maintenance & archival
php artisan kpi:archive                    # Run full archival process
php artisan kpi:archive --factory=1        # Archive specific factory only

# Check table sizes
SELECT
    table_name,
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
    table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name LIKE 'kpi_%'
ORDER BY (data_length + index_length) DESC;
```

---

## Conclusion

This implementation plan provides a comprehensive roadmap for optimizing the KPI system to support 90+ manufacturing KPIs with excellent performance. The 3-tier architecture balances real-time needs with system efficiency, while Redis caching and summary tables ensure fast response times even at scale.

**Key Success Factors:**
1. Phased implementation (8 weeks)
2. Comprehensive testing at each phase
3. Proper multi-tenancy isolation
4. Automated report generation
5. Continuous monitoring and optimization

**Next Steps:**
1. Review and approve this plan
2. Begin Phase 1 (Foundation setup)
3. Regular progress reviews
4. Adjust timelines based on actual progress

---

---

## Migration Path: Shared to Separate Databases

### Overview

This section provides a complete migration guide from shared database to database-per-factory architecture when you're ready to scale beyond 10 factories.

### Pre-Migration Checklist

- [ ] Current factory count: 10-15+
- [ ] Query performance degrading (>500ms)
- [ ] Database size >10 GB
- [ ] Different factory sizes require isolation
- [ ] Team ready for increased maintenance complexity

### Migration Script

Create `app/Console/Commands/MigrateToTenantDatabases.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class MigrateToTenantDatabases extends Command
{
    protected $signature = 'migrate:to-tenant-databases
                            {factory? : Specific factory ID to migrate}
                            {--dry-run : Preview without executing}';

    protected $description = 'Migrate from shared DB to database-per-factory';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $factoryId = $this->argument('factory');
        $factories = $factoryId
            ? Factory::where('id', $factoryId)->get()
            : Factory::all();

        $this->info("Migrating {$factories->count()} factory(ies)...");

        foreach ($factories as $factory) {
            $this->migrateFactory($factory);
        }

        $this->info('✅ Migration complete!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Update .env: TENANT_MODE=database_per_factory');
        $this->info('2. Test thoroughly on staging');
        $this->info('3. Deploy to production');
        $this->info('4. Monitor for 24-48 hours');
        $this->info('5. Once stable, drop factory_id columns from old DB');

        return Command::SUCCESS;
    }

    protected function migrateFactory(Factory $factory): void
    {
        $this->info("Migrating: {$factory->name} (ID: {$factory->id})");

        // Step 1: Create new database
        $this->createFactoryDatabase($factory);

        // Step 2: Run migrations
        $this->runFactoryMigrations($factory);

        // Step 3: Copy data
        $this->copyFactoryData($factory);

        // Step 4: Verify data integrity
        $this->verifyDataIntegrity($factory);

        $this->info("  ✓ {$factory->name} migrated successfully");
    }

    protected function createFactoryDatabase(Factory $factory): void
    {
        $dbName = "prodstream_factory_{$factory->id}";

        if ($this->option('dry-run')) {
            $this->line("  [DRY RUN] Would create database: {$dbName}");
            return;
        }

        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->line("  ✓ Database created: {$dbName}");
        } catch (\Exception $e) {
            $this->error("  ✗ Failed to create database: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function runFactoryMigrations(Factory $factory): void
    {
        if ($this->option('dry-run')) {
            $this->line("  [DRY RUN] Would run migrations");
            return;
        }

        $connection = "factory_{$factory->id}";

        // Register connection
        config(["database.connections.{$connection}" => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'database' => "prodstream_factory_{$factory->id}",
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
        ]]);

        Artisan::call('migrate', [
            '--database' => $connection,
            '--force' => true,
            '--path' => 'database/migrations/tenant'  // Tenant-specific migrations
        ]);

        $this->line("  ✓ Migrations complete");
    }

    protected function copyFactoryData(Factory $factory): void
    {
        $this->line("  Copying data for factory {$factory->id}...");

        $tables = [
            'work_orders',
            'machines',
            'operators',
            'boms',
            'kpi_machine_daily',
            'kpi_operator_daily',
            'kpi_part_daily',
            'kpi_machine_weekly',
            'kpi_operator_weekly',
            'kpi_part_weekly',
            'kpi_machine_monthly',
            'kpi_operator_monthly',
            'kpi_part_monthly',
            // ... add all tables
        ];

        $connection = "factory_{$factory->id}";
        $sourceDb = env('DB_DATABASE');
        $targetDb = "prodstream_factory_{$factory->id}";

        foreach ($tables as $table) {
            if ($this->option('dry-run')) {
                $count = DB::table($table)->where('factory_id', $factory->id)->count();
                $this->line("  [DRY RUN] Would copy {$count} rows from {$table}");
                continue;
            }

            $this->copyTable($table, $factory->id, $sourceDb, $targetDb);
        }

        $this->line("  ✓ Data copied");
    }

    protected function copyTable(string $table, int $factoryId, string $sourceDb, string $targetDb): void
    {
        // Copy data in chunks to avoid memory issues
        DB::table("$sourceDb.$table")
            ->where('factory_id', $factoryId)
            ->orderBy('id')
            ->chunk(1000, function ($rows) use ($table, $targetDb) {
                $data = $rows->map(function ($row) {
                    $array = (array) $row;
                    // Remove factory_id column (not needed in tenant DB)
                    unset($array['factory_id']);
                    return $array;
                })->toArray();

                DB::table("$targetDb.$table")->insert($data);
            });

        $count = DB::table("$targetDb.$table")->count();
        $this->line("    ✓ {$table}: {$count} rows copied");
    }

    protected function verifyDataIntegrity(Factory $factory): void
    {
        $connection = "factory_{$factory->id}";
        $sourceDb = env('DB_DATABASE');
        $targetDb = "prodstream_factory_{$factory->id}";

        $this->line("  Verifying data integrity...");

        $tables = ['work_orders', 'machines', 'operators'];

        foreach ($tables as $table) {
            $sourceCount = DB::table("$sourceDb.$table")
                ->where('factory_id', $factory->id)
                ->count();

            $targetCount = DB::connection($connection)
                ->table($table)
                ->count();

            if ($sourceCount !== $targetCount) {
                $this->error("    ✗ {$table}: Mismatch! Source: {$sourceCount}, Target: {$targetCount}");
                throw new \Exception("Data integrity check failed for {$table}");
            }

            $this->line("    ✓ {$table}: {$targetCount} rows verified");
        }

        $this->line("  ✓ Data integrity verified");
    }
}
```

### Migration Steps

#### Step 1: Preparation (Week 1)

```bash
# 1. Backup current database
mysqldump prodstream_main > backup_before_migration_$(date +%Y%m%d).sql

# 2. Create staging environment
php artisan env --staging

# 3. Test migration on staging with one factory
php artisan migrate:to-tenant-databases 1 --dry-run
php artisan migrate:to-tenant-databases 1
```

#### Step 2: Pilot Migration (Week 2)

```bash
# Migrate 1-2 smaller factories
php artisan migrate:to-tenant-databases 1
php artisan migrate:to-tenant-databases 2

# Update config for these factories only
php artisan cache:clear
```

#### Step 3: Gradual Rollout (Weeks 3-4)

```bash
# Migrate 3-5 factories per week
php artisan migrate:to-tenant-databases 3
php artisan migrate:to-tenant-databases 4
php artisan migrate:to-tenant-databases 5

# Monitor performance daily
```

#### Step 4: Complete Migration (Week 5)

```bash
# Migrate remaining factories
php artisan migrate:to-tenant-databases

# Update environment configuration
# .env: TENANT_MODE=database_per_factory

# Clear all caches
php artisan cache:clear
php artisan config:clear

# Restart queue workers
php artisan queue:restart
```

#### Step 5: Cleanup (Week 6)

```bash
# After 1-2 weeks of stable operation:

# 1. Create migrations to drop factory_id columns
php artisan make:migration drop_factory_id_columns_from_shared_db

# 2. Execute cleanup
php artisan migrate --force

# 3. Vacuum/optimize old shared DB
mysql prodstream_main -e "OPTIMIZE TABLE work_orders, machines, operators"
```

### Rollback Plan

If issues arise during migration:

```bash
# 1. Revert .env
TENANT_MODE=shared_database

# 2. Clear caches
php artisan cache:clear
php artisan config:clear

# 3. Restart services
php artisan queue:restart

# 4. The shared DB is still intact as source of truth
# New tenant databases can be dropped if needed
```

### Post-Migration Verification

```sql
-- Verify data consistency
-- Run for each migrated factory

-- Source (shared DB)
SELECT COUNT(*) FROM prodstream_main.work_orders WHERE factory_id = 1;

-- Target (tenant DB)
SELECT COUNT(*) FROM prodstream_factory_1.work_orders;

-- Should match!
```

### Performance Monitoring

Monitor these metrics post-migration:

```php
// In AppServiceProvider
DB::listen(function ($query) {
    if ($query->time > 500) {
        Log::warning('Slow query after migration', [
            'sql' => $query->sql,
            'time' => $query->time,
            'connection' => $query->connectionName,
        ]);
    }
});
```

Track:
- Query response times per factory
- Cache hit rates
- Queue processing times
- User-reported issues

---

**Document Version:** 3.0
**Status:** Ready for Implementation
**Estimated Timeline:** 7-8 weeks (initial) + Migration as needed
**Last Updated:** October 13, 2025

### Version 3.0 Updates

**Key Enhancements:**
1. **Flexible Architecture** - Start with shared DB, migrate to database-per-factory as you scale
2. **Hybrid Granularity Storage Strategy** - Daily/Weekly/Monthly tables with automatic lifecycle management
3. **User-Selectable Time Ranges** - Flexible period selection with smart table routing
4. **Storage Optimization** - 92% reduction in long-term storage (2.4M vs 32M rows over 5 years)
5. **On-Demand Cross-Dimensional Queries** - Rare KPI combinations calculated dynamically with caching
6. **Automatic Data Archival** - Monthly jobs to aggregate and purge old data
7. **Complete Migration Path** - Detailed guide from shared to separate databases

**Impact:**
- Start simple with shared database for 1-10 factories
- Scale smoothly to database-per-factory for 10+ factories
- Single codebase works for both architectures
- Provides clear migration path with minimal downtime
- Solves the storage explosion problem for multi-tenant deployments
- Maintains fast query performance through smart table selection
- Reduces storage costs by 92% over 5 years while preserving data integrity


## Recent Performance Improvements (October 2025)

### Manual Refresh Implementation for Dashboard Mode

**Change:** Replaced automatic 5-minute polling with user-controlled manual refresh button.

**Implementation Details:**
- Dashboard Mode now includes a **Refresh button** in the header
- Button bypasses cache completely to fetch fresh data
- Shows spinning icon and loading state during refresh
- Updates "Last Updated" timestamp with each refresh
- Cache is retained for regular page loads (5-minute TTL)

**Performance Benefits:**
1. **Reduced Server Load**: Eliminates continuous polling every 5 minutes for all active users
2. **Lower Database Load**: Only fetches fresh data when users explicitly request it
3. **Better User Control**: Users decide when they need the latest data
4. **Improved Cache Efficiency**: Cache remains useful for page loads, bypassed only on manual refresh
5. **Bandwidth Savings**: Reduces unnecessary network traffic

**Impact Calculation:**
- **Before**: 20 users × 12 refreshes/hour = 240 database queries/hour
- **After**: 20 users × 2-3 manual refreshes/hour = 40-60 database queries/hour
- **Reduction**: 75-83% fewer queries for Dashboard Mode

**User Experience:**
- Clear visual feedback with spinning icon
- Timestamp shows when data was last refreshed
- Button disabled during refresh to prevent duplicate requests
- Intuitive placement next to "Machine Status" heading

**Technical Implementation:**
```php
// KPIAnalyticsDashboard.php
public bool $skipCache = false;

public function refreshData(): void
{
    $this->skipCache = true;
}

// RealTimeKPIService.php
public function getCurrentMachineStatus(bool $skipCache = false): array
{
    if ($skipCache) {
        return $callback(); // Bypass cache
    }
    return $this->getCachedKPI('current_machine_status', $callback, 300);
}
```

**Documentation Updates:**
- ✅ MACHINE_STATUS_ANALYTICS.md - Updated Dashboard Mode refresh strategy
- ✅ KPI_DASHBOARD_DESIGN.md - Added manual refresh to performance considerations
- ✅ KPI_OPTIMIZATION_IMPLEMENTATION_PLAN.md - Updated Tier 1 description

**Next Steps:**
- Monitor user refresh patterns to validate 75-83% reduction
- Consider adding optional auto-refresh toggle for power users who want it
- Apply same pattern to other real-time KPIs as they're implemented

