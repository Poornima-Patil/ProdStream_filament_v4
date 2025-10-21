# Work Order Status Distribution - Dashboard & Analytics Implementation

## Overview

The Work Order Status Distribution feature provides two distinct modes for viewing and analyzing work order data:

1. **Dashboard Mode** - Real-time snapshot of today's work order status
2. **Analytics Mode** - Historical analysis of work order status distribution over custom time periods

## Architecture

### Service Layer Architecture

The implementation follows a two-tier service architecture:

```
┌─────────────────────────────────────────────────────────────┐
│                  KPIAnalyticsDashboard                      │
│                    (Filament Page)                          │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ getWorkOrderStatusData()
                       │
          ┌────────────┴────────────┐
          │                         │
    ┌─────▼─────┐            ┌──────▼──────┐
    │ Dashboard │            │  Analytics  │
    │   Mode    │            │    Mode     │
    └─────┬─────┘            └──────┬──────┘
          │                         │
  ┌───────▼────────┐        ┌───────▼────────────┐
  │ RealTimeKPI    │        │ OperationalKPI     │
  │   Service      │        │    Service         │
  │  (Tier 1)      │        │   (Tier 2)         │
  └────────────────┘        └────────────────────┘
```

### Key Components

#### 1. Page Component
- **File**: `app/Filament/Admin/Pages/KPIAnalyticsDashboard.php`
- **Responsibility**: Route requests to appropriate service based on mode

#### 2. Real-Time Service (Tier 1)
- **File**: `app/Services/KPI/RealTimeKPIService.php`
- **Method**: `getCurrentWorkOrderStatus()`
- **Purpose**: Dashboard mode - real-time data for TODAY only

#### 3. Operational Service (Tier 2)
- **File**: `app/Services/KPI/OperationalKPIService.php`
- **Method**: `getWorkOrderStatusAnalytics()`
- **Purpose**: Analytics mode - historical data with custom date ranges

## Dashboard Mode

### Purpose
Provides a **real-time snapshot** of work order status for today, categorized into:
- **Planned for Today**: Work orders assigned and scheduled to start today
- **Real-Time Execution**: Currently active work orders (Start, Hold, Completed, Closed)

### Data Scope

```php
// TODAY's data only
$today = now()->startOfDay();
$endOfToday = now()->endOfDay();
```

#### Section 1: Planned for Today
- **Assigned**: Work orders with `status = 'Assigned'` AND `start_time` between today's start and end

#### Section 2: Real-Time Execution
- **Hold**: ALL work orders with `status = 'Hold'` (no date filter)
- **Start**: ALL work orders with `status = 'Start'` (no date filter)
- **Completed**: Work orders that changed to 'Completed' status TODAY (via `work_order_logs`)
- **Closed**: Work orders that changed to 'Closed' status TODAY (via `work_order_logs`)

### Implementation Details

```php
public function getCurrentWorkOrderStatus(bool $skipCache = false): array
{
    // Section 1: Assigned WOs scheduled for TODAY
    $assignedWOs = WorkOrder::where('factory_id', $this->factory->id)
        ->where('status', 'Assigned')
        ->whereBetween('start_time', [$today, $endOfToday])
        ->get();

    // Section 2: Real-Time Execution
    // Start: ALL currently running
    $startWOs = WorkOrder::where('factory_id', $this->factory->id)
        ->where('status', 'Start')
        ->get();

    // Hold: ALL currently on hold
    $holdWOs = WorkOrder::where('factory_id', $this->factory->id)
        ->where('status', 'Hold')
        ->get();

    // Completed: Status changed to Completed TODAY
    $completedWOIds = WorkOrderLog::whereBetween('changed_at', [$today, $endOfToday])
        ->where('status', 'Completed')
        ->pluck('work_order_id')
        ->unique();

    $completedWOs = WorkOrder::where('factory_id', $this->factory->id)
        ->where('status', 'Completed')
        ->whereIn('id', $completedWOIds)
        ->get();

    // Closed: Status changed to Closed TODAY
    // Similar logic as Completed
}
```

