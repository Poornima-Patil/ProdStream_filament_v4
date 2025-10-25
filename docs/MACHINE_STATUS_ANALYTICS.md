# Machine Status - Analytics Mode Documentation

**IMPORTANT NOTE:** This document describes the **Machine Status KPI**, which tracks machine status distribution (Running, Hold, Scheduled, Idle). For machine utilization percentages and productivity metrics, see [MACHINE_UTILIZATION_RATE.md](MACHINE_UTILIZATION_RATE.md).

## Table of Contents
1. [Overview](#overview)
2. [Analytics Display Components](#analytics-display-components)
3. [Metrics Definitions](#metrics-definitions)
4. [Data Sources](#data-sources)
5. [Calculation Details](#calculation-details)
6. [Data Population](#data-population)
7. [Time Periods](#time-periods)
8. [Comparison Mode](#comparison-mode)
9. [Use Cases & Examples](#use-cases--examples)
10. [Future Enhancements](#future-enhancements)

---

## Overview

### What is Machine Status Analytics Mode?

Machine Status Analytics Mode provides **historical analysis of machine status distribution** over time. It shows how many machines were Running, on Hold, Scheduled (Assigned), or Idle on each day, allowing users to identify patterns, compare time periods, and understand machine utilization from a status perspective.

### Machine Status vs Machine Utilization

**Two Separate KPIs:**

| KPI | What It Shows | Key Metrics |
|-----|--------------|-------------|
| **Machine Status** (This Document) | Distribution of machine states | Running, Hold, Scheduled, Idle counts |
| **Machine Utilization** | Productivity percentages | Scheduled Utilization %, Active Utilization % |

**Important:** These are distinct KPIs. Machine Status shows *what state machines are in*, while Machine Utilization shows *how productively time is being used*.

### Dashboard Mode vs Analytics Mode

| Feature | Dashboard Mode | Analytics Mode |
|---------|---------------|----------------|
| **Data Source** | Real-time from `work_orders` table | Historical aggregation from `work_orders` |
| **Refresh Rate** | Manual refresh button (on-demand) | Calculated on-demand with caching |
| **Time Scope** | Current day only | Any historical date range |
| **Purpose** | Monitor what's happening NOW | Analyze status patterns over time |
| **Display** | Live status tables by machine | Summary cards + daily breakdown table |
| **Use Case** | "What machines are running right now?" | "How many machines were idle last week?" |

### When to Use Each Mode

**Use Dashboard Mode when:**
- You need to see current machine status
- You want to monitor today's production
- You need to identify machines on hold RIGHT NOW
- You're managing real-time operations
- Click the **Refresh** button to fetch the latest data on-demand

**Use Analytics Mode when:**
- You want to analyze historical trends
- You need to compare time periods
- You're preparing performance reports
- You want to identify patterns over weeks/months

---

## Analytics Display Components

### Dashboard Mode Controls

**Manual Refresh Button:**
- Located in the header next to "Machine Status" title
- Click to fetch fresh data from the database
- Shows a spinning icon while loading
- Updates the "Last Updated" timestamp
- Bypasses cache to ensure fresh data

**Benefits:**
- Reduces unnecessary server load
- User controls when to update data
- Better performance (no background polling)
- Clear visual feedback during refresh

### 1. Summary Cards (Top Section)

Displays 4 key metrics showing average machine counts across the selected time period:

```
┌─────────────────┬─────────────────┬─────────────────┬─────────────────┐
│  Avg Running    │   Avg On Hold   │  Avg Scheduled  │    Avg Idle     │
│      4.2        │      1.3        │      2.1        │      0.4        │
│  (52.5% of 8)   │  (16.3% of 8)   │  (26.3% of 8)   │   (5.0% of 8)   │
└─────────────────┴─────────────────┴─────────────────┴─────────────────┘
```

**With Comparison Enabled:**
Each card shows trend indicators comparing current vs previous period:
- ↑ +0.5 machines (+12%) - for Running (green = good)
- ↓ -0.3 machines (-19%) - for Hold (green = good, fewer holds)
- ↑ +0.2 machines (+10%) - for Scheduled (neutral)
- ↓ -0.1 machines (-20%) - for Idle (green = good, fewer idle)

### 2. Daily Breakdown Table (Main Content)

Shows day-by-day status distribution for the selected period:

| Date | Running | Hold | Scheduled | Idle | Visual Distribution |
|------|---------|------|-----------|------|---------------------|
| Oct 14, 2025 | 5 (63%) | 1 (13%) | 2 (25%) | 0 (0%) | ████████░░░░ |
| Oct 13, 2025 | 4 (50%) | 2 (25%) | 2 (25%) | 0 (0%) | ██████████ |
| Oct 12, 2025 | 6 (75%) | 0 (0%) | 1 (13%) | 1 (13%) | ████████░░░░ |

**Features:**
- **Paginated**: Shows 10 days per page (configurable)
- **Visual Distribution**: Color-coded progress bars with borders showing status breakdown
- **Color Coding**: Green (Running), Yellow (Hold), Blue (Scheduled), Gray (Idle)

### 3. Comparison Mode (Optional)

When enabled, shows:
- Current period metrics vs comparison period metrics
- Percentage change for each metric
- Trend indicators (↑ improved, ↓ declined)
- Color coding (green = better, red = worse)

---

## Metrics Definitions

### Machine Status Overview

Machine Status tracks the **count of machines in each status category**. Each machine can be in one of four states on any given day:

1. **Running** - Machines actively producing (work order status = 'Start')
2. **Hold** - Machines paused due to issues (work order status = 'Hold')
3. **Scheduled** - Machines with assigned work not yet started (work order status = 'Assigned')
4. **Idle** - Machines with no work orders scheduled

### Status Determination Logic

For each day, each machine is categorized based on work orders scheduled for that day:

```
For Machine X on Date Y:
1. Get all work orders where start_time falls on Date Y
2. Check statuses of those work orders:
   - If any WO has status 'Start' → Machine is RUNNING
   - Else if any WO has status 'Hold' → Machine is ON HOLD
   - Else if any WO has status 'Assigned' → Machine is SCHEDULED
   - Else (no work orders) → Machine is IDLE
```

**Priority Order:** Running > Hold > Scheduled > Idle
- A machine with multiple work orders uses the highest priority status

### 1. Average Running

**What it measures:** Average number of machines actively running (producing) per day

**Formula:**
```
Average Running = Sum of Running Machines across all days / Number of Days

Where:
- Running Machines (per day) = COUNT(DISTINCT machine_id) where work order status = 'Start'
```

**Example:**
```
Factory with 8 machines, analyzing Last 7 Days:

Oct 14: 5 machines running
Oct 13: 4 machines running
Oct 12: 6 machines running
Oct 11: 5 machines running
Oct 10: 4 machines running
Oct 9:  5 machines running
Oct 8:  5 machines running

Average Running = (5+4+6+5+4+5+5) / 7 = 4.86 machines
Percentage: 4.86 / 8 = 60.7% of total machines
```

**Interpretation:**
- **6-8 machines**: Excellent capacity utilization
- **4-6 machines**: Good utilization
- **2-4 machines**: Moderate (check workload)
- **0-2 machines**: Poor (investigate capacity issues)

---

### 2. Average On Hold

**What it measures:** Average number of machines on hold (paused) per day

**Formula:**
```
Average Hold = Sum of Machines on Hold across all days / Number of Days

Where:
- Machines on Hold (per day) = COUNT(DISTINCT machine_id) where work order status = 'Hold'
```

**Example:**
```
Oct 14: 1 machine on hold (material delay)
Oct 13: 2 machines on hold (quality issues)
Oct 12: 0 machines on hold
Oct 11: 1 machine on hold
Oct 10: 2 machines on hold
Oct 9:  1 machine on hold
Oct 8:  1 machine on hold

Average Hold = (1+2+0+1+2+1+1) / 7 = 1.14 machines
Percentage: 1.14 / 8 = 14.3% of total machines
```

**What counts as Hold:**
- Material shortages
- Quality issues requiring inspection
- Equipment failures awaiting repair
- Operator breaks (if work order paused)

**Interpretation:**
- **0-1 machines**: Excellent (minimal disruptions)
- **1-2 machines**: Acceptable (some issues)
- **2-4 machines**: Concerning (investigate root causes)
- **4+ machines**: Critical (systemic problems)

---

### 3. Average Scheduled

**What it measures:** Average number of machines with assigned work not yet started

**Formula:**
```
Average Scheduled = Sum of Scheduled Machines across all days / Number of Days

Where:
- Scheduled Machines (per day) = COUNT(DISTINCT machine_id) where work order status = 'Assigned'
```

**Example:**
```
Oct 14: 2 machines scheduled (work starts later today)
Oct 13: 2 machines scheduled
Oct 12: 1 machine scheduled
Oct 11: 2 machines scheduled
Oct 10: 2 machines scheduled
Oct 9:  3 machines scheduled
Oct 8:  2 machines scheduled

Average Scheduled = (2+2+1+2+2+3+2) / 7 = 2.0 machines
Percentage: 2.0 / 8 = 25% of total machines
```

**Interpretation:**
- **2-4 machines**: Good planning (work queued up)
- **1-2 machines**: Acceptable
- **0-1 machines**: Risk of capacity gaps
- **4+ machines**: Possible scheduling congestion

---

### 4. Average Idle

**What it measures:** Average number of machines with no work orders scheduled

**Formula:**
```
Average Idle = Sum of Idle Machines across all days / Number of Days

Where:
- Idle Machines (per day) = Total Machines - (Running + Hold + Scheduled)
```

**Example:**
```
Factory with 8 machines:

Oct 14: 5 running + 1 hold + 2 scheduled = 0 idle
Oct 13: 4 running + 2 hold + 2 scheduled = 0 idle
Oct 12: 6 running + 0 hold + 1 scheduled = 1 idle
Oct 11: 5 running + 1 hold + 2 scheduled = 0 idle
Oct 10: 4 running + 2 hold + 2 scheduled = 0 idle
Oct 9:  5 running + 1 hold + 3 scheduled = -1 idle → 0 (can't be negative)
Oct 8:  5 running + 1 hold + 2 scheduled = 0 idle

Average Idle = (0+0+1+0+0+0+0) / 7 = 0.14 machines
Percentage: 0.14 / 8 = 1.8% of total machines
```

**Interpretation:**
- **0 machines**: Excellent (full capacity planning)
- **0-1 machines**: Good utilization
- **1-3 machines**: Moderate underutilization
- **3+ machines**: Poor (insufficient workload)

---

###5. Total Machines

**What it measures:** Total number of machines in the factory

**Formula:**
```
Total Machines = COUNT(machines) where factory_id = current_factory
```

**Used in calculations:**
- Percentage calculations for all status metrics
- Baseline for capacity planning

---

### 6. Days Analyzed

**What it measures:** Number of days included in the selected time period

**Example:**
```
Time Period: "Last 7 Days"
Days Analyzed: 7

Time Period: "This Month" (October)
Days Analyzed: 19 (if today is Oct 19)
```

---

## Data Sources

### Primary Tables

#### 1. `work_orders` (Main source for Analytics)

```sql
Fields used:
- factory_id, machine_id
- start_time (determines which day)
- status ('Start', 'Hold', 'Assigned', 'Completed')
```

#### 2. `machines` (For total machine count)

```sql
Fields used:
- id, factory_id
- name, assetId (for display)
```

### Data Query Example

```php
// Get machine status distribution for Oct 14, 2025
$date = '2025-10-14';
$totalMachines = Machine::where('factory_id', $factoryId)->count();

$workOrders = WorkOrder::where('factory_id', $factoryId)
    ->whereBetween('start_time', [
        Carbon::parse($date)->startOfDay(),
        Carbon::parse($date)->endOfDay()
    ])
    ->whereIn('status', ['Start', 'Assigned', 'Hold', 'Completed'])
    ->get(['id', 'machine_id', 'status']);

// Group by status
$machinesRunning = $workOrders->where('status', 'Start')
    ->pluck('machine_id')->unique()->count();

$machinesOnHold = $workOrders->where('status', 'Hold')
    ->pluck('machine_id')->unique()->count();

$machinesScheduled = $workOrders->where('status', 'Assigned')
    ->pluck('machine_id')->unique()->count();

// Machines with any work orders
$machinesWithWork = $workOrders->pluck('machine_id')->unique()->count();
$machinesIdle = $totalMachines - $machinesWithWork;
```

---

## Calculation Details

### How Averages Are Calculated

All "Average" metrics in Machine Status Analytics use the same simple formula:

**Formula:**
```
Average = Sum of all daily counts / Number of days
```

**Process:**
1. **Loop through each day** in the selected date range
2. **Count machines in each status** for that day
3. **Sum all the daily counts** across all days
4. **Divide by the number of days** to get the average

**Concrete Example:**

Selected Period: "Last 7 Days" (Oct 8-14, 2025)
Factory with 8 total machines

```
Day 1 (Oct 14): 5 running, 1 hold, 2 scheduled, 0 idle
Day 2 (Oct 13): 4 running, 2 hold, 2 scheduled, 0 idle
Day 3 (Oct 12): 6 running, 0 hold, 1 scheduled, 1 idle
Day 4 (Oct 11): 5 running, 1 hold, 2 scheduled, 0 idle
Day 5 (Oct 10): 4 running, 2 hold, 2 scheduled, 0 idle
Day 6 (Oct 9):  5 running, 1 hold, 2 scheduled, 0 idle
Day 7 (Oct 8):  5 running, 1 hold, 2 scheduled, 0 idle
```

**Average Running Calculation:**
```
Daily counts: [5, 4, 6, 5, 4, 5, 5]
Sum: 5 + 4 + 6 + 5 + 4 + 5 + 5 = 34 machines
Days: 7
Average: 34 / 7 = 4.86 → Rounded to 4.9 machines
Percentage: (4.9 / 8) × 100 = 61.3% of total machines
```

**Average Hold Calculation:**
```
Daily counts: [1, 2, 0, 1, 2, 1, 1]
Sum: 1 + 2 + 0 + 1 + 2 + 1 + 1 = 8 machines
Days: 7
Average: 8 / 7 = 1.14 → Rounded to 1.1 machines
Percentage: (1.1 / 8) × 100 = 13.8% of total machines
```

**Average Scheduled Calculation:**
```
Daily counts: [2, 2, 1, 2, 2, 2, 2]
Sum: 2 + 2 + 1 + 2 + 2 + 2 + 2 = 13 machines
Days: 7
Average: 13 / 7 = 1.86 → Rounded to 1.9 machines
Percentage: (1.9 / 8) × 100 = 23.8% of total machines
```

**Average Idle Calculation:**
```
Daily counts: [0, 0, 1, 0, 0, 0, 0]
Sum: 0 + 0 + 1 + 0 + 0 + 0 + 0 = 1 machine
Days: 7
Average: 1 / 7 = 0.14 → Rounded to 0.1 machines
Percentage: (0.1 / 8) × 100 = 1.3% of total machines
```

**Code Implementation:**
```php
// In OperationalKPIService.php
$summary = [
    'avg_running' => round(
        array_sum(array_column($dailyBreakdown, 'running')) / count($dailyBreakdown),
        1
    ),
    'avg_hold' => round(
        array_sum(array_column($dailyBreakdown, 'hold')) / count($dailyBreakdown),
        1
    ),
    'avg_scheduled' => round(
        array_sum(array_column($dailyBreakdown, 'scheduled')) / count($dailyBreakdown),
        1
    ),
    'avg_idle' => round(
        array_sum(array_column($dailyBreakdown, 'idle')) / count($dailyBreakdown),
        1
    ),
];
```

**Where:**
- `array_column($dailyBreakdown, 'running')` extracts all running counts from daily data
- `array_sum(...)` adds all the counts together
- `count($dailyBreakdown)` gets the total number of days analyzed
- `round(..., 1)` rounds to 1 decimal place

**Result:** You get the **average number of machines in each status per day** over the selected period.

---

### Service Layer

**File:** `app/Services/KPI/OperationalKPIService.php`

**Method:** `getMachineStatusAnalytics(array $options): array`

**Implementation:**

```php
public function getMachineStatusAnalytics(array $options): array
{
    $period = $options['time_period'] ?? 'yesterday';
    $enableComparison = $options['enable_comparison'] ?? false;
    $comparisonType = $options['comparison_type'] ?? 'previous_period';

    [$startDate, $endDate] = $this->getDateRange($period, $dateFrom, $dateTo);

    // Fetch primary period data
    $primaryData = $this->fetchMachineStatusDistribution($startDate, $endDate);

    $result = [
        'primary_period' => [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'daily_breakdown' => $primaryData['daily'],
            'summary' => $primaryData['summary'],
        ],
    ];

    // Add comparison if enabled
    if ($enableComparison) {
        [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate, $comparisonType);
        $comparisonData = $this->fetchMachineStatusDistribution($compStart, $compEnd);

        $result['comparison_period'] = [
            'start_date' => $compStart->toDateString(),
            'end_date' => $compEnd->toDateString(),
            'daily_breakdown' => $comparisonData['daily'],
            'summary' => $comparisonData['summary'],
        ];

        $result['comparison_analysis'] = $this->calculateStatusDistributionComparison(
            $primaryData['summary'],
            $comparisonData['summary']
        );
    }

    return $result;
}
```

**Method:** `fetchMachineStatusDistribution(Carbon $startDate, Carbon $endDate): array`

This method loops through each day in the date range and calculates the machine status distribution:

```php
protected function fetchMachineStatusDistribution(Carbon $startDate, Carbon $endDate): array
{
    $totalMachines = Machine::where('factory_id', $this->factory->id)->count();
    $dailyBreakdown = [];
    $current = $startDate->copy();

    while ($current->lte($endDate)) {
        $dayStart = $current->copy()->startOfDay();
        $dayEnd = $current->copy()->endOfDay();

        // Get work orders for this day
        $workOrders = WorkOrder::where('factory_id', $this->factory->id)
            ->whereBetween('start_time', [$dayStart, $dayEnd])
            ->whereIn('status', ['Start', 'Assigned', 'Hold', 'Completed'])
            ->get(['id', 'machine_id', 'status']);

        // Group machines by status
        $machinesRunning = $workOrders->where('status', 'Start')
            ->pluck('machine_id')->unique()->count();
        $machinesOnHold = $workOrders->where('status', 'Hold')
            ->pluck('machine_id')->unique()->count();
        $machinesScheduled = $workOrders->where('status', 'Assigned')
            ->pluck('machine_id')->unique()->count();

        // Calculate idle
        $machinesWithWork = $workOrders->pluck('machine_id')->unique()->count();
        $machinesIdle = $totalMachines - $machinesWithWork;

        $dailyBreakdown[] = [
            'date' => $current->toDateString(),
            'running' => $machinesRunning,
            'hold' => $machinesOnHold,
            'scheduled' => $machinesScheduled,
            'idle' => $machinesIdle,
            'total_machines' => $totalMachines,
        ];

        $current->addDay();
    }

    // Calculate summary statistics
    $summary = [
        'avg_running' => round(array_sum(array_column($dailyBreakdown, 'running')) / count($dailyBreakdown), 1),
        'avg_hold' => round(array_sum(array_column($dailyBreakdown, 'hold')) / count($dailyBreakdown), 1),
        'avg_scheduled' => round(array_sum(array_column($dailyBreakdown, 'hold')) / count($dailyBreakdown), 1),
        'avg_idle' => round(array_sum(array_column($dailyBreakdown, 'idle')) / count($dailyBreakdown), 1),
        'total_machines' => $totalMachines,
        'days_analyzed' => count($dailyBreakdown),
        // Percentage calculations
        'avg_running_pct' => round(($summary['avg_running'] / $totalMachines) * 100, 1),
        'avg_hold_pct' => round(($summary['avg_hold'] / $totalMachines) * 100, 1),
        'avg_scheduled_pct' => round(($summary['avg_scheduled'] / $totalMachines) * 100, 1),
        'avg_idle_pct' => round(($summary['avg_idle'] / $totalMachines) * 100, 1),
    ];

    return [
        'daily' => $dailyBreakdown,
        'summary' => $summary,
    ];
}
```

### Caching Strategy

**Cache Key Format:**
```php
$cacheKey = "machine_status_analytics_{$period}_" . md5(json_encode($options));
```

**Cache Store:** Custom `kpi_cache` store (see `TenantKPICache.php`)

**Cache TTL:**
- Yesterday: 3600 seconds (1 hour)
- Last Week: 3600 seconds
- Last Month: 7200 seconds (2 hours)
- Custom ranges: 1800 seconds (30 minutes)

**Cache Tags:** `factory_{factory_id}`, `tier_2`, `kpi`

---

##Comparison Mode Calculations

### Comparison Analysis

**Method:** `calculateStatusDistributionComparison(array $current, array $previous): array`

For each status, the system calculates:

1. **Difference:** `current - previous`
2. **Percentage Change:** `((current - previous) / previous) × 100`
3. **Trend:** 'up' if current > previous, 'down' otherwise
4. **Status:** 'improved' or 'declined' based on whether the change is good or bad

**Example:**

```php
Running:
- Current: 5.2 machines
- Previous: 4.7 machines
- Difference: +0.5
- Percentage Change: +10.6%
- Trend: up
- Status: improved (more machines running is good)

Hold:
- Current: 1.1 machines
- Previous: 1.5 machines
- Difference: -0.4
- Percentage Change: -26.7%
- Trend: down
- Status: improved (fewer machines on hold is good)
```

---

## Time Periods

### Available Time Period Options

Analytics Mode supports the following time periods:

---

## Calculation Details

### Rounding Rules

- **Percentages**: Round to 1 decimal place (75.2%)
- **Counts**: Round to 1 decimal place for averages (4.5 machines)
- **Whole Numbers**: No rounding for daily counts

---

## Data Population

### How Analytics Data is Updated

**Important:** Machine Status Analytics data is now **pre-aggregated** via the `kpi:aggregate-daily` command. Each factory/day snapshot is persisted to `kpi_machine_status_daily`. Analytics reads from this table first and only falls back to live computation when a day has not yet been materialised (for example, “today” before the job runs or immediately after a forced refresh).

### Pre-Aggregated Calculation (Current Implementation)

When a user views Machine Status Analytics:

1. **User Opens Analytics Mode** → Triggers `getMachineStatusAnalytics()` method.
2. **Cache Check** → System checks if the request is already cached.
3. **If Cached** → Returns cached data immediately.
4. **If Not Cached** → Loads rows from `kpi_machine_status_daily` for the requested range.
5. **Fallback** → Missing dates are computed on demand (matching the legacy behaviour) and returned.
6. **Store in Cache** → Saves results for subsequent requests.

**Flow Diagram:**

```
User Views Analytics
       ↓
Check Cache (TenantKPICache tier_2)
       ↓
   Cached? ── Yes ──→ Return Cached Data ✓
       ↓
      No
       ↓
Load kpi_machine_status_daily rows
       ↓
Fallback? ── Any missing dates ──→ Compute from work_orders (legacy path)
       ↓
Build summary statistics
       ↓
Store in cache (with TTL)
       ↓
Return data to user
```

### Cache Implementation

**Cache Store:** Custom `kpi_cache` store (separate from default Laravel cache)

**Persistence Table:** `kpi_machine_status_daily` (populated by `kpi:aggregate-daily`)

**Cache Class:** `App\Support\TenantKPICache`

**Cache Key Format:**
```php
"machine_status_analytics_{$period}_" . md5(json_encode($options))

Examples:
- machine_status_analytics_yesterday_a3f2d8e1...
- machine_status_analytics_last_week_9c4b1a7f...
- machine_status_analytics_30d_5e8d2c9a...
```

**Cache TTL (Time To Live):**

| Time Period | Cache Duration | Reasoning |
|-------------|----------------|-----------|
| Yesterday | 3600 seconds (1 hour) | Historical data, won't change often |
| Last Week | 3600 seconds (1 hour) | Historical data, stable |
| Last Month | 7200 seconds (2 hours) | Large dataset, longer cache OK |
| Custom Range | 1800 seconds (30 minutes) | May vary, shorter cache |
| Today | 300 seconds (5 minutes) | Data actively changing |

**Cache Tags:**
- `factory_{factory_id}` - Isolates cache per factory
- `tier_2` - Groups all Tier 2 KPIs together
- `kpi` - General KPI tag

**Clearing Cache:**

To manually clear Machine Status Analytics cache:

```php
// Clear all Tier 2 KPI cache for a factory
$factory = \App\Models\Factory::first();
$cache = new \App\Support\TenantKPICache($factory);
$cache->flushTier('tier_2');

// Or via Tinker
php artisan tinker
> $factory = \App\Models\Factory::find(1);
> $cache = new \App\Support\TenantKPICache($factory);
> $cache->flushTier('tier_2');
```

### Advantages of On-Demand Approach

✅ **No Background Jobs Needed** - Simpler infrastructure, no scheduler setup
✅ **Always Fresh Data** - Queries latest work order data directly
✅ **Flexible Date Ranges** - Can analyze any custom period without pre-aggregation
✅ **Lower Storage Requirements** - No need for `kpi_machine_daily` table
✅ **Easier to Maintain** - No aggregation logic to keep in sync with work orders

### Disadvantages of On-Demand Approach

❌ **First Load Slower** - Initial query takes time (3-5 seconds for large date ranges)
❌ **Database Load** - Direct queries on `work_orders` table
❌ **Scalability Concerns** - May slow down with millions of work orders
❌ **Repeated Calculations** - Same data recalculated after cache expires

### Performance Characteristics

**Fast Scenarios:**
- Data is cached (subsequent views within TTL period)
- Short date ranges (Yesterday, Last 7 Days)
- Small factory (few machines, limited work orders)

**Slow Scenarios:**
- Cache expired or first load
- Long date ranges (Last 90 Days, This Year)
- Large factory (many machines, thousands of work orders)
- Complex queries with comparisons enabled

**Typical Performance:**

| Scenario | First Load | Cached Load |
|----------|-----------|-------------|
| Yesterday (8 machines) | 0.5-1 second | <100ms |
| Last 7 Days (8 machines) | 1-2 seconds | <100ms |
| Last 30 Days (8 machines) | 3-5 seconds | <150ms |
| Last 90 Days (8 machines) | 8-12 seconds | <200ms |

### Future Enhancement: Pre-Aggregation

**Potential Approach (Not Currently Implemented):**

Create a background job to pre-calculate and store daily metrics:

**Step 1: Create Migration**
```sql
CREATE TABLE kpi_machine_status_daily (
    id BIGINT PRIMARY KEY,
    factory_id INT NOT NULL,
    date DATE NOT NULL,
    machines_running INT DEFAULT 0,
    machines_hold INT DEFAULT 0,
    machines_scheduled INT DEFAULT 0,
    machines_idle INT DEFAULT 0,
    total_machines INT DEFAULT 0,
    calculated_at TIMESTAMP NULL,
    UNIQUE KEY unique_factory_date (factory_id, date)
);
```

**Step 2: Create Artisan Command**
```bash
php artisan make:command AggregateKPIMachineStatus
```

**Step 3: Schedule Daily**
```php
// In bootstrap/app.php or routes/console.php
Schedule::command('kpi:aggregate-machine-status')
    ->dailyAt('01:00')  // Run at 1 AM
    ->timezone('America/New_York');
```

**Benefits of Pre-Aggregation:**
- ✅ Faster analytics load times (query pre-aggregated table)
- ✅ Lower database load (no complex joins)
- ✅ Better for large datasets
- ✅ Enables historical trend analysis

**Trade-offs:**
- ❌ Requires background job infrastructure
- ❌ More storage space needed
- ❌ Additional maintenance complexity
- ❌ Data may be slightly stale (until next aggregation)

**Recommendation:** Implement pre-aggregation if:
- Factory has >20 machines
- Analytics accessed frequently (>50 times/day)
- Work order volume exceeds 1000/day
- Load times consistently >5 seconds

**Current State:** Machine Status Analytics does **not** use background jobs or scheduled commands. All data is calculated on-demand when users view the analytics page.

**For future reference:** If pre-aggregation is implemented, this section will document the aggregation command usage.


---

## Time Periods

### Available Time Period Options

Analytics Mode supports the following time periods:

#### Single Day Options
- **Today**: Current day (uses today's date)
- **Yesterday**: Previous day

#### Week Options
- **This Week**: Monday to Sunday of current week
- **Last Week**: Monday to Sunday of previous week
- **Last 7 Days**: Rolling 7-day window from today

#### Month Options
- **This Month**: 1st to last day of current month
- **Last Month**: 1st to last day of previous month
- **Last 14 Days**: Rolling 14-day window
- **Last 30 Days**: Rolling 30-day window

#### Quarter & Year Options
- **This Quarter**: Q1 (Jan-Mar), Q2 (Apr-Jun), Q3 (Jul-Sep), Q4 (Oct-Dec)
- **This Year**: Jan 1 to Dec 31 of current year
- **Last 60 Days**: Rolling 60-day window
- **Last 90 Days**: Rolling 90-day window

#### Custom Range
- **Custom Date Range**: User selects start and end dates
- Maximum range: No limit (but performance may degrade for very long ranges)

### Time Period Behavior

**For "Today":**
- Shows real-time aggregated data
- May show incomplete metrics if day is ongoing
- Best viewed at end of day for complete data

**For Historical Periods:**
- Shows finalized data calculated on-demand
- Data is stable and won't change
- Better for reporting and analysis

---

## Comparison Mode

### What is Comparison Mode?

Comparison Mode allows you to **compare current period metrics with a previous period** to identify trends, improvements, or declines.

### How to Enable

1. Navigate to Machine Status KPI Analytics Mode
2. Select a time period (e.g., "This Month")
3. Toggle "Compare with previous period" switch to ON
4. Select comparison type from dropdown

### Comparison Types

#### 1. Previous Period (Default)
**Logic:** Compare with period of same duration immediately before

**Example:**
```
Current Period: Oct 1-14 (14 days)
Comparison Period: Sep 17-30 (14 days)
```

#### 2. Previous Week
**Logic:** Compare with same day range in previous week

**Example:**
```
Current Period: Oct 8-14
Comparison Period: Oct 1-7
```

#### 3. Previous Month
**Logic:** Compare with same dates in previous month

**Example:**
```
Current Period: Oct 1-14
Comparison Period: Sep 1-14
```

#### 4. Previous Quarter
**Logic:** Compare with same dates in previous quarter

**Example:**
```
Current Period: Q4 (Oct-Dec) 2025
Comparison Period: Q3 (Jul-Sep) 2025
```

#### 5. Previous Year (Same Period Last Year)
**Logic:** Compare with exact same dates one year ago

**Example:**
```
Current Period: Oct 1-14, 2025
Comparison Period: Oct 1-14, 2024
```

### Comparison Display

#### Summary Cards with Trends
```
┌─────────────────────────────────┐
│ Avg Running                     │
│ 4.5    ↑ +0.5 vs Previous      │
│        (4.0 previously)         │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Avg Hold                        │
│ 1.2    ↓ -0.3 vs Previous      │
│        (1.5 previously)         │
└─────────────────────────────────┘
```

#### Trend Indicators
- **↑ Green**: Improvement for Running machines (more is better)
- **↓ Red**: Decline for Running machines (fewer is worse)
- **↓ Green**: Improvement for Hold/Idle machines (fewer is better)
- **↑ Red**: Decline for Hold/Idle machines (more is worse)

#### Percentage Change Calculation
```
Change % = ((Current - Previous) / Previous) × 100

Example:
Current Avg Running: 4.5 machines
Previous Avg Running: 4.0 machines
Change: ((4.5 - 4.0) / 4.0) × 100 = +12.5%
```

### Interpretation Guide

#### Positive Trends (Good)
- Average Running ↑ (more machines producing)
- Average Hold ↓ (fewer machines paused)
- Average Idle ↓ (fewer machines without work)

#### Negative Trends (Bad)
- Average Running ↓ (fewer machines producing)
- Average Hold ↑ (more machines paused)
- Average Idle ↑ (more machines without work)

---

## Use Cases & Examples

### Use Case 1: Identifying Underused Machines

**Scenario:** Factory manager wants to find machines with low activity

**Steps:**
1. Navigate to Machine Status Analytics
2. Select "This Month" as time period
3. Review daily breakdown to see average running vs idle machines

**Sample Findings:**
```
Average status over the month:
- Running: 5.2 machines (65%)
- Hold: 1.1 machines (14%)
- Scheduled: 1.5 machines (19%)
- Idle: 0.2 machines (3%)

Daily patterns show:
- Machines 1, 2, 4, 7, 8: Consistently running (good)
- Machine 3: Frequently idle (investigation needed)
- Machine 5: Often on hold (quality issues)
```

**Actions:**
- Investigate why Machine-003 is frequently idle
- Check if there's insufficient work scheduled for this machine
- Consider reassigning work orders to utilize idle capacity
- Address quality issues causing Machine-005 to be on hold

---

### Use Case 2: Week-over-Week Status Comparison

**Scenario:** Production supervisor reviews weekly machine status every Monday

**Steps:**
1. Navigate to Machine Status Analytics
2. Select "Last Week" (Oct 7-13)
3. Enable comparison mode: "Previous Week"
4. Review summary cards and daily breakdown

**Sample Findings:**
```
Last Week vs Previous Week:

Avg Running: 5.1 machines ↑ +0.4 machines (+8.5%)
  Previous: 4.7 machines

Avg Hold: 1.2 machines ↓ -0.3 machines (-20%)
  Previous: 1.5 machines

Avg Scheduled: 1.8 machines → (stable)
  Previous: 1.8 machines

Avg Idle: 0.9 machines ↓ -0.1 machines (-10%)
  Previous: 1.0 machines
```

**Interpretation:** Strong week! More machines running, fewer on hold and idle.

---

### Use Case 3: Monthly Status Reporting to Management

**Scenario:** Factory manager prepares monthly performance report

**Steps:**
1. Navigate to Machine Status Analytics
2. Select "Last Month" (September 2025)
3. Enable comparison: "Previous Month" (August 2025)
4. Export or screenshot summary cards for report

**Sample Report Data:**
```
September 2025 Machine Status Summary:

Key Metrics:
- Avg Running: 5.3 machines (66% of 8 total) - Target: 70%
- Avg Hold: 1.2 machines (15% of total)
- Avg Scheduled: 1.3 machines (16% of total)
- Avg Idle: 0.2 machines (3% of total)

Comparison with August:
- Running: +0.4 machines (+8%) ✓ Improved
- Hold: -0.2 machines (-14%) ✓ Fewer issues
- Idle: -0.1 machines (-33%) ✓ Better capacity planning

Highlights:
✓ More machines actively running
✓ Fewer machines on hold (improved issue resolution)
✓ Lower idle time (better work scheduling)

Action Items:
- Continue improvement trend
- Investigate remaining hold causes
- Target 70% average running rate for next month
```

---

### Use Case 4: Identifying Status Patterns

**Scenario:** Operations manager notices production delays

**Steps:**
1. Select "Last 7 Days"
2. Review daily breakdown table
3. Look for days with unusual status patterns
4. Identify specific issues causing delays

**Sample Findings:**
```
Daily Analysis (Oct 8-14):

Oct 10: Only 3 machines running (abnormally low)
- 3 machines on hold (material shortage)
- 2 machines idle (no work scheduled)

Oct 12: Only 4 machines running (below average)
- 2 machines on hold (equipment issues)
- 2 machines scheduled but not started

Pattern: Material shortages and equipment issues causing status problems
```

**Actions:**
- Improve material inventory management to reduce hold status
- Schedule preventive maintenance to reduce equipment-related holds
- Investigate work order scheduling to reduce idle machines

---

## Future Enhancements

### Phase 1: Hold Reason Tracking

**Enhanced Hold Analysis**
- Currently holds are tracked by machine but not by reason
- Add ability to categorize hold reasons:
  - Material shortages
  - Quality issues
  - Equipment failures
  - Operator breaks
  - Tool changes

**Impact:**
- Identify most common hold causes
- Better root cause analysis
- Targeted improvement initiatives

---

### Phase 2: Historical Trend Charts

**Visual Analytics:**
- Line charts showing machine status trends over time
- Day-by-day visualization of Running/Hold/Scheduled/Idle counts
- Comparison overlays for different time periods

**Display:**
- Interactive charts with hover tooltips
- Export to image for reports
- Drill-down to daily details

---

### Phase 3: Shift-Level Status Analytics

**Granular Analysis:**
- Break down machine status by shift (Shift 1, Shift 2, Shift 3)
- Compare shift performance for status distribution
- Identify if certain shifts have more holds or idle machines

**Display:**
```
Shift Performance (Oct 14, 2025):

Shift 1 (6:00-14:00):  Running: 5.2, Hold: 0.8, Scheduled: 1.5, Idle: 0.5
Shift 2 (14:00-22:00): Running: 4.8, Hold: 1.2, Scheduled: 1.7, Idle: 0.3
Shift 3 (22:00-6:00):  Running: 3.9, Hold: 1.8, Scheduled: 2.0, Idle: 0.3

Insight: Shift 3 has fewer running machines - investigate staffing/scheduling
```

---

### Phase 4: Machine-Specific Status History

**Per-Machine Analysis:**
- View individual machine status history over time
- Identify machines with frequent hold status
- Track patterns for specific machines

**Display:**
- Timeline view showing status changes for a machine
- Highlight problematic periods
- Compare machine performance

---

### Phase 5: Predictive Analytics

**Use Machine Learning to:**
- Predict which machines are likely to go on hold
- Forecast machine status patterns based on historical data
- Recommend optimal work order scheduling to minimize idle time
- Identify early warning signs of recurring hold issues

**Example:**
```
Predictive Insights for Machine-003:

⚠️ Warning: High probability (75%) of hold status in next 24 hours
   Based on: Recent hold pattern, material delivery schedule, maintenance logs

   Recommendation: Schedule material delivery or defer work orders
```

---

### Phase 6: Alerts & Notifications

**Real-time Monitoring:**
- Email/SMS alerts when machine status metrics fall below thresholds
- Dashboard notifications for critical issues
- Weekly status summary reports

**Example Rules:**
```
Alert: Average running machines below 50% for 2+ consecutive days
Alert: More than 3 machines on hold simultaneously
Alert: Machine idle for more than 8 hours
Alert: Scheduled machines exceeding capacity
```

---

### Phase 7: Export & Reporting

**Features:**
- Export machine status analytics data to Excel/CSV
- Generate PDF reports with status distribution charts
- Schedule automated email reports
- API endpoints for external BI tools

---

### Phase 8: Advanced Visualizations

**Charts & Graphs:**
- Machine status trend line chart (Running/Hold/Scheduled/Idle over time)
- Status distribution pie chart
- Heat map showing machine status by hour/day
- Stacked bar chart for multi-machine comparison
- Gantt chart for machine status timeline

---

### Phase 9: Multi-Factory Comparison

**For Organizations with Multiple Facilities:**
- Compare machine status distribution across factories
- Identify best practices from top-performing sites
- Benchmark factories against each other

**Example:**
```
Factory Performance Comparison (October 2025):

Factory A: Avg Running 72% ⭐ Top Performer
Factory B: Avg Running 65%
Factory C: Avg Running 58%

Insight: Factory A keeps 14% more machines running than Factory C
Action: Study Factory A's scheduling and maintenance practices
```

---

## Conclusion

Machine Status Analytics Mode provides powerful insights into historical machine performance, enabling data-driven decisions to improve factory efficiency, quality, and productivity.

For questions or suggestions, contact the development team or file an issue in the project repository.

---

## Work Order Status KPI

### Overview

The **Work Order Status KPI** provides visibility into work order progress across the factory, complementing the Machine Status KPI. While Machine Status shows which machines are running/idle, Work Order Status shows what work is planned, actively executing, or completed.

### Dashboard Mode vs Analytics Mode

| Feature | Dashboard Mode | Analytics Mode |
|---------|---------------|----------------|
| **Data Source** | Real-time from `work_orders` & `work_order_logs` tables | Pre-aggregated historical data |
| **Date Filter** | Today only (with specific rules per status) | Any historical date range |
| **Purpose** | Monitor TODAY's work orders | Analyze historical work order trends |
| **Display** | Two-section structure (Planned + Real-Time) | Summary cards + daily breakdown |
| **Use Case** | "What's scheduled/executing today?" | "How many WOs completed last month?" |

---

### Work Order Statuses

The system tracks **5 work order statuses**:

1. **Assigned** - Work order scheduled but not started yet
2. **Start** - Work order actively running (operator producing)
3. **Hold** - Work order paused (material shortage, quality issue, etc.)
4. **Completed** - Work order finished production
5. **Closed** - Work order fully closed (administrative closure)

---

### Dashboard Mode Structure

Dashboard Mode is organized into **two distinct sections** reflecting operational reality:

#### Section 1: PLANNED FOR TODAY 📅

**Purpose:** Shows work orders scheduled to START today

**Status:** Assigned only

**Data Filter:**
```php
WorkOrder::where('status', 'Assigned')
    ->whereBetween('start_time', [today()->startOfDay(), today()->endOfDay()])
```

**Key Points:**
- Only includes work orders with `start_time` = today
- These are WOs planned to begin today but haven't started yet
- Helps operators/planners see upcoming work

**Example:**
```
Planned for Today (5 Work Orders):
- WO-1001: Machine-003, Part#12345, Scheduled: 14:00
- WO-1002: Machine-007, Part#67890, Scheduled: 15:30
- WO-1003: Machine-001, Part#11111, Scheduled: 16:00
...
```

---

#### Section 2: REAL-TIME EXECUTION 🔴

**Purpose:** Shows work orders currently active or completed TODAY

**Statuses:** Start, Hold, Completed, Closed

**Data Filters:**

**1. Start (Running):**
```php
WorkOrder::where('status', 'Start')
    // NO date filter - shows ALL currently running WOs
```
- Shows ALL work orders currently in production
- May include WOs that started yesterday/last week but are still running
- Critical for real-time operations monitoring

**2. Hold (On Hold):**
```php
WorkOrder::where('status', 'Hold')
    // NO date filter - shows ALL currently on-hold WOs
```
- Shows ALL work orders currently paused
- May include WOs that went on hold yesterday
- Requires immediate attention to resolve blocking issues

**3. Completed:**
```php
// Step 1: Find WOs that changed to 'Completed' status TODAY
$completedWOIds = WorkOrderLog::whereBetween('changed_at', [today()->startOfDay(), today()->endOfDay()])
    ->where('status', 'Completed')
    ->pluck('work_order_id');

// Step 2: Get WOs with current status = 'Completed' AND changed today
WorkOrder::where('status', 'Completed')
    ->whereIn('id', $completedWOIds)
```
- Only shows WOs that were marked 'Completed' TODAY
- Uses `work_order_logs` table to check when status changed
- WO completed yesterday won't appear in today's dashboard

**4. Closed:**
```php
// Step 1: Find WOs that changed to 'Closed' status TODAY
$closedWOIds = WorkOrderLog::whereBetween('changed_at', [today()->startOfDay(), today()->endOfDay()])
    ->where('status', 'Closed')
    ->pluck('work_order_id');

// Step 2: Get WOs with current status = 'Closed' AND changed today
WorkOrder::where('status', 'Closed')
    ->whereIn('id', $closedWOIds)
```
- Only shows WOs that were closed TODAY
- Administrative closure separate from completion
- Tracks final WO lifecycle stage

---

### Why Different Filtering Logic?

The filtering logic differs intentionally based on operational needs:

#### Start & Hold: NO Date Filter

**Rationale:**
- A WO started last week but still running today IS operationally relevant TODAY
- A WO on hold since yesterday STILL needs attention TODAY
- Operators need to see ALL active work, regardless of start date

**Example Scenario:**
```
WO-2001 started Monday (Oct 14)
Still running on Friday (Oct 18)

Dashboard on Oct 18 shows:
✓ WO-2001 in "Start" section
→ Operators need to see this active work!
```

#### Assigned: Filtered by start_time = Today

**Rationale:**
- Only shows WOs scheduled to START today
- WOs scheduled for tomorrow aren't relevant today
- Helps with day-to-day planning

**Example Scenario:**
```
WO-3001: start_time = Oct 18, status = Assigned → Shows on Oct 18
WO-3002: start_time = Oct 19, status = Assigned → Shows on Oct 19
```

#### Completed & Closed: Filtered by Status Change Date

**Rationale:**
- Shows productivity achieved TODAY
- WO completed last week is historical, not today's achievement
- Uses `work_order_logs.changed_at` to determine completion date

**Example Scenario:**
```
WO-4001:
- Started: Oct 15
- Status changed to 'Completed': Oct 18 at 14:30
- Dashboard on Oct 18: ✓ Shows in Completed section
- Dashboard on Oct 19: ✗ Not shown (completed yesterday)
```

---

### Data Flow: work_orders vs work_order_logs

#### work_orders Table
```
Fields:
- id, factory_id, machine_id
- start_time, end_time
- status (current status)
- qty, ok_qtys, scrapped_qtys
```

**Purpose:** Current state of work orders

#### work_order_logs Table
```
Fields:
- work_order_id
- status (status at this log entry)
- changed_at (timestamp of status change)
- ok_qtys, scrapped_qtys, fpy
```

**Purpose:** Historical record of status changes

**Why Use Logs for Completed/Closed?**

Without logs:
```php
// ❌ WRONG: This shows WOs currently in 'Completed' status, regardless of WHEN they completed
WorkOrder::where('status', 'Completed')
```
- Could show WOs completed last month
- Doesn't tell us WHEN completion happened

With logs:
```php
// ✓ CORRECT: Shows WOs that changed to 'Completed' TODAY
$completedToday = WorkOrderLog::whereBetween('changed_at', [$today, $endOfToday])
    ->where('status', 'Completed')
    ->pluck('work_order_id');

WorkOrder::where('status', 'Completed')
    ->whereIn('id', $completedToday)
```
- Accurately shows today's completed work
- Based on status change timestamp

---

### Dashboard Display Structure

```
┌─────────────────────────────────────────────────────┐
│  Work Order Status Distribution                     │
│  [Refresh Button]                                   │
├─────────────────────────────────────────────────────┤
│                                                     │
│  📅 PLANNED FOR TODAY                               │
│  (Scheduled to start today)                         │
│                                                     │
│  ┌─────────────────────────────────┐              │
│  │ Assigned: 5 Work Orders          │              │
│  │ WOs scheduled for today          │              │
│  └─────────────────────────────────┘              │
│                                                     │
│  [Collapsible Table: Assigned WOs]                 │
│                                                     │
├─────────────────────────────────────────────────────┤
│                                                     │
│  🔴 REAL-TIME EXECUTION                            │
│  (Currently active or completed today)              │
│                                                     │
│  ┌────────┬────────┬──────────┬────────┐          │
│  │ Hold:3 │Start:8 │Complete:4│Close:2 │          │
│  └────────┴────────┴──────────┴────────┘          │
│                                                     │
│  [Collapsible Table: Hold WOs - Priority]          │
│  [Collapsible Table: Start WOs]                    │
│  [Collapsible Table: Completed WOs]                │
│  [Collapsible Table: Closed WOs]                   │
│                                                     │
└─────────────────────────────────────────────────────┘
```

---

### Service Implementation: RealTimeKPIService

#### Method: getCurrentWorkOrderStatus()

**Purpose:** Fetch and organize work orders for Dashboard Mode

**Implementation Overview:**

```php
public function getCurrentWorkOrderStatus(bool $skipCache = false): array
{
    $today = now()->startOfDay();
    $endOfToday = now()->endOfDay();

    // SECTION 1: PLANNED - Assigned WOs scheduled for TODAY
    $assignedWOs = WorkOrder::where('factory_id', $this->factory->id)
        ->where('status', 'Assigned')
        ->whereBetween('start_time', [$today, $endOfToday])
        ->orderBy('start_time', 'asc')
        ->get();

    // SECTION 2: REAL-TIME EXECUTION

    // Start: ALL currently running (no date filter)
    $startWOs = WorkOrder::where('factory_id', $this->factory->id)
        ->where('status', 'Start')
        ->orderBy('start_time', 'asc')
        ->get();

    // Hold: ALL currently on-hold (no date filter)
    $holdWOs = WorkOrder::where('factory_id', $this->factory->id)
        ->where('status', 'Hold')
        ->orderBy('updated_at', 'desc')
        ->get();

    // Completed: Status changed to 'Completed' TODAY
    $completedWOIds = WorkOrderLog::whereBetween('changed_at', [$today, $endOfToday])
        ->where('status', 'Completed')
        ->pluck('work_order_id')
        ->unique();

    $completedWOs = WorkOrder::where('factory_id', $this->factory->id)
        ->where('status', 'Completed')
        ->whereIn('id', $completedWOIds)
        ->orderBy('updated_at', 'desc')
        ->get();

    // Closed: Status changed to 'Closed' TODAY
    $closedWOIds = WorkOrderLog::whereBetween('changed_at', [$today, $endOfToday])
        ->where('status', 'Closed')
        ->pluck('work_order_id')
        ->unique();

    $closedWOs = WorkOrder::where('factory_id', $this->factory->id)
        ->where('status', 'Closed')
        ->whereIn('id', $closedWOIds)
        ->orderBy('updated_at', 'desc')
        ->get();

    // Combine and process...
    return [
        'status_distribution' => [
            'assigned' => ['count' => $assignedWOs->count(), 'work_orders' => ...],
            'start' => ['count' => $startWOs->count(), 'work_orders' => ...],
            'hold' => ['count' => $holdWOs->count(), 'work_orders' => ...],
            'completed' => ['count' => $completedWOs->count(), 'work_orders' => ...],
            'closed' => ['count' => $closedWOs->count(), 'work_orders' => ...],
        ],
        'total_work_orders' => ...,
        'updated_at' => now()->toDateTimeString(),
    ];
}
```

---

### Use Cases

#### Use Case 1: Morning Planning Review

**Scenario:** Production supervisor starts shift at 6:00 AM

**Steps:**
1. Opens Work Order Status dashboard
2. Reviews "Planned for Today" section
3. Sees 12 WOs assigned for today

**Benefits:**
- Knows what work is scheduled
- Can prepare materials/operators
- Identifies any resource conflicts

---

#### Use Case 2: Active Work Monitoring

**Scenario:** Floor manager monitoring production at 2:00 PM

**Steps:**
1. Checks "Real-Time Execution" section
2. Sees:
   - 3 WOs on Hold (requires attention)
   - 8 WOs running (Start)
   - 4 WOs completed today

**Actions:**
- Investigates 3 Hold WOs
- Resolves blocking issues
- Monitors running work progress

---

#### Use Case 3: End-of-Day Review

**Scenario:** Manager reviews daily performance at 10:00 PM

**Steps:**
1. Checks Completed + Closed sections
2. Sees 18 WOs completed today
3. Sees 5 WOs closed today

**Insights:**
- Daily productivity: 18 completions
- Good closure rate: 5 closed
- Compare to target (e.g., 20 completions)

---

### Comparison to Machine Status KPI

| Aspect | Machine Status | Work Order Status |
|--------|---------------|-------------------|
| **Focus** | Physical machines | Work being performed |
| **Primary Question** | "Is machine running/idle?" | "What work is scheduled/executing?" |
| **Granularity** | Per machine | Per work order |
| **Dashboard Filter** | WOs scheduled for today (start_time) | Split: Planned (start_time) + Real-Time (status) |
| **Key Statuses** | Running, Hold, Scheduled, Idle | Assigned, Start, Hold, Completed, Closed |
| **Use Case** | Asset utilization | Work progress tracking |

**Complementary Relationship:**
- Machine Status: "Machine-003 is idle" → Machine perspective
- Work Order Status: "No WOs assigned to Machine-003 today" → Work perspective
- Together they provide complete operational visibility

---

### Troubleshooting

#### Issue: Completed WO Not Showing on Dashboard

**Symptom:** Work order marked 'Completed' but not appearing in dashboard

**Diagnosis:**
```php
// Check if status change happened today
WorkOrderLog::where('work_order_id', $woId)
    ->where('status', 'Completed')
    ->whereDate('changed_at', today())
    ->exists();
```

**Possible Causes:**
1. WO was completed yesterday, not today
2. `work_order_logs` entry missing (data integrity issue)
3. Status change timestamp outside today's range

**Solution:**
- If completed today: Check `work_order_logs` table for entry
- If completed yesterday: Expected behavior (won't show today)
- If logs missing: Create manual log entry or re-complete WO

---

#### Issue: Running WO Started Last Week Not Showing

**Symptom:** WO started 3 days ago, status = 'Start', but not in dashboard

**Diagnosis:**
```php
WorkOrder::where('id', $woId)
    ->where('status', 'Start')
    ->exists(); // Should return true
```

**Possible Causes:**
1. WO status changed from 'Start' to something else
2. Factory ID mismatch
3. Cache issue (if using cache)

**Solution:**
```bash
# Check work order status
php artisan tinker
> $wo = WorkOrder::find($woId);
> $wo->status; // Should be 'Start'
> $wo->factory_id; // Should match current factory
```

---

### Performance Considerations

**Dashboard Mode:**
- 5 separate queries (one per status)
- Each query filtered appropriately
- Eager loads: machine, operator, bom chain
- Cache duration: 5 minutes (300 seconds)
- Manual refresh bypasses cache

**Optimization Tips:**
1. Index on (`factory_id`, `status`, `start_time`)
2. Index on `work_order_logs` (`status`, `changed_at`)
3. Limit eager loading to necessary fields only
4. Consider Redis for high-traffic dashboards

---

### Future Enhancements

#### Phase 1: Work Order Performance Metrics
- Average completion time per WO
- On-time completion rate
- WO efficiency scores

#### Phase 2: Hold Reason Analytics
- Most common hold reasons
- Average hold duration by reason
- Trend analysis of hold causes

#### Phase 3: WO Scheduling Intelligence
- Predict WO completion time
- Suggest optimal scheduling
- Identify potential bottlenecks

#### Phase 4: Operator Performance
- WOs completed per operator
- Average cycle time per operator
- Quality rate by operator

---

**Last Updated:** October 16, 2025
**Version:** 1.1 (Added Work Order Status documentation)
**Author:** ProdStream Development Team
