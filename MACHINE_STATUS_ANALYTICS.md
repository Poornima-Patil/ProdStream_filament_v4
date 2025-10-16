# Machine Status - Analytics Mode Documentation

## Table of Contents
1. [Overview](#overview)
2. [Analytics Display Components](#analytics-display-components)
3. [Metrics Definitions](#metrics-definitions)
4. [Data Sources](#data-sources)
5. [Calculation Details](#calculation-details)
6. [Data Population](#data-population)
7. [Metric Implementation Guide](#metric-implementation-guide)
8. [Time Periods](#time-periods)
9. [Comparison Mode](#comparison-mode)
10. [Use Cases & Examples](#use-cases--examples)
11. [Future Enhancements](#future-enhancements)

---

## Overview

### What is Machine Status Analytics Mode?

Machine Status Analytics Mode provides **historical performance analysis** of machines based on aggregated daily data. It allows users to analyze trends, compare periods, and gain insights into machine utilization, production efficiency, and quality metrics over time.

### Dashboard Mode vs Analytics Mode

| Feature | Dashboard Mode | Analytics Mode |
|---------|---------------|----------------|
| **Data Source** | Real-time from `work_orders` table | Pre-aggregated from `kpi_machine_daily` table |
| **Refresh Rate** | Manual refresh button (on-demand) | Static historical data |
| **Time Scope** | Current day only | Any historical date range |
| **Purpose** | Monitor what's happening NOW | Analyze what happened BEFORE |
| **Display** | Live machine status tables | Summary cards + daily breakdown |
| **Use Case** | "What machines are running today?" | "How did we perform last month?" |

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

Displays 6 key metrics aggregated over the selected time period:

```
┌─────────────────┬─────────────────┬─────────────────┐
│ Avg Utilization │  Total Uptime   │ Total Downtime  │
│     75.5%       │    180.5 h      │     58.5 h      │
└─────────────────┴─────────────────┴─────────────────┘
┌─────────────────┬─────────────────┬─────────────────┐
│ Units Produced  │  Work Orders    │    Machines     │
│     12,450      │       48        │        6        │
└─────────────────┴─────────────────┴─────────────────┘
```

### 2. Daily Breakdown Table (Main Content)

Shows day-by-day details for the selected period:

| Date | Utilization % | Uptime (h) | Downtime (h) | Units | Work Orders |
|------|--------------|------------|--------------|-------|-------------|
| Oct 14, 2025 | 78.2% | 18.8 | 5.2 | 2,340 | 8 |
| Oct 13, 2025 | 72.5% | 17.4 | 6.6 | 2,180 | 7 |
| Oct 12, 2025 | 80.1% | 19.2 | 4.8 | 2,520 | 9 |

### 3. Comparison Mode (Optional)

When enabled, shows:
- Current period metrics vs comparison period metrics
- Percentage change for each metric
- Trend indicators (↑ improved, ↓ declined)
- Color coding (green = better, red = worse)

---

## Metrics Definitions

### 1. Utilization Rate

The system tracks **TWO types of utilization** to provide different perspectives on machine performance:

---

#### 1.1 Scheduled Utilization (Factory View)

**What it measures:** Percentage of available time that machines had work orders scheduled, including hold periods

**Accountability:** Factory/Management perspective - measures total time machines were assigned work

**Formula:**
```
Scheduled Utilization = (Scheduled Work Time / Available Time) × 100

Where:
- Scheduled Work Time = Sum of (work_order.end_time - work_order.start_time)
                        for work orders with status IN ('Start', 'Completed')
- Available Time = Total shift hours for the period
```

**Data Source:**
- Uses `work_orders.start_time` and `work_orders.end_time` columns
- Counts entire duration from start to end, including any hold periods within that timeframe

**Example:**
```
Machine-001 on Oct 14, 2025
- Factory has 3 shifts: 6:00-14:00, 14:00-22:00, 22:00-6:00
- Available Time: 28 hours (factory has 4 shifts configured)

Work Orders:
- WO1: 06:00-14:00 (8 hours, Status: Completed)
  Includes: 1.5 hours on hold for material
- WO2: 14:00-22:00 (8 hours, Status: Start)
  Includes: 2 hours on hold for quality check

Scheduled Work Time: 8 + 8 = 16 hours (entire duration, including holds)
Scheduled Utilization: (16 / 28) × 100 = 57.14%
```

**Database Field:** `kpi_machine_daily.utilization_rate`

**Purpose:**
- Helps factory managers understand overall machine assignment
- Shows if machines have enough work scheduled
- Includes all accountability periods (active + holds)

---

#### 1.2 Active Utilization (Operator View)

**What it measures:** Percentage of available time that machines were **actively running**, excluding hold periods

**Accountability:** Operator/Production perspective - measures actual productive work time

**Formula:**
```
Active Utilization = (Active Work Time / Available Time) × 100

Where:
- Active Work Time = Sum of durations when work order status was 'Start'
                     (tracked in work_order_logs)
- Available Time = Total shift hours for the period
- Excludes: All hold periods, setup time, waiting time
```

**Data Source:**
- Uses `work_order_logs` table to track status changes
- Only counts time between status = 'Start' and next status change
- Precisely calculates when machine was actively producing

**Calculation Logic:**
```
For each work order:
1. Get all status changes from work_order_logs ordered by changed_at
2. Iterate through logs:
   - If previous status was 'Start' and current status is 'Hold'/'Completed':
     → Count the time difference as active time
   - If status is 'Hold': skip this period (not counted)
3. Sum all active time periods
4. Calculate percentage vs available hours
```

**Example:**
```
Machine-001 on Oct 14, 2025
- Available Time: 28 hours

Work Order WO1 Timeline (from work_order_logs):
06:00 - Status: Start
09:30 - Status: Hold (Material delay)    → Active: 3.5 hours
11:00 - Status: Start (Resumed)
14:00 - Status: Completed                → Active: 3.0 hours

Work Order WO2 Timeline:
14:00 - Status: Start
18:00 - Status: Hold (Quality check)     → Active: 4.0 hours
20:00 - Status: Start (Resumed)
22:00 - Status: Completed                → Active: 2.0 hours

Total Active Time: 3.5 + 3.0 + 4.0 + 2.0 = 12.5 hours
Active Utilization: (12.5 / 28) × 100 = 44.64%
```

**Database Field:** `kpi_machine_daily.active_utilization_rate`

**Purpose:**
- Shows actual productive time (operator accountability)
- Helps identify hold/delay issues
- More accurate for operator performance evaluation
- Always equal to or lower than Scheduled Utilization

---

#### 1.3 Key Differences Between Both Metrics

| Aspect | Scheduled Utilization | Active Utilization |
|--------|----------------------|-------------------|
| **Perspective** | Factory/Management | Operator/Production |
| **Includes Holds?** | ✅ Yes | ❌ No |
| **Data Source** | `work_orders` table | `work_order_logs` table |
| **Calculation** | end_time - start_time | Sum of 'Start' status durations |
| **Value** | Higher | Lower (excludes holds) |
| **Use Case** | Machine assignment planning | Operator performance |
| **Formula** | (Scheduled Time / Available) × 100 | (Active Time / Available) × 100 |

**Relationship:**
```
Active Utilization ≤ Scheduled Utilization

The difference indicates time spent in hold/delay states:
Hold Time ≈ Scheduled Utilization - Active Utilization
```

**Real Example:**
```
Machine-003 on Oct 14, 2025:
- Scheduled Utilization: 65.0% (18.2 hours scheduled)
- Active Utilization: 52.3% (14.6 hours actively running)
- Difference: 12.7% (3.6 hours in hold)

Interpretation: Machine had work assigned for 65% of the day,
but only ran actively for 52%, with 13% spent waiting/on hold.
```

---

#### 1.4 Interpretation Guidelines

**Scheduled Utilization:**
- **> 80%**: Excellent machine assignment
- **60-80%**: Good utilization
- **40-60%**: Moderate (check work order planning)
- **< 40%**: Poor (urgent attention needed - insufficient work)

**Active Utilization:**
- **> 70%**: Excellent productive time
- **50-70%**: Good productive time
- **30-50%**: Moderate (investigate hold causes)
- **< 30%**: Poor (excessive holds/delays)

**Gap Analysis:**
```
If Scheduled is 75% but Active is 45%:
→ Large gap (30%) indicates excessive hold time
→ Action: Investigate hold reasons (material, quality, maintenance)

---

### 2. Uptime Hours

**What it measures:** Total hours machines were productive (running or completed work)

**Formula:**
```
Uptime Hours = Sum of (end_time - start_time) for all work orders where status IN ('Start', 'Completed')
```

**What counts as uptime:**
- Work orders with status 'Start' (actively running)
- Work orders with status 'Completed' (finished production)

**What does NOT count:**
- Work orders with status 'Hold' (counted as downtime)
- Work orders with status 'Assigned' (not yet started)
- Gaps between work orders (idle time)

**Example:**
```
Machine-002 on Oct 14, 2025

Work Orders:
1. WO101: 06:00-14:00, Status: Completed → 8 hours uptime
2. WO102: 14:30-22:00, Status: Start → 7.5 hours uptime
3. WO103: 22:00-06:00 (next day), Status: Completed → 8 hours uptime (next day)

Total Uptime for Oct 14: 8 + 7.5 = 15.5 hours
```

---

### 3. Downtime Hours

**What it measures:** Total hours machines were not producing

**Formula:**
```
Downtime Hours = Available Hours - Uptime Hours

Or more precisely:
Downtime Hours = Hold Time + Idle Time

Where:
- Hold Time = Sum of time work orders spent in 'Hold' status
- Idle Time = Gaps between work orders within shift hours
```

**Types of Downtime:**
- **Planned Downtime**: Scheduled maintenance, breaks (not tracked yet - shows 0)
- **Unplanned Downtime**: Equipment failures, material shortages, quality issues

**Current Implementation:**
- All downtime is considered "unplanned"
- `planned_downtime_hours` field = 0 (future enhancement)
- `unplanned_downtime_hours` field = total downtime

**Example:**
```
Machine-003 on Oct 14, 2025
- Available: 24 hours
- Uptime: 18 hours
- Downtime: 24 - 18 = 6 hours

Breakdown:
- 2 hours in 'Hold' status (machine issue)
- 4 hours idle (no work scheduled)
```

---

### 4. Units Produced

**What it measures:** Total number of good units manufactured

**Formula:**
```
Units Produced = Sum of ok_qtys from all work orders on that date
```

**Calculation:**
```sql
SELECT SUM(ok_qtys)
FROM work_orders
WHERE DATE(start_time) = '2025-10-14'
  AND factory_id = 1
  AND status IN ('Start', 'Completed', 'Hold')
```

**Note:** Only counts OK (good) quantities, excludes scrapped quantities

**Example:**
```
Machine-004 on Oct 14, 2025

Work Orders:
- WO201: ok_qtys = 429, scrapped_qtys = 5
- WO202: ok_qtys = 380, scrapped_qtys = 8
- WO203: ok_qtys = 150, scrapped_qtys = 2

Units Produced = 429 + 380 + 150 = 959 units
(Scrapped quantities: 15 units - not counted here)
```

---

### 5. Work Orders Completed

**What it measures:** Number of work orders fully finished

**Formula:**
```
Work Orders Completed = COUNT of work orders where status = 'Completed'
```

**Calculation:**
```sql
SELECT COUNT(*)
FROM work_orders
WHERE DATE(start_time) = '2025-10-14'
  AND factory_id = 1
  AND status = 'Completed'
```

**What counts:**
- Only work orders with status = 'Completed'

**What does NOT count:**
- 'Start' status (still running)
- 'Hold' status (paused)
- 'Assigned' status (not started yet)

---

### 6. Quality Rate

**What it measures:** Percentage of good units vs total units produced

**Formula:**
```
Quality Rate = (OK Units / Total Units) × 100

Where:
- OK Units = Sum of ok_qtys
- Total Units = Sum of (ok_qtys + scrapped_qtys)
```

**Example:**
```
Machine-005 on Oct 14, 2025

Work Orders:
- WO301: ok_qtys = 500, scrapped_qtys = 10
- WO302: ok_qtys = 300, scrapped_qtys = 5

OK Units: 500 + 300 = 800
Total Units: 510 + 305 = 815
Quality Rate: (800 / 815) × 100 = 98.16%
```

**Interpretation:**
- **> 98%**: Excellent quality
- **95-98%**: Good quality
- **90-95%**: Acceptable (monitor closely)
- **< 90%**: Poor quality (investigate root causes)

---

### 7. Scrap Rate

**What it measures:** Percentage of defective units

**Formula:**
```
Scrap Rate = (Scrapped Units / Total Units) × 100

Or:
Scrap Rate = 100 - Quality Rate
```

**Example:**
```
Using same data as Quality Rate example:
Scrap Rate = 100 - 98.16 = 1.84%
```

---

### 8. First Pass Yield (FPY)

**What it measures:** Percentage of units produced correctly the first time

**Formula:**
```
FPY = (Units Passed First Time / Total Units Started) × 100
```

**Data Source:** Calculated from `work_order_logs` table

**Note:** Currently stored in logs but not displayed in Analytics Mode yet (future enhancement)

---

### 9. Average Cycle Time

**What it measures:** Average time to produce one unit

**Formula:**
```
Average Cycle Time = Total Production Time / Total Units Produced

Where:
- Total Production Time = Sum of (end_time - start_time) for all work orders
- Total Units Produced = Sum of ok_qtys
```

**Example:**
```
Machine-006 on Oct 14, 2025

Work Orders:
- WO401: 8 hours, produced 400 units
- WO402: 6 hours, produced 300 units

Total Time: 14 hours = 840 minutes
Total Units: 700 units
Average Cycle Time: 840 / 700 = 1.2 minutes per unit
```

---

### 10. Machine Count

**What it measures:** Number of unique machines that had work orders during the period

**Formula:**
```
Machine Count = COUNT(DISTINCT machine_id) from work orders in the period
```

---

## Data Sources

### Primary Tables

#### 1. `kpi_machine_daily` (Main source for Analytics)
```
Fields:
- factory_id, machine_id, summary_date
- utilization_rate, uptime_hours, downtime_hours
- units_produced, work_orders_completed
- quality_rate, scrap_rate, first_pass_yield
- average_cycle_time
- calculated_at (when aggregation was performed)
```

#### 2. `work_orders` (Raw data source)
```
Fields:
- id, factory_id, machine_id, operator_id
- start_time, end_time, status
- qty, ok_qtys, scrapped_qtys
- created_at, updated_at
```

### Supporting Tables

#### 3. `shifts` (For calculating available time)
```
Fields:
- factory_id, name
- start_time, end_time
```

#### 4. `work_order_logs` (For status change tracking)
```
Fields:
- work_order_id, status, changed_at
- ok_qtys, scrapped_qtys, fpy
```

### Data Flow Diagram

```
┌─────────────────┐
│  work_orders    │
│  (raw data)     │
└────────┬────────┘
         │
         │ Daily Aggregation
         │ (artisan command)
         ▼
┌─────────────────┐
│ kpi_machine_    │
│    daily        │◄─── Analytics Mode queries this
│ (aggregated)    │
└─────────────────┘
         │
         │ Displayed in
         ▼
┌─────────────────┐
│  Analytics UI   │
│  (Blade View)   │
└─────────────────┘
```

---

## Calculation Details

### Handling Work Orders That Span Multiple Days

**Scenario:** Work order starts at 22:00 on Oct 14 and ends at 06:00 on Oct 15

**Solution Options:**

**Option A: Simple (Current Implementation)**
- Assign entire work order to start_date (Oct 14)
- Easier to calculate, but may skew single-day metrics

**Option B: Proportional Split (Future Enhancement)**
- Split duration between Oct 14 (8 hours) and Oct 15 (8 hours)
- More accurate but complex calculation

**Current Approach:** Option A (assign to start_date)

### Calculating Available Time

**Formula:**
```
Available Time per Machine = Number of Shifts × Hours per Shift

Factory Example:
- Shift 1: 06:00-14:00 (8 hours)
- Shift 2: 14:00-22:00 (8 hours)
- Shift 3: 22:00-06:00 (8 hours)
- Total: 3 shifts × 8 hours = 24 hours per machine per day
```

**For Multiple Machines:**
```
If factory has 5 machines:
Total Available Time per Day = 5 machines × 24 hours = 120 hours
```

### Handling Overlapping Work Orders

**Scenario:** Machine has 2 work orders scheduled at same time (should not happen, but edge case)

**Solution:**
- Validation in work order creation prevents overlaps
- If occurs, count actual duration only once
- Log warning for investigation

### Rounding Rules

- **Percentages**: Round to 2 decimal places (75.23%)
- **Hours**: Round to 1 decimal place (18.5 hours)
- **Counts**: No rounding, whole numbers only
- **Cycle Time**: Round to 2 decimal places (1.25 minutes)

---

## Data Population

### Overview

Analytics data is populated through a **daily aggregation process** that:
1. Queries raw work order data
2. Calculates all metrics per machine per day
3. Inserts/updates the `kpi_machine_daily` table

### Aggregation Command

**Command Name:** `php artisan kpi:aggregate-daily`

**Purpose:** Process work orders and populate KPI metrics for Analytics mode

### Command Usage

#### Basic Usage (Single Date)
```bash
# Aggregate data for Oct 14, 2025 for factory 1
php artisan kpi:aggregate-daily 2025-10-14 --factory=1
```

#### Date Range
```bash
# Aggregate data from Sept 30 to Oct 14 for factory 1
php artisan kpi:aggregate-daily 2025-09-30 --to=2025-10-14 --factory=1
```

#### All Factories
```bash
# Aggregate data for Oct 14 for all factories
php artisan kpi:aggregate-daily 2025-10-14
```

#### Force Re-aggregation
```bash
# Re-calculate metrics even if data already exists
php artisan kpi:aggregate-daily 2025-10-14 --factory=1 --force
```

#### Default (Yesterday)
```bash
# Aggregate yesterday's data for all factories
php artisan kpi:aggregate-daily
```

### Aggregation Process

**Step-by-Step:**

1. **Query work orders for the date**
   ```sql
   SELECT * FROM work_orders
   WHERE DATE(start_time) = ?
     AND factory_id = ?
   ```

2. **Group by machine**
   - Collect all work orders per machine

3. **Calculate metrics**
   - Utilization Rate: (runtime / 24 hours) × 100
   - Uptime Hours: Sum of work order durations where status IN ('Start', 'Completed')
   - Downtime Hours: 24 - Uptime Hours
   - Units Produced: Sum of ok_qtys
   - Work Orders Completed: Count where status = 'Completed'
   - Quality Rate: (ok_qtys / (ok_qtys + scrapped_qtys)) × 100
   - Scrap Rate: 100 - Quality Rate
   - Average Cycle Time: Total time / Total units

4. **Upsert into kpi_machine_daily**
   ```sql
   INSERT INTO kpi_machine_daily (...)
   ON DUPLICATE KEY UPDATE ...
   ```

### Scheduling Strategy

#### Manual (Current)
```bash
# Run after work orders are simulated/completed
php artisan kpi:aggregate-daily 2025-10-14 --factory=1
```

#### Automated (Future - Add to Scheduler)

**In `bootstrap/app.php` or `routes/console.php`:**
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('kpi:aggregate-daily')
    ->dailyAt('00:30')  // Run at 12:30 AM
    ->timezone('America/New_York');
```

**Why 12:30 AM?**
- All work orders for previous day are finalized
- Low system activity
- Data ready for morning reports

### Backfilling Historical Data

**Scenario:** You have 3 weeks of work orders but no aggregated KPI data

**Solution:**
```bash
# Aggregate all historical dates at once
php artisan kpi:aggregate-daily 2025-09-30 --to=2025-10-21 --factory=1
```

**Output:**
```
Processing date range: 2025-09-30 to 2025-10-21
Factory: 1

 19/19 [============================] 100%

Successfully processed:
- Total days: 19
- Total machines: 7
- Total records created/updated: 133
- Errors: 0

Completed in 4.2 seconds
```

### Error Handling

**Common Issues:**

1. **No work orders found for date**
   - Command skips date with info message
   - No error thrown

2. **Invalid date format**
   - Error: "Invalid date format. Use YYYY-MM-DD"
   - Command exits

3. **Factory not found**
   - Error: "Factory with ID X not found"
   - Command exits

4. **Database constraint violation**
   - Logs error with details
   - Continues processing other machines
   - Shows summary at end

---

## Metric Implementation Guide

This section provides detailed technical implementation steps for calculating each metric in the `php artisan kpi:aggregate-daily` command.

### Implementation Overview

Each metric follows this pattern:
1. **Query** - Fetch relevant work orders for the date/machine
2. **Calculate** - Apply formula to compute the metric
3. **Store** - Insert/update value in `kpi_machine_daily` table

---

### Metric 1: Utilization Rate (Both Types)

The system calculates **two separate utilization metrics** in the aggregation command.

---

#### Metric 1.1: Scheduled Utilization (Factory View)

**What It Calculates:** Percentage of available time with work orders scheduled (including hold periods)

**Implementation Steps:**

**Step 1: Query Work Orders**
```php
$workOrders = WorkOrder::where('factory_id', $factoryId)
    ->where('machine_id', $machineId)
    ->whereDate('start_time', $date)
    ->whereIn('status', ['Start', 'Completed'])
    ->get(['start_time', 'end_time']);
```

**Step 2: Calculate Total Scheduled Time**
```php
$totalProductionSeconds = 0;

foreach ($workOrders as $wo) {
    $start = Carbon::parse($wo->start_time);
    $end = Carbon::parse($wo->end_time);

    // If work order spans multiple days, only count time on the target date
    $dayStart = Carbon::parse($date)->startOfDay();
    $dayEnd = Carbon::parse($date)->endOfDay();

    // Clip to day boundaries
    $effectiveStart = $start->lt($dayStart) ? $dayStart->copy() : $start->copy();
    $effectiveEnd = $end->gt($dayEnd) ? $dayEnd->copy() : $end->copy();

    if ($effectiveEnd->gt($effectiveStart)) {
        $totalProductionSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
    }
}

$totalProductionHours = $totalProductionSeconds / 3600;
```

**Important:** Use `$start->lt($dayStart) ? $dayStart->copy() : $start->copy()` instead of `$start->max($dayStart)` to avoid Carbon mutation issues.

**Step 3: Calculate Available Time**
```php
// Get total shift hours per day for this factory
$shifts = Shift::where('factory_id', $factoryId)->get();

$availableHours = 0;
foreach ($shifts as $shift) {
    $start = Carbon::createFromTimeString($shift->start_time);
    $end = Carbon::createFromTimeString($shift->end_time);

    // Handle overnight shifts
    if ($end->lt($start)) {
        $end->addDay();
    }

    $availableHours += $end->diffInHours($start);
}

// For most factories with 3 shifts of 8 hours each:
// $availableHours = 24
```

**Step 4: Calculate Utilization Rate**
```php
$utilizationRate = $availableHours > 0
    ? round(($totalProductionHours / $availableHours) * 100, 2)
    : 0.00;
```

**Step 5: Store in Database**
```php
DB::table('kpi_machine_daily')->upsert([
    'factory_id' => $factoryId,
    'machine_id' => $machineId,
    'summary_date' => $date,
    'utilization_rate' => $utilizationRate,
    'calculated_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
], ['factory_id', 'machine_id', 'summary_date'], // Unique keys
['utilization_rate', 'calculated_at', 'updated_at']); // Update fields
```

#### Edge Cases to Handle

**Case 1: No Work Orders**
```php
// If $workOrders->isEmpty()
$utilizationRate = 0.00;
```

**Case 2: Work Order Spans Multiple Days**
```php
// WO starts at 22:00 on Oct 14 and ends at 06:00 on Oct 15
// Only count 2 hours (22:00-24:00) for Oct 14
// Count 6 hours (00:00-06:00) for Oct 15 (when processing Oct 15)

$effectiveStart = max($wo->start_time, $date->startOfDay());
$effectiveEnd = min($wo->end_time, $date->endOfDay());
```

**Case 3: Overlapping Work Orders (Should Not Happen)**
```php
// If detected, log warning but count all hours
// Production validation should prevent this
Log::warning("Overlapping work orders detected", [
    'machine_id' => $machineId,
    'date' => $date,
    'work_orders' => $workOrders->pluck('id')
]);
```

**Case 4: Utilization > 100%**
```php
// Should never happen, but cap at 100% if it does
$utilizationRate = min($utilizationRate, 100.00);
```

#### Complete Method Example

```php
private function calculateUtilizationRate(
    int $factoryId,
    int $machineId,
    string $date
): float {
    // Step 1: Get work orders
    $workOrders = WorkOrder::where('factory_id', $factoryId)
        ->where('machine_id', $machineId)
        ->whereDate('start_time', $date)
        ->whereIn('status', ['Start', 'Completed'])
        ->get(['start_time', 'end_time']);

    if ($workOrders->isEmpty()) {
        return 0.00;
    }

    // Step 2: Calculate production time
    $dayStart = Carbon::parse($date)->startOfDay();
    $dayEnd = Carbon::parse($date)->endOfDay();
    $totalSeconds = 0;

    foreach ($workOrders as $wo) {
        $start = Carbon::parse($wo->start_time)->max($dayStart);
        $end = Carbon::parse($wo->end_time)->min($dayEnd);

        if ($end->gt($start)) {
            $totalSeconds += $end->diffInSeconds($start);
        }
    }

    $productionHours = $totalSeconds / 3600;

    // Step 3: Get available hours
    $availableHours = $this->getAvailableHours($factoryId);

    // Step 4: Calculate rate
    $rate = $availableHours > 0
        ? ($productionHours / $availableHours) * 100
        : 0;

    // Cap at 100%
    return round(min($rate, 100), 2);
}

private function getAvailableHours(int $factoryId): float
{
    static $cache = [];

    if (!isset($cache[$factoryId])) {
        $shifts = Shift::where('factory_id', $factoryId)->get();
        $hours = 0;

        foreach ($shifts as $shift) {
            $start = Carbon::createFromTimeString($shift->start_time);
            $end = Carbon::createFromTimeString($shift->end_time);

            if ($end->lt($start)) {
                $end->addDay();
            }

            $hours += $end->diffInHours($start);
        }

        $cache[$factoryId] = $hours;
    }

    return $cache[$factoryId];
}
```

#### Testing the Implementation

**Test Case 1: Full Day Utilization**
```php
// Given:
// - Machine-001 on Oct 14
// - 3 shifts × 8 hours = 24 hours available
// - WO1: 06:00-14:00 (8h, Completed)
// - WO2: 14:00-22:00 (8h, Start)
// - WO3: 22:00-06:00 (8h, Completed, next day)

// Expected for Oct 14:
// Production Time: 8 + 8 = 16 hours
// Utilization: (16 / 24) × 100 = 66.67%

$this->assertEquals(66.67, $utilizationRate);
```

**Test Case 2: No Production**
```php
// Given:
// - Machine-002 on Oct 14
// - No work orders scheduled

// Expected:
// Utilization: 0.00%

$this->assertEquals(0.00, $utilizationRate);
```

**Test Case 3: Spanning Midnight**
```php
// Given:
// - WO starts at 22:00 on Oct 14
// - WO ends at 06:00 on Oct 15

// Expected for Oct 14:
// Count only 22:00-23:59:59 = 2 hours
// Utilization: (2 / 24) × 100 = 8.33%

$this->assertEquals(8.33, $utilizationRate);
```

#### Database Schema Reference

```sql
CREATE TABLE kpi_machine_daily (
    factory_id INT NOT NULL,
    machine_id INT NOT NULL,
    summary_date DATE NOT NULL,
    utilization_rate DECIMAL(5,2) NULL,  -- Example: 66.67
    -- ... other fields
    UNIQUE KEY unique_daily_kpi (factory_id, machine_id, summary_date)
);
```

#### Performance Optimization

**Use Eager Loading**
```php
$workOrders = WorkOrder::where('factory_id', $factoryId)
    ->whereDate('start_time', $date)
    ->with('machine:id,name')  // Only if needed for logging
    ->get(['id', 'machine_id', 'start_time', 'end_time', 'status']);
```

**Batch Processing**
```php
// Process all machines for a date in one query
$workOrders = WorkOrder::where('factory_id', $factoryId)
    ->whereDate('start_time', $date)
    ->whereIn('status', ['Start', 'Completed'])
    ->get()
    ->groupBy('machine_id');

foreach ($workOrders as $machineId => $machineWorkOrders) {
    $utilization = $this->calculateUtilizationForWorkOrders($machineWorkOrders);
    // Store...
}
```

**Cache Available Hours**
```php
// Don't query shifts table for every machine
// Cache at factory level since all machines share same shifts
private static $availableHoursCache = [];
```

---

#### Metric 1.2: Active Utilization (Operator View)

**What It Calculates:** Percentage of available time machine was actively running (excludes hold periods)

**Implementation Steps:**

**Step 1: Query Work Orders and Logs**
```php
$workOrders = WorkOrder::where('factory_id', $factoryId)
    ->where('machine_id', $machineId)
    ->whereDate('start_time', $date)
    ->whereIn('status', ['Start', 'Completed', 'Hold'])
    ->get(['id', 'start_time', 'end_time']);
```

**Step 2: Calculate Active Time from Work Order Logs**
```php
$totalActiveSeconds = 0;
$dayStart = Carbon::parse($date)->startOfDay();
$dayEnd = Carbon::parse($date)->endOfDay();

foreach ($workOrders as $wo) {
    // Get all status change logs for this work order
    $logs = WorkOrderLog::where('work_order_id', $wo->id)
        ->orderBy('changed_at', 'asc')
        ->get(['status', 'changed_at']);

    if ($logs->isEmpty()) {
        continue; // Skip if no logs
    }

    $previousLog = null;

    foreach ($logs as $log) {
        if ($previousLog && $previousLog->status === 'Start') {
            // Machine was actively running from previousLog to current log
            $startTime = Carbon::parse($previousLog->changed_at);
            $endTime = Carbon::parse($log->changed_at);

            // Clip to day boundaries
            $effectiveStart = $startTime->lt($dayStart) ? $dayStart->copy() : $startTime->copy();
            $effectiveEnd = $endTime->gt($dayEnd) ? $dayEnd->copy() : $endTime->copy();

            if ($effectiveEnd->gt($effectiveStart)) {
                $totalActiveSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
            }
        }

        $previousLog = $log;
    }

    // Handle ongoing work orders still in 'Start' status
    if ($previousLog && $previousLog->status === 'Start') {
        $startTime = Carbon::parse($previousLog->changed_at);
        $endTime = now();

        // Clip to day boundaries
        $effectiveStart = $startTime->lt($dayStart) ? $dayStart->copy() : $startTime->copy();
        $effectiveEnd = $endTime->gt($dayEnd) ? $dayEnd->copy() : $endTime->copy();

        if ($effectiveEnd->gt($effectiveStart)) {
            $totalActiveSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
        }
    }
}

$activeHours = $totalActiveSeconds / 3600;
```

**Step 3: Calculate Active Utilization Rate**
```php
$availableHours = $this->getAvailableHours($factoryId);

$activeUtilizationRate = $availableHours > 0
    ? round(min(($activeHours / $availableHours) * 100, 100), 2)
    : 0.00;
```

**Step 4: Store in Database**
```php
DB::table('kpi_machine_daily')->upsert([
    'factory_id' => $factoryId,
    'machine_id' => $machineId,
    'summary_date' => $date,
    'active_utilization_rate' => $activeUtilizationRate,
    'calculated_at' => now(),
], ['factory_id', 'machine_id', 'summary_date'],
['active_utilization_rate', 'calculated_at', 'updated_at']);
```

#### Key Implementation Notes

**Carbon Mutation Bug Prevention:**
- Always use conditional logic instead of `max()` and `min()` methods
- Carbon's `max()` and `min()` modify the original object even after `copy()`
- Use: `$start->lt($dayStart) ? $dayStart->copy() : $start->copy()`
- NOT: `$start->copy()->max($dayStart)` (this still mutates!)

**Example Timeline Calculation:**
```
Work Order Timeline (from work_order_logs):
10:00 - Status: Start
12:00 - Status: Hold      → Active: 2 hours (10:00-12:00)
14:00 - Status: Start
16:00 - Status: Completed → Active: 2 hours (14:00-16:00)

Total Active Time: 4 hours
Scheduled Time: 6 hours (10:00-16:00)
Hold Time: 2 hours (12:00-14:00)
```

**Database Schema:**
```sql
CREATE TABLE kpi_machine_daily (
    ...
    utilization_rate DECIMAL(5,2) NULL,         -- Scheduled Utilization
    active_utilization_rate DECIMAL(5,2) NULL,  -- Active Utilization (NEW)
    ...
);
```

**Complete Method Example:**
```php
private function calculateActiveUtilization(
    $workOrders,
    Carbon $dayStart,
    Carbon $dayEnd,
    float $availableHours
): array {
    $totalActiveSeconds = 0;

    foreach ($workOrders as $wo) {
        $logs = WorkOrderLog::where('work_order_id', $wo->id)
            ->orderBy('changed_at', 'asc')
            ->get(['status', 'changed_at']);

        if ($logs->isEmpty()) {
            continue;
        }

        $previousLog = null;

        foreach ($logs as $log) {
            if ($previousLog && $previousLog->status === 'Start') {
                $startTime = Carbon::parse($previousLog->changed_at);
                $endTime = Carbon::parse($log->changed_at);

                $effectiveStart = $startTime->lt($dayStart)
                    ? $dayStart->copy()
                    : $startTime->copy();
                $effectiveEnd = $endTime->gt($dayEnd)
                    ? $dayEnd->copy()
                    : $endTime->copy();

                if ($effectiveEnd->gt($effectiveStart)) {
                    $totalActiveSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
                }
            }

            $previousLog = $log;
        }

        // Handle ongoing 'Start' status
        if ($previousLog && $previousLog->status === 'Start') {
            $startTime = Carbon::parse($previousLog->changed_at);
            $endTime = now();

            $effectiveStart = $startTime->lt($dayStart)
                ? $dayStart->copy()
                : $startTime->copy();
            $effectiveEnd = $endTime->gt($dayEnd)
                ? $dayEnd->copy()
                : $endTime->copy();

            if ($effectiveEnd->gt($effectiveStart)) {
                $totalActiveSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
            }
        }
    }

    $activeHours = round($totalActiveSeconds / 3600, 2);

    $activeUtilizationRate = $availableHours > 0
        ? round(min(($activeHours / $availableHours) * 100, 100), 2)
        : 0.00;

    return [
        'active_utilization_rate' => $activeUtilizationRate,
        'active_hours' => $activeHours,
    ];
}
```

**Testing Active Utilization:**
```php
// Test Case: Work Order with Hold
// WO1: 10:00-16:00 (6 hours scheduled)
// Logs:
//   10:00 - Start
//   12:00 - Hold (2 hours active)
//   14:00 - Start
//   16:00 - Completed (2 hours active)
//
// Expected:
// Active Time: 4 hours
// Scheduled Time: 6 hours
// Active Utilization: (4 / 28) × 100 = 14.29%
// Scheduled Utilization: (6 / 28) × 100 = 21.43%
// Difference: 7.14% (2 hours in hold)
```

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
- Shows finalized data from `kpi_machine_daily` table
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
│ Avg Utilization                 │
│ 75.5%  ↑ +5.2% vs Previous     │
│        (70.3% previously)       │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Total Downtime                  │
│ 58.5h  ↓ -12.3% vs Previous    │
│        (66.8h previously)       │
└─────────────────────────────────┘
```

#### Trend Indicators
- **↑ Green**: Improvement (higher is better for this metric)
- **↓ Red**: Decline (lower is worse for this metric)
- **↓ Green**: Improvement (lower is better for downtime/scrap rate)
- **↑ Red**: Decline (higher is worse for downtime/scrap rate)

#### Percentage Change Calculation
```
Change % = ((Current - Previous) / Previous) × 100

Example:
Current Utilization: 75.5%
Previous Utilization: 70.3%
Change: ((75.5 - 70.3) / 70.3) × 100 = +7.4%
```

### Interpretation Guide

#### Positive Trends (Good)
- Utilization Rate ↑
- Uptime Hours ↑
- Units Produced ↑
- Quality Rate ↑
- Downtime Hours ↓
- Scrap Rate ↓

#### Negative Trends (Bad)
- Utilization Rate ↓
- Uptime Hours ↓
- Units Produced ↓
- Quality Rate ↓
- Downtime Hours ↑
- Scrap Rate ↑

---

## Use Cases & Examples

### Use Case 1: Identifying Underutilized Machines

**Scenario:** Factory manager wants to find machines not being used efficiently

**Steps:**
1. Navigate to Machine Status Analytics
2. Select "This Month" as time period
3. Review utilization rates for each machine

**Sample Findings:**
```
Machine-001: 85% utilization ✓ (Good)
Machine-002: 78% utilization ✓ (Good)
Machine-003: 42% utilization ✗ (Investigation needed)
Machine-004: 90% utilization ✓ (Excellent)
Machine-005: 38% utilization ✗ (Investigation needed)
```

**Actions:**
- Investigate Machine-003 and Machine-005
- Check if there's insufficient work scheduled
- Consider reassigning work orders to utilize idle capacity

---

### Use Case 2: Tracking Quality Trends

**Scenario:** Quality manager wants to monitor defect rates over time

**Steps:**
1. Navigate to Machine Status Analytics
2. Select "Last 30 Days"
3. Review quality rate and scrap rate metrics
4. Enable comparison with previous 30 days

**Sample Findings:**
```
Current Period:
- Quality Rate: 96.5%
- Scrap Rate: 3.5%

Previous Period:
- Quality Rate: 98.2%
- Scrap Rate: 1.8%

Trend: ↓ Quality declining by -1.7%
```

**Actions:**
- Investigate root causes of increased scrap
- Check if new materials/operators were introduced
- Review work orders with high scrap rates
- Implement corrective actions

---

### Use Case 3: Week-over-Week Performance Comparison

**Scenario:** Production supervisor reviews weekly performance every Monday

**Steps:**
1. Navigate to Machine Status Analytics
2. Select "Last Week" (Oct 7-13)
3. Enable comparison mode: "Previous Week"
4. Review summary cards and daily breakdown

**Sample Findings:**
```
Last Week vs Previous Week:

Utilization: 78.5% ↑ +3.2% (Good improvement)
Uptime: 132h ↑ +8h (More productive time)
Units Produced: 8,450 ↑ +850 units (10% increase)
Work Orders Completed: 42 ↑ +4 (More completions)
Downtime: 35h ↓ -5h (Less idle time)
```

**Interpretation:** Strong week! Production increased, downtime reduced.

---

### Use Case 4: Monthly Reporting to Management

**Scenario:** Factory manager prepares monthly performance report

**Steps:**
1. Navigate to Machine Status Analytics
2. Select "Last Month" (September 2025)
3. Enable comparison: "Previous Month" (August 2025)
4. Export or screenshot summary cards for report

**Sample Report Data:**
```
September 2025 Performance Summary:

Key Metrics:
- Average Utilization: 74.8% (Target: 75% - Close to target)
- Total Production: 28,450 units (+5.2% vs August)
- Quality Rate: 97.3% (Target: 95% - Exceeding target)
- Work Orders Completed: 142 (Target: 140 - On track)
- Average Downtime: 6.2h/day (+0.8h vs August - Investigate)

Highlights:
✓ Production volume increased 5.2%
✓ Quality exceeds target by 2.3%
✗ Downtime increased - root cause analysis needed

Action Items:
- Investigate increase in downtime on Machine-003 and Machine-007
- Continue current quality practices
- Schedule maintenance for machines with high failure rates
```

---

### Use Case 5: Identifying Bottlenecks

**Scenario:** Operations manager notices production delays

**Steps:**
1. Select "Last 7 Days"
2. Review daily breakdown table
3. Look for days with low utilization or high downtime
4. Identify specific machines causing issues

**Sample Findings:**
```
Daily Analysis (Oct 8-14):

Oct 10: Utilization 58% (abnormally low)
- Machine-002: 12 hours downtime (material shortage)
- Machine-005: 8 hours downtime (equipment failure)

Oct 12: Utilization 68% (below average)
- Machine-007: 10 hours downtime (operator absence)

Pattern: Material shortages and equipment failures are main bottlenecks
```

**Actions:**
- Improve material inventory management
- Schedule preventive maintenance for Machine-005
- Cross-train operators to cover absences

---

## Future Enhancements

### Phase 1: Enhanced Downtime Tracking

**Planned Downtime vs Unplanned Downtime**
- Currently all downtime is marked as "unplanned"
- Add ability to schedule maintenance windows
- Differentiate between:
  - Planned: Scheduled maintenance, breaks, shift changes
  - Unplanned: Failures, material shortages, quality holds

**Impact:**
- More accurate utilization calculations
- Better maintenance planning
- Identify truly problematic downtime

---

### Phase 2: OEE (Overall Equipment Effectiveness)

**Formula:**
```
OEE = Availability × Performance × Quality

Where:
- Availability = Uptime / Planned Production Time
- Performance = Actual Output / Theoretical Output
- Quality = Good Units / Total Units
```

**Example:**
```
Machine-001 on Oct 14:
- Availability: 90% (21.6h uptime / 24h available)
- Performance: 85% (actual output vs ideal cycle time)
- Quality: 98% (OK units / total units)

OEE = 0.90 × 0.85 × 0.98 = 75.0%
```

**Display:**
- New OEE metric card in summary section
- Color coding: >85% (green), 60-85% (yellow), <60% (red)
- Trend analysis over time

---

### Phase 3: Maintenance Metrics

**MTBF (Mean Time Between Failures)**
```
MTBF = Total Operating Time / Number of Failures
```

**MTTR (Mean Time To Repair)**
```
MTTR = Total Repair Time / Number of Repairs
```

**Requirements:**
- Add `machine_failures` table to track breakdowns
- Log failure timestamps and resolution times
- Track failure types and root causes

---

### Phase 4: Shift-Level Analytics

**Granular Analysis:**
- Break down metrics by shift (Shift 1, Shift 2, Shift 3)
- Compare shift performance
- Identify if certain shifts consistently underperform

**Display:**
```
Shift Performance (Oct 14, 2025):

Shift 1 (6:00-14:00):  Utilization: 82%, Units: 3,200
Shift 2 (14:00-22:00): Utilization: 75%, Units: 2,850
Shift 3 (22:00-6:00):  Utilization: 68%, Units: 2,400

Insight: Shift 3 underperforming - investigate staffing/equipment issues
```

---

### Phase 5: Machine Performance Index

**Composite Score:**
- Combine multiple metrics into single performance score
- Weight factors: Utilization (40%), Quality (30%), Downtime (20%), Completions (10%)
- Score range: 0-100

**Example:**
```
Machine-002 Performance Score: 87/100

Breakdown:
- Utilization: 85% → 34 points (out of 40)
- Quality: 98% → 29.4 points (out of 30)
- Downtime: Low → 18 points (out of 20)
- Completions: 95% target → 9.5 points (out of 10)

Rating: Excellent (85-100)
```

---

### Phase 6: Predictive Analytics

**Use Machine Learning to:**
- Predict when machines are likely to fail
- Forecast production output for next week/month
- Recommend optimal work order scheduling
- Identify early warning signs of quality issues

**Example:**
```
Predictive Insights for Machine-003:

⚠️ Warning: High probability (78%) of downtime event in next 48 hours
   Based on: Recent vibration patterns, increased cycle time, past failure history

   Recommendation: Schedule preventive maintenance before Oct 16
```

---

### Phase 7: Alerts & Notifications

**Real-time Monitoring:**
- Email/SMS alerts when metrics fall below thresholds
- Dashboard notifications for critical issues
- Weekly summary reports

**Example Rules:**
```
Alert: Utilization below 50% for 2+ consecutive days
Alert: Scrap rate exceeds 5%
Alert: Machine downtime exceeds 8 hours/day
Alert: Work order completions 20% below target
```

---

### Phase 8: Export & Reporting

**Features:**
- Export analytics data to Excel/CSV
- Generate PDF reports with charts
- Schedule automated email reports
- API endpoints for external BI tools

---

### Phase 9: Advanced Visualizations

**Charts & Graphs:**
- Utilization trend line chart
- Downtime breakdown pie chart
- Quality rate area chart
- Work order completion bar chart
- Heatmap showing machine utilization by hour/day

---

### Phase 10: Multi-Factory Comparison

**For Organizations with Multiple Facilities:**
- Compare performance across factories
- Identify best practices from top-performing sites
- Benchmark factories against each other

**Example:**
```
Factory Performance Comparison (October 2025):

Factory A: Avg Utilization 82% ⭐ Top Performer
Factory B: Avg Utilization 75%
Factory C: Avg Utilization 71%

Insight: Factory A achieves 11% higher utilization than Factory C
Action: Study Factory A's practices and replicate across organization
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