### Cache Strategy
- **Cache Key**: `current_work_order_status`
- **TTL**: 300 seconds (5 minutes)
- **Skip Cache**: Available via manual refresh button

### Data Structure

```php
[
    'status_distribution' => [
        'hold' => [
            'count' => 5,
            'work_orders' => [
                [
                    'id' => 123,
                    'wo_number' => 'WO-001',
                    'machine_name' => 'Machine A',
                    'hold_reason' => 'Material shortage',
                    'hold_since' => '2025-10-21 08:30:00',
                    'hold_duration' => '2 hours',
                    // ... other fields
                ]
            ]
        ],
        'start' => [...],
        'assigned' => [...],
        'completed' => [...],
        'closed' => [...]
    ],
    'total_work_orders' => 25,
    'updated_at' => '2025-10-21 10:45:00'
]
```

## Analytics Mode

### Purpose
Provides **historical analysis** of work order status distribution over custom time periods with optional comparison to previous periods.

### Data Scope

```php
// Custom date range based on time period selection
[$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

// Examples:
// - 'yesterday': 2025-10-20 00:00:00 to 2025-10-20 23:59:59
// - 'last_week': 2025-10-14 00:00:00 to 2025-10-20 23:59:59
// - '30d': Last 30 days
// - 'custom': User-defined date range
```

### Key Differences from Dashboard Mode

| Aspect | Dashboard Mode | Analytics Mode |
|--------|---------------|----------------|
| **Time Scope** | Today only | Custom date range |
| **Data Source** | Current status + Today's logs | Work order logs within period |
| **Hold/Start** | ALL currently in that status | Had that status during period |
| **Completed/Closed** | Changed to status TODAY | Changed to status in period |
| **Assigned** | Scheduled for TODAY | Had assigned status in period |
| **Purpose** | Real-time monitoring | Historical analysis & trends |

### Implementation Details

```php
public function getWorkOrderStatusAnalytics(array $options): array
{
    $period = $options['time_period'] ?? 'yesterday';
    $enableComparison = $options['enable_comparison'] ?? false;

    [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

    // Fetch work orders that had activity during this period
    $workOrders = WorkOrder::where('factory_id', $this->factory->id)
        ->whereHas('workOrderLogs', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('changed_at', [$startDate, $endDate]);
        })
        ->with([
            'machine',
            'operator.user',
            'bom.purchaseOrder.partNumber',
            'workOrderLogs' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('changed_at', [$startDate, $endDate])
                  ->with('holdReason')
                  ->orderBy('changed_at', 'desc');
            }
        ])
        ->get();

    // Track all statuses each work order had during the period
    foreach ($workOrders as $wo) {
        $statuses = $wo->workOrderLogs
            ->pluck('status')
            ->unique()
            ->map(fn($s) => strtolower($s));

        // A work order can appear in multiple status groups
        // if it transitioned through different states
        foreach ($statuses as $status) {
            $statusDistribution[$status]['work_orders'][] = $woData;
            $workOrderStatusCounts[$status]++;
        }
    }
}
```

### Status Counting Logic

**Important**: In Analytics mode, a single work order can appear in multiple status categories if it transitioned through different states during the selected period.

Example:
```
Work Order WO-123 during Oct 15-20:
- Oct 15 10:00: Changed to "Assigned"
- Oct 16 08:00: Changed to "Start"
- Oct 17 15:00: Changed to "Hold"
- Oct 18 09:00: Changed to "Start"
- Oct 19 16:00: Changed to "Completed"

Analytics Result for Oct 15-20:
✓ Counted in "Assigned" (was assigned on Oct 15)
✓ Counted in "Start" (was started on Oct 16 & Oct 18)
✓ Counted in "Hold" (was on hold on Oct 17)
✓ Counted in "Completed" (completed on Oct 19)

Total appears in: 4 different status categories
```

### Comparison Feature

Analytics mode supports comparing the selected period with a previous period:

