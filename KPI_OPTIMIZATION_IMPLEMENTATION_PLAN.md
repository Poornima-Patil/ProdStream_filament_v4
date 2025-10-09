# KPI Performance Optimization - Complete Implementation Plan

**Project:** ProdStream Manufacturing KPI System
**Version:** 1.0
**Date:** October 8, 2025
**Target:** 90+ KPIs with Multi-Tenancy Support

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current State Analysis](#current-state-analysis)
3. [Proposed Architecture](#proposed-architecture)
4. [Database Schema Design](#database-schema-design)
5. [Redis Cache Configuration](#redis-cache-configuration)
6. [Implementation Roadmap](#implementation-roadmap)
7. [Code Structure & Design Patterns](#code-structure--design-patterns)
8. [Performance Optimization Techniques](#performance-optimization-techniques)
9. [Multi-Tenancy Strategy](#multi-tenancy-strategy)
10. [Report Generation System](#report-generation-system)
11. [Testing Strategy](#testing-strategy)
12. [Deployment Plan](#deployment-plan)
13. [Monitoring & Maintenance](#monitoring--maintenance)
14. [Performance Projections](#performance-projections)
15. [Risk Mitigation](#risk-mitigation)

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
- **Tier 1:** 18 real-time KPIs (1-5 min cache)
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

## Database Schema Design

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

#### 6. kpi_monthly_aggregates

**Purpose:** Historical monthly data (immutable after month ends)

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
php artisan make:migration create_kpi_shift_summaries_table
php artisan make:migration create_kpi_daily_summaries_table
php artisan make:migration create_kpi_machine_daily_table
php artisan make:migration create_kpi_operator_daily_table
php artisan make:migration create_kpi_part_daily_table
php artisan make:migration create_kpi_monthly_aggregates_table
php artisan make:migration create_kpi_reports_table
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
```

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
- **Risk:** Summary tables grow too large
- **Mitigation:** Archive old data (>1 year), partition tables, implement data retention policy

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

# Database maintenance
php artisan kpi:archive --older-than=365days
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

**Document Version:** 1.0
**Status:** Ready for Implementation
**Estimated Timeline:** 7-8 weeks
**Last Updated:** October 8, 2025