```php
if ($enableComparison) {
    [$compStart, $compEnd] = $this->getComparisonDateRange(
        $startDate,
        $endDate,
        $comparisonType
    );

    $comparisonData = $this->fetchWorkOrderStatusDistribution($compStart, $compEnd);

    $result['comparison_analysis'] = $this->calculateWorkOrderStatusComparison(
        $primaryData['summary'],
        $comparisonData['summary']
    );
}
```

#### Comparison Types
- **previous_period**: Same duration before the selected period
- **previous_week**: Previous week
- **previous_month**: Previous month
- **previous_quarter**: Previous quarter
- **previous_year**: Same period last year

#### Comparison Metrics

```php
[
    'total' => [
        'current' => 125,
        'previous' => 110,
        'difference' => 15,
        'percentage_change' => 13.64,
        'trend' => 'up'
    ],
    'start' => [
        'current' => 45,
        'previous' => 38,
        'difference' => 7,
        'percentage_change' => 18.42,
        'trend' => 'up',
        'status' => 'improved'  // More work orders running is good
    ],
    'hold' => [
        'current' => 8,
        'previous' => 12,
        'difference' => -4,
        'percentage_change' => -33.33,
        'trend' => 'down',
        'status' => 'improved'  // Fewer holds is good
    ],
    // ... other statuses
]
```

### Data Structure

```php
[
    'status_distribution' => [
        'hold' => ['count' => 15, 'work_orders' => [...]],
        'start' => ['count' => 45, 'work_orders' => [...]],
        'assigned' => ['count' => 30, 'work_orders' => [...]],
        'completed' => ['count' => 85, 'work_orders' => [...]],
        'closed' => ['count' => 80, 'work_orders' => [...]]
    ],
    'total_work_orders' => 125,  // Note: Can be > sum of counts due to status transitions
    'updated_at' => '2025-10-21 10:45:00',
    'period_label' => 'Last 7 Days',
    'date_range' => [
        'start' => '2025-10-14',
        'end' => '2025-10-20'
    ],
    'primary_period' => [
        'start_date' => '2025-10-14',
        'end_date' => '2025-10-20',
        'label' => 'Last 7 Days',
        'summary' => [
            'total' => 125,
            'assigned_count' => 30,
            'start_count' => 45,
            'hold_count' => 15,
            'completed_count' => 85,
            'closed_count' => 80,
            'assigned_pct' => 24.0,
            'start_pct' => 36.0,
            'hold_pct' => 12.0,
            'completed_pct' => 68.0,
            'closed_pct' => 64.0
        ]
    ],
    'comparison_period' => [...],  // If comparison enabled
    'comparison_analysis' => [...]  // If comparison enabled
]
```

## Page Controller Implementation

### Mode Switching Logic

```php
// app/Filament/Admin/Pages/KPIAnalyticsDashboard.php

public function getWorkOrderStatusData(): array
{
    $factory = Auth::user()->factory;

    if (!$factory) {
        return $this->getEmptyWorkOrderData();
    }

    // DECISION POINT: Dashboard vs Analytics
    if ($this->kpiMode === 'dashboard') {
        // Real-time data for TODAY
        $service = new RealTimeKPIService($factory);
        $data = $service->getCurrentWorkOrderStatus($this->skipCache);

        $this->skipCache = false;
        return $data;
    }

    // Historical analysis for selected period
    $service = new OperationalKPIService($factory);

    return $service->getWorkOrderStatusAnalytics([
        'time_period' => $this->timePeriod,
        'date_from' => $this->dateFrom,
        'date_to' => $this->dateTo,
        'enable_comparison' => $this->enableComparison,
        'comparison_type' => $this->comparisonType,
    ]);
}
```

### Page Properties

```php
// Mode control
public string $kpiMode = 'dashboard';  // 'dashboard' or 'analytics'

// Analytics filters
public string $timePeriod = 'yesterday';
public ?string $dateFrom = null;
public ?string $dateTo = null;
public bool $enableComparison = false;
public string $comparisonType = 'previous_period';

// Pagination for each status group
public int $woHoldPage = 1;
public int $woStartPage = 1;
public int $woAssignedPage = 1;
public int $woCompletedPage = 1;
public int $woClosedPage = 1;

// Expandable sections
public bool $woHoldExpanded = true;
public bool $woStartExpanded = true;
public bool $woAssignedExpanded = true;
public bool $woCompletedExpanded = false;
public bool $woClosedExpanded = false;
```

## Frontend Implementation

### View File
- **File**: `resources/views/filament/admin/pages/kpi-analytics-dashboard.blade.php`

### Mode Toggle UI

```blade
{{-- Dashboard / Analytics Mode Toggle --}}
<div class="flex gap-2 mb-4">
    <button
        wire:click="setKPIMode('dashboard')"
        class="px-4 py-2 rounded {{ $kpiMode === 'dashboard' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}"
    >
        Dashboard
    </button>
    <button
        wire:click="setKPIMode('analytics')"
        class="px-4 py-2 rounded {{ $kpiMode === 'analytics' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}"
    >
        Analytics
    </button>
</div>

{{-- Analytics Filters (only shown in analytics mode) --}}
@if($kpiMode === 'analytics')
    {{ $this->form }}
@endif
```

### Status Cards Display

```blade
@php
    $woData = $this->getWorkOrderStatusData();
@endphp

{{-- Dashboard Mode: Categorized View --}}
@if($kpiMode === 'dashboard')
    {{-- SECTION 1: Planned for Today --}}
    <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
        <h3>Assigned</h3>
        <div class="text-3xl font-bold">
            {{ $woData['status_distribution']['assigned']['count'] ?? 0 }}
        </div>
        <div class="text-xs">Work orders scheduled for today</div>
    </div>

    {{-- SECTION 2: Real-Time Execution --}}
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-yellow-50 border-2 border-yellow-200 rounded-lg p-4">
            <h3>Hold</h3>
            <div class="text-3xl font-bold">
                {{ $woData['status_distribution']['hold']['count'] ?? 0 }}
            </div>
            <div class="text-xs">Currently on hold</div>
        </div>
        {{-- Start, Completed, Closed cards --}}
    </div>
@endif

{{-- Analytics Mode: Historical View --}}
@if($kpiMode === 'analytics')
    <div class="bg-white rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3>Work Order Status Distribution</h3>
            <span class="text-sm text-gray-500">
                {{ $woData['period_label'] ?? 'Yesterday' }}
                ({{ $woData['date_range']['start'] ?? '' }} to {{ $woData['date_range']['end'] ?? '' }})
            </span>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-5 gap-4">
            @foreach(['assigned', 'start', 'hold', 'completed', 'closed'] as $status)
                <div class="border rounded-lg p-4">
                    <div class="text-xs uppercase">{{ $status }}</div>
                    <div class="text-2xl font-bold">
                        {{ $woData['status_distribution'][$status]['count'] ?? 0 }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $woData['primary_period']['summary'][$status . '_pct'] ?? 0 }}%
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Comparison Analysis (if enabled) --}}
        @if($enableComparison && isset($woData['comparison_analysis']))
            <div class="mt-6 border-t pt-4">
                <h4>Comparison with {{ $woData['comparison_period']['label'] }}</h4>
                {{-- Comparison metrics display --}}
            </div>
        @endif
    </div>
@endif
```

## Pagination Implementation

Each status group has independent pagination:

```php
public function getPaginatedWorkOrders(array $workOrders, string $status): array
{
    $page = match ($status) {
        'hold' => $this->woHoldPage,
        'start' => $this->woStartPage,
        'assigned' => $this->woAssignedPage,
        'completed' => $this->woCompletedPage,
        'closed' => $this->woClosedPage,
        default => 1,
    };

    $total = count($workOrders);
    $totalPages = max(1, ceil($total / $this->perPage));
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
```

## Cache Management

### Dashboard Mode Caching
```php
// Cache for 5 minutes
$cacheKey = 'current_work_order_status';
$cacheTTL = 300;

// Manual refresh clears cache
public function refreshData(): void
{
    $this->skipCache = true;
}
```

### Analytics Mode Caching

Analytics mode uses **intelligent caching** with variable TTL based on data freshness.

#### Implementation

```php
// app/Services/KPI/OperationalKPIService.php

public function getWorkOrderStatusAnalytics(array $options): array
{
    $period = $options['time_period'] ?? 'yesterday';

    // Unique cache key includes ALL filter parameters
    $cacheKey = "work_order_status_analytics_{$period}_" . md5(json_encode($options));

    // TTL varies based on period
    $cacheTTL = $this->getCacheTTL($period);

    return $this->getCachedKPI($cacheKey, function () use (...) {
        // Fetch and process data...
    }, $cacheTTL);
}
```

#### Cache Key Components

The cache key is constructed from:
1. **Base prefix**: `work_order_status_analytics_`
2. **Time period**: The selected period (yesterday, last_week, etc.)
3. **MD5 hash of options**: Includes all filter parameters
   - `time_period`
   - `date_from` (for custom ranges)
   - `date_to` (for custom ranges)
   - `enable_comparison`
   - `comparison_type`

**Example Cache Keys**:
```php
// Yesterday without comparison
work_order_status_analytics_yesterday_a3f8b29c4e1d...

// Last week with comparison
work_order_status_analytics_last_week_9e2d5c7a1b4f...

// Custom range with comparison
work_order_status_analytics_custom_5f1a8c2e9d3b...
```

This ensures that **different filter combinations create separate cache entries**.

#### Cache TTL Strategy

```php
// app/Services/KPI/BaseKPIService.php

protected function getCacheTTL(string $period): int
{
    return match($period) {
        // Current/Recent data - shorter TTL (data might still change)
        'today'        => 300,      // 5 minutes
        'yesterday'    => 900,      // 15 minutes
        'this_week'    => 900,      // 15 minutes
        '7d'           => 1800,     // 30 minutes
        '14d'          => 1800,     // 30 minutes

        // Recent historical data - medium TTL
        'last_week'    => 3600,     // 1 hour
        'this_month'   => 3600,     // 1 hour
        '30d'          => 3600,     // 1 hour

        // Older historical data - longer TTL (rarely changes)
        'last_month'   => 21600,    // 6 hours
        '60d'          => 21600,    // 6 hours
        '90d'          => 21600,    // 6 hours
        'this_quarter' => 21600,    // 6 hours
        'this_year'    => 21600,    // 6 hours

        // Default
        default        => 1800,     // 30 minutes
    };
}
```

#### TTL Reasoning

| Period Type | TTL | Reasoning |
|------------|-----|-----------|
| **Today** | 5 min | Data is actively changing throughout the day |
| **Yesterday/This Week** | 15 min | Recent but mostly stable data |
| **Last Week/This Month** | 1 hour | Historical data that won't change |
| **Older Periods** | 6 hours | Old data that is completely static |

#### Cache Invalidation

Unlike Dashboard mode which has a manual "Refresh" button, Analytics mode does **NOT** support manual cache clearing via `skipCache`.

**Cache refreshes automatically when**:
1. **TTL expires** based on the period selected
2. **User changes ANY filter** (generates new cache key):
   - Changes time period
   - Selects different dates
   - Enables/disables comparison
   - Changes comparison type

**Example Scenarios**:

```php
// Scenario 1: First load
User selects: "Last Week"
→ Cache MISS (no cache exists)
→ Fetch data from database
→ Store in cache for 1 hour
→ Return data

// Scenario 2: Same selection within TTL
User selects: "Last Week" (again, within 1 hour)
→ Cache HIT
→ Return cached data (no database query)

// Scenario 3: TTL expired
User selects: "Last Week" (after 1+ hours)
→ Cache MISS (expired)
→ Fetch fresh data from database
→ Store in cache for 1 hour
→ Return data

// Scenario 4: Different filter
User selects: "Last Month"
→ Cache MISS (different cache key)
→ Fetch data for last month
→ Store in NEW cache entry for 6 hours
→ Return data

// Scenario 5: Enable comparison
User enables comparison with previous period
→ Cache MISS (MD5 hash changed due to new option)
→ Fetch primary + comparison data
→ Store in cache
→ Return data
```

#### Performance Benefits

**Reduced database load**:
- Historical data is expensive to compute (joins, aggregations, log analysis)
- Caching prevents redundant queries for the same period
- Longer TTLs for older data (which never changes)

**Faster response times**:
- First load: 2-5 seconds (database query)
- Cached load: 50-200ms (cache retrieval)
- **10-100x faster** for cached requests

**Smart TTL allocation**:
- Recent data: Frequent refreshes ensure accuracy
- Old data: Rare refreshes save resources (data is static)

#### Cache Storage

The cache uses Laravel's configured cache driver:
```php
// Default: File-based cache
Cache::remember($cacheKey, $cacheTTL, function () {
    // Compute expensive analytics...
});
```

**Cache location** (if using file driver):
- `storage/framework/cache/data/`

#### Monitoring Cache Performance

To check if cache is working:

```php
// Add this temporarily to test caching
use Illuminate\Support\Facades\Log;

public function getWorkOrderStatusAnalytics(array $options): array
{
    $cacheKey = "work_order_status_analytics_{$period}_" . md5(json_encode($options));

    if (Cache::has($cacheKey)) {
        Log::info("CACHE HIT: {$cacheKey}");
    } else {
        Log::info("CACHE MISS: {$cacheKey}");
    }

    // ... rest of code
}
```

Then check `storage/logs/laravel.log` to see cache hits/misses.

## Time Period Options

Analytics mode supports these time periods:

```php
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
'custom' => 'Custom Date Range'
```

## Usage Examples

### Example 1: View Today's Work Orders (Dashboard Mode)
```php
// User clicks "Dashboard" button
$this->setKPIMode('dashboard');

// Page fetches real-time data
$woData = $this->getWorkOrderStatusData();

// Returns:
// - Assigned: WOs scheduled to start today
// - Start: ALL currently running WOs
// - Hold: ALL currently on-hold WOs
// - Completed: WOs completed today
// - Closed: WOs closed today
```

### Example 2: Analyze Last Month (Analytics Mode)
```php
// User clicks "Analytics" button
$this->setKPIMode('analytics');

// User selects "Last Month" from dropdown
$this->timePeriod = 'last_month';

// Page fetches historical data
$woData = $this->getWorkOrderStatusData();

// Returns:
// - All WOs that had status changes during last month
// - Work orders can appear in multiple statuses if they transitioned
// - Summary with counts and percentages
```

### Example 3: Compare This Week vs Last Week
```php
// User enables comparison
$this->enableComparison = true;
$this->comparisonType = 'previous_week';
$this->timePeriod = 'this_week';

// Page fetches both periods
$woData = $this->getWorkOrderStatusData();

// Returns:
// - Primary period: This week's data
// - Comparison period: Last week's data
// - Comparison analysis: Differences, trends, percentage changes
```

## Key Takeaways

1. **Dashboard Mode** = Real-time monitoring of TODAY's work orders
2. **Analytics Mode** = Historical analysis with custom date ranges
3. **Hold/Start in Dashboard** = Current status (no date filter)
4. **Hold/Start in Analytics** = Had that status during the selected period
5. **Work orders in Analytics** can appear in multiple status categories
6. **Comparison feature** only available in Analytics mode
7. **Independent pagination** for each status group
8. **Smart caching** with different TTLs based on data recency

## Related Documentation

- [Machine Status Analytics](./MACHINE_STATUS_ANALYTICS.md)
- [Production Schedule Adherence](./PRODUCTION_SCHEDULE_ADHERENCE.md)
- [KPI Classification Guide](./KPI_CLASSIFICATION_GUIDE.md)
