# Setup Time Per Machine - KPI Documentation

**IMPORTANT NOTE:** This document describes the **Setup Time Per Machine KPI**, which tracks time spent preparing machines for production runs. This is a Tier 2 operational KPI used to measure production efficiency and machine utilization optimization.

## Table of Contents
1. [Overview](#overview)
2. [Setup Time Definition](#setup-time-definition)
3. [Dashboard vs Analytics Mode](#dashboard-vs-analytics-mode)
4. [Metrics Definitions](#metrics-definitions)
5. [Data Sources](#data-sources)
6. [Calculation Details](#calculation-details)
7. [Data Population](#data-population)
8. [Time Periods](#time-periods)
9. [Comparison Mode](#comparison-mode)
10. [Use Cases & Examples](#use-cases--examples)
11. [Future Enhancements](#future-enhancements)

---

## Overview

### What is Setup Time Per Machine Analytics?

Setup Time Per Machine tracks the **time spent preparing machines for production runs**, including:
- Tooling changes
- Fixture setup
- Material loading
- Calibration and testing
- Changeover between different part numbers or production runs

### Why Track Setup Time?

**Manufacturing Impact:**
- **Efficiency**: Lower setup time = more productive manufacturing time
- **Cost**: Setup time is non-revenue-generating; minimizing it improves profitability
- **Throughput**: Frequent setups reduce daily output capacity
- **Scheduling**: Setup time affects work order scheduling and planning
- **Machine Utilization**: Setup time counts as "utilized" time but not "productive" time

### Setup Time vs Production Time

| Aspect | Setup Time | Production Time |
|--------|-----------|-----------------|
| **Activity** | Preparing machine for run | Manufacturing parts |
| **Revenue Generated** | No | Yes |
| **Part Count** | 0 | Increases qty produced |
| **Cost Impact** | Direct overhead | Revenue contributing |
| **Optimization** | Reduce setups, standardize processes | Maximize run rates |
| **Duration** | Minutes/Hours | Minutes/Hours |

---

## Setup Time Definition

### How Setup Time is Calculated

**Setup Time = Time Gap Between Status Changes**

```
Work Order Status Flow:

1. Assigned (Operator receives job) → changed_at: 2025-10-14 06:00:00
        ↓
        (Operator prepares machine: tool change, fixtures, materials, etc.)
        ↓
2. Start (Machine begins production) → changed_at: 2025-10-14 06:45:00
        ↓
Setup Time = 06:45:00 - 06:00:00 = 45 minutes
```

### Data Source: work_order_logs Table

The setup time is calculated from two log entries:

```sql
-- Log Entry 1: When status changes to 'Assigned'
SELECT work_order_id, status, changed_at
FROM work_order_logs
WHERE work_order_id = 1001
AND status = 'Assigned'
ORDER BY changed_at ASC
LIMIT 1;
-- Result: 2025-10-14 06:00:00

-- Log Entry 2: First time status changes to 'Start'
SELECT work_order_id, status, changed_at
FROM work_order_logs
WHERE work_order_id = 1001
AND status = 'Start'
ORDER BY changed_at ASC
LIMIT 1;
-- Result: 2025-10-14 06:45:00

-- Setup Time = 45 minutes
```

### Real-World Example

```
Work Order WO-2501:
- Assigned to Machine-007 at 06:00 (Operator: Maria)
- Maria checks setup instructions: 2 minutes
- Changes existing tooling: 15 minutes
- Loads new material: 8 minutes
- Runs calibration check: 12 minutes
- Waits for supervisor sign-off: 5 minutes
- Status changes to 'Start' at 06:42

Setup Time for WO-2501 = 42 minutes
```

---

## Dashboard vs Analytics Mode

### Mode Comparison

| Feature | Dashboard Mode | Analytics Mode |
|---------|---------------|----------------|
| **Data Source** | Real-time from `work_order_logs` | Historical from `work_order_logs` with caching |
| **Refresh Rate** | Manual refresh button (on-demand) | Calculated on-demand with caching |
| **Time Scope** | Current day only (today's WOs) | Any historical date range |
| **Purpose** | Monitor TODAY's setup activities | Analyze setup time trends over time |
| **Display** | Current setup summary + active setups | Summary cards + daily breakdown + machine breakdown |
| **Use Case** | "Which machines are being set up now?" | "Which machine has longest average setup time?" |

### When to Use Each Mode

**Use Dashboard Mode when:**
- You need to see setup activities happening today
- You want to track which machine is currently in setup
- You're monitoring production floor operations in real-time
- You want to manually refresh to get latest data on-demand

**Use Analytics Mode when:**
- You want to analyze setup time trends
- You need to identify which machines have excessive setup times
- You're optimizing production scheduling
- You want to compare setup times across machines or time periods
- You're preparing performance reports
- You want to investigate historical patterns

---

## Metrics Definitions

### 1. Setup Time (Per Work Order)

**What it measures:** Duration from when WO is Assigned to when it first Starts

**Formula:**
```
Setup Time = Time of first 'Start' status - Time of 'Assigned' status
           (from work_order_logs for the same WO)
```

**Example:**
```
WO-2501:
- Assigned: 2025-10-14 06:00:00
- Start:    2025-10-14 06:45:00
- Setup Time: 45 minutes
```

**Interpretation:**
- **<15 minutes**: Quick setup (standard/repetitive part)
- **15-45 minutes**: Normal setup (typical changeover)
- **45-90 minutes**: Extended setup (complex changeover)
- **>90 minutes**: Excessive (investigate cause)

---

### 2. Total Setup Time (Daily)

**What it measures:** Sum of all setup time across all machines on a given day

**Formula:**
```
Total Setup Time (per day) = SUM of all setup durations for all WOs
                            where Assigned status changed on that day
```

**Example:**
```
Oct 14, 2025:
- WO-2501: 45 minutes (Machine-007)
- WO-2502: 30 minutes (Machine-001)
- WO-2503: 20 minutes (Machine-005)
- WO-2504: 75 minutes (Machine-003)

Total Setup Time = 45 + 30 + 20 + 75 = 170 minutes (2.83 hours)
```

**Interpretation:**
- **<1 hour/day**: Excellent (minimal changeovers)
- **1-3 hours/day**: Good (balanced setup vs production)
- **3-5 hours/day**: Moderate (increasing setup burden)
- **>5 hours/day**: High (investigate setup optimization)

---

### 3. Average Setup Time (Per Machine)

**What it measures:** Average duration of each setup on a specific machine

**Formula:**
```
Average Setup Time = Total Setup Time for Machine X / Number of Setups
                   = SUM(setup_duration for WOs on Machine X) / COUNT(WOs on Machine X)
```

**Example:**
```
Machine-001 in October 2025:

WO-1001: 45 minutes
WO-1002: 50 minutes
WO-1003: 40 minutes
WO-1004: 48 minutes

Average = (45 + 50 + 40 + 48) / 4 = 45.75 minutes
```

**Interpretation:**
- **<30 minutes**: Efficient setup process
- **30-60 minutes**: Standard setup time
- **60-90 minutes**: Longer setup (investigate if normal)
- **>90 minutes**: Excessive (optimization needed)

---

### 4. Total Setup Time (Aggregated Period)

**What it measures:** Sum of all setup time across all machines over a selected time period

**Formula:**
```
Total Setup Time (period) = SUM of all daily totals in the period
                           = SUM(daily total setup times)
```

**Example:**
```
Last 7 Days (Oct 8-14):

Oct 14: 170 minutes
Oct 13: 145 minutes
Oct 12: 190 minutes
Oct 11: 155 minutes
Oct 10: 165 minutes
Oct 9:  135 minutes
Oct 8:  150 minutes

Total = 1,110 minutes (18.5 hours)
```

---

### 5. Average Daily Setup Time

**What it measures:** Average setup time per day across the selected period

**Formula:**
```
Average Daily Setup Time = Total Setup Time (period) / Number of Days
```

**Example:**
```
Last 7 Days (Oct 8-14):

Total: 1,110 minutes
Days: 7

Average Daily = 1,110 / 7 = 158.6 minutes per day (2.64 hours)
```

**Interpretation:**
- Shows what "normal" daily setup looks like
- Compare specific days to this average
- Identify high-setup days for investigation

---

### 6. Setup Time by Machine (Breakdown)

**What it measures:** Total setup time for each individual machine over the period

**Formula:**
```
Setup Time per Machine = SUM of all setups for Machine X in the period
```

**Example Table:**
```
Machine | Total Setup Time | Setups | Avg Setup Time | % of Total
---------|-----------------|--------|----------------|----------
Machine-001 | 245 min | 5 | 49 min | 22.1%
Machine-003 | 180 min | 3 | 60 min | 16.2%
Machine-005 | 165 min | 4 | 41 min | 14.9%
Machine-007 | 320 min | 4 | 80 min | 28.8% ⚠️ High
Machine-009 | 200 min | 4 | 50 min | 18.0%

Total | 1,110 min | 20 | 55.5 min | 100%
```

**Insights:**
- Machine-007: 28.8% of total setup time (highest)
- Machine-005: Most efficient (41 min average)
- Machine-007: Longest average setup (80 min) - investigate

---

### 7. Setup Frequency (Setups Per Machine Per Day)

**What it measures:** How many setup events occur per machine per day

**Formula:**
```
Setup Frequency = Number of Setups / Number of Days
```

**Example:**
```
Machine-001, Last 30 Days:

Total Setups: 45
Days: 30

Frequency = 45 / 30 = 1.5 setups per day
```

**Interpretation:**
- **0-1 setup/day**: Long production runs (good capacity)
- **1-2 setups/day**: Multiple products per day (normal)
- **2-3 setups/day**: High changeover frequency (investigate scheduling)
- **>3 setups/day**: Excessive setup burden (optimize order batching)

---

### 8. Setup Time as % of Available Production Time

**What it measures:** What percentage of available production time is spent on setup vs actual production

**Formula:**
```
Setup % = (Total Setup Time / Available Production Time) × 100

Where:
- Available Production Time = 8 hours per shift = 480 minutes
- For a full day: 8 hours × number of shifts
```

**Example:**
```
Oct 14, 2025 (8-hour shift):

Available Time: 480 minutes
Total Setup Time: 170 minutes
Setup % = (170 / 480) × 100 = 35.4%

Production Capacity Used: 64.6%
```

**Interpretation:**
- **<20%**: Excellent (more production time)
- **20-35%**: Good (balanced)
- **35-50%**: Moderate (acceptable)
- **>50%**: High (setup time exceeds production time - critical issue)

---

### 9. Setup Time Trend

**What it measures:** How setup times are changing over time

**Calculated as:**
```
Trend = Average Setup Time (Current Period) - Average Setup Time (Previous Period)
Percentage Change = ((Current - Previous) / Previous) × 100
```

**Example:**
```
Last Week: Average Daily Setup Time = 2.5 hours
Previous Week: Average Daily Setup Time = 2.2 hours

Trend: +0.3 hours (+13.6%)

Interpretation: Setup times increasing - investigate if due to:
- More product variety?
- Process degradation?
- New operators still learning?
- Machine maintenance issues?
```

---

## Data Sources

### Primary Table: work_order_logs

The setup time is calculated entirely from the `work_order_logs` table.

**Fields used:**
```sql
- work_order_id (which WO)
- status (status at this log entry: 'Assigned', 'Start', etc.)
- changed_at (timestamp when status changed)
- factory_id (to filter by factory)
```

### Query Strategy

**Getting Setup Time for a Specific Work Order:**

```php
// Step 1: Find when WO was 'Assigned'
$assignedLog = WorkOrderLog::where('work_order_id', $workOrderId)
    ->where('status', 'Assigned')
    ->orderBy('changed_at', 'asc')
    ->first();

// Step 2: Find first 'Start' after assignment
$startLog = WorkOrderLog::where('work_order_id', $workOrderId)
    ->where('status', 'Start')
    ->where('changed_at', '>', $assignedLog->changed_at)
    ->orderBy('changed_at', 'asc')
    ->first();

// Step 3: Calculate gap
if ($assignedLog && $startLog) {
    $setupTimeMinutes = $assignedLog->changed_at
        ->diffInMinutes($startLog->changed_at);
} else {
    $setupTimeMinutes = 0; // WO not yet started
}
```

**Getting Daily Setup Time:**

```php
$date = '2025-10-14';
$dayStart = Carbon::parse($date)->startOfDay();
$dayEnd = Carbon::parse($date)->endOfDay();

// Get all WOs assigned on this day
$assignedLogs = WorkOrderLog::where('factory_id', $factoryId)
    ->where('status', 'Assigned')
    ->whereBetween('changed_at', [$dayStart, $dayEnd])
    ->groupBy('work_order_id')
    ->get(['work_order_id', 'changed_at']);

$totalSetupTime = 0;

foreach ($assignedLogs as $assignedLog) {
    // Find corresponding Start log
    $startLog = WorkOrderLog::where('work_order_id', $assignedLog->work_order_id)
        ->where('status', 'Start')
        ->where('changed_at', '>', $assignedLog->changed_at)
        ->orderBy('changed_at', 'asc')
        ->first();

    if ($startLog) {
        $setupTime = $assignedLog->changed_at->diffInMinutes($startLog->changed_at);
        $totalSetupTime += $setupTime;
    }
}

// Result: Total setup time for the day
```

---

## Calculation Details

### Step-by-Step Setup Time Calculation

**For a Single Work Order:**

```
Query work_order_logs for WO-2501:

1. Find 'Assigned' entry:
   | id | work_order_id | status   | changed_at          |
   |----|---------------|----------|---------------------|
   | 501| 2501          | Assigned | 2025-10-14 06:00:00 |

2. Find first 'Start' entry after Assigned:
   | id | work_order_id | status | changed_at          |
   |----|---------------|--------|---------------------|
   | 502| 2501          | Start  | 2025-10-14 06:45:00 |

3. Calculate difference:
   Setup Time = 06:45:00 - 06:00:00 = 45 minutes
```

### Implementation in Service

**File:** `app/Services/KPI/OperationalKPIService.php`

**Method:** `getSetupTimeAnalytics(array $options): array`

```php
public function getSetupTimeAnalytics(array $options): array
{
    $period = $options['time_period'] ?? 'yesterday';
    $enableComparison = $options['enable_comparison'] ?? false;
    $machineFilter = $options['machine_id'] ?? null;

    [$startDate, $endDate] = $this->getDateRange($period);

    // Fetch primary period data
    $primaryData = $this->fetchSetupTimeDistribution($startDate, $endDate, $machineFilter);

    $result = [
        'primary_period' => [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'daily_breakdown' => $primaryData['daily'],
            'machine_breakdown' => $primaryData['by_machine'],
            'summary' => $primaryData['summary'],
        ],
    ];

    // Add comparison if enabled
    if ($enableComparison) {
        [$compStart, $compEnd] = $this->getComparisonDateRange($startDate, $endDate);
        $comparisonData = $this->fetchSetupTimeDistribution($compStart, $compEnd, $machineFilter);

        $result['comparison_period'] = [
            'start_date' => $compStart->toDateString(),
            'end_date' => $compEnd->toDateString(),
            'daily_breakdown' => $comparisonData['daily'],
            'machine_breakdown' => $comparisonData['by_machine'],
            'summary' => $comparisonData['summary'],
        ];

        $result['comparison_analysis'] = $this->calculateSetupTimeComparison(
            $primaryData['summary'],
            $comparisonData['summary']
        );
    }

    return $result;
}
```

**Helper Method:** `fetchSetupTimeDistribution(Carbon $startDate, Carbon $endDate, ?int $machineId): array`

```php
protected function fetchSetupTimeDistribution(Carbon $startDate, Carbon $endDate, ?int $machineId = null): array
{
    // Get all Assigned status logs in the date range
    $assignedLogs = WorkOrderLog::where('factory_id', $this->factory->id)
        ->where('status', 'Assigned')
        ->whereBetween('changed_at', [$startDate->startOfDay(), $endDate->endOfDay()])
        ->get();

    $dailyBreakdown = [];
    $machineBreakdown = [];
    $totalSetupTime = 0;

    foreach ($assignedLogs as $assignedLog) {
        // Find corresponding Start log
        $startLog = WorkOrderLog::where('work_order_id', $assignedLog->work_order_id)
            ->where('status', 'Start')
            ->where('changed_at', '>', $assignedLog->changed_at)
            ->orderBy('changed_at', 'asc')
            ->first();

        if (!$startLog) {
            continue; // WO not yet started
        }

        // Calculate setup time
        $setupMinutes = $assignedLog->changed_at->diffInMinutes($startLog->changed_at);

        // Get machine info from work order
        $workOrder = WorkOrder::find($assignedLog->work_order_id);
        if (!$workOrder) continue;

        // Apply machine filter if specified
        if ($machineId && $workOrder->machine_id != $machineId) {
            continue;
        }

        $date = $assignedLog->changed_at->toDateString();

        // Add to daily breakdown
        if (!isset($dailyBreakdown[$date])) {
            $dailyBreakdown[$date] = [
                'date' => $date,
                'total_setup_time' => 0,
                'total_setups' => 0,
                'avg_setup_time' => 0,
            ];
        }

        $dailyBreakdown[$date]['total_setup_time'] += $setupMinutes;
        $dailyBreakdown[$date]['total_setups'] += 1;
        $totalSetupTime += $setupMinutes;

        // Add to machine breakdown
        if (!isset($machineBreakdown[$workOrder->machine_id])) {
            $machineBreakdown[$workOrder->machine_id] = [
                'machine_id' => $workOrder->machine_id,
                'machine_name' => $workOrder->machine->name ?? 'Unknown',
                'asset_id' => $workOrder->machine->assetId ?? null,
                'total_setup_time' => 0,
                'total_setups' => 0,
                'daily_data' => [],
            ];
        }

        $machineBreakdown[$workOrder->machine_id]['total_setup_time'] += $setupMinutes;
        $machineBreakdown[$workOrder->machine_id]['total_setups'] += 1;

        if (!isset($machineBreakdown[$workOrder->machine_id]['daily_data'][$date])) {
            $machineBreakdown[$workOrder->machine_id]['daily_data'][$date] = [
                'date' => $date,
                'setup_time' => 0,
                'setup_count' => 0,
            ];
        }

        $machineBreakdown[$workOrder->machine_id]['daily_data'][$date]['setup_time'] += $setupMinutes;
        $machineBreakdown[$workOrder->machine_id]['daily_data'][$date]['setup_count'] += 1;
    }

    // Calculate averages for daily breakdown
    foreach ($dailyBreakdown as &$day) {
        $day['avg_setup_time'] = $day['total_setups'] > 0
            ? round($day['total_setup_time'] / $day['total_setups'], 2)
            : 0;
    }

    // Sort and prepare final data
    ksort($dailyBreakdown);
    $dailyBreakdown = array_values($dailyBreakdown);

    // Calculate summary statistics
    $totalDays = count($dailyBreakdown);
    $totalSetups = array_sum(array_column($dailyBreakdown, 'total_setups'));

    $summary = [
        'total_setup_time' => round($totalSetupTime / 60, 2), // Convert to hours
        'total_setup_minutes' => $totalSetupTime,
        'total_setups' => $totalSetups,
        'avg_daily_setup_time' => $totalDays > 0
            ? round($totalSetupTime / $totalDays / 60, 2) // Hours
            : 0,
        'avg_setup_duration' => $totalSetups > 0
            ? round($totalSetupTime / $totalSetups, 2) // Minutes
            : 0,
        'days_analyzed' => $totalDays,
        'machines_with_setups' => count($machineBreakdown),
    ];

    return [
        'daily' => $dailyBreakdown,
        'by_machine' => array_values($machineBreakdown),
        'summary' => $summary,
    ];
}
```

**Helper Method:** `calculateSetupTimeComparison(array $current, array $previous): array`

```php
protected function calculateSetupTimeComparison(array $current, array $previous): array
{
    return [
        'total_setup_time' => [
            'current' => $current['total_setup_time'],
            'previous' => $previous['total_setup_time'],
            'difference' => round($current['total_setup_time'] - $previous['total_setup_time'], 2),
            'percentage_change' => $previous['total_setup_time'] > 0
                ? round((($current['total_setup_time'] - $previous['total_setup_time']) / $previous['total_setup_time']) * 100, 2)
                : 0,
            'trend' => $current['total_setup_time'] > $previous['total_setup_time'] ? 'up' : 'down',
            'status' => $current['total_setup_time'] < $previous['total_setup_time'] ? 'improved' : 'declined',
        ],
        'avg_daily_setup_time' => [
            'current' => $current['avg_daily_setup_time'],
            'previous' => $previous['avg_daily_setup_time'],
            'difference' => round($current['avg_daily_setup_time'] - $previous['avg_daily_setup_time'], 2),
            'percentage_change' => $previous['avg_daily_setup_time'] > 0
                ? round((($current['avg_daily_setup_time'] - $previous['avg_daily_setup_time']) / $previous['avg_daily_setup_time']) * 100, 2)
                : 0,
            'trend' => $current['avg_daily_setup_time'] > $previous['avg_daily_setup_time'] ? 'up' : 'down',
            'status' => $current['avg_daily_setup_time'] < $previous['avg_daily_setup_time'] ? 'improved' : 'declined',
        ],
        'avg_setup_duration' => [
            'current' => $current['avg_setup_duration'],
            'previous' => $previous['avg_setup_duration'],
            'difference' => round($current['avg_setup_duration'] - $previous['avg_setup_duration'], 2),
            'percentage_change' => $previous['avg_setup_duration'] > 0
                ? round((($current['avg_setup_duration'] - $previous['avg_setup_duration']) / $previous['avg_setup_duration']) * 100, 2)
                : 0,
            'trend' => $current['avg_setup_duration'] > $previous['avg_setup_duration'] ? 'up' : 'down',
            'status' => $current['avg_setup_duration'] < $previous['avg_setup_duration'] ? 'improved' : 'declined',
        ],
    ];
}
```

---

## Data Population

### How Setup Time Data is Updated

**Important:** Setup Time data is **calculated on-demand with caching**. It does **NOT** require background jobs or pre-aggregation.

### Flow Diagram

```
User Opens Analytics
       ↓
Check Cache (TenantKPICache)
       ↓
   Cached? ─── Yes ──→ Return Cached Data (Fast!) ✓
       ↓
      No
       ↓
Query work_order_logs for 'Assigned' and 'Start' entries
       ↓
Loop through each Assigned entry
       ↓
Find corresponding Start entry
       ↓
Calculate setup time gap
       ↓
Build daily and machine breakdowns
       ↓
Calculate summary statistics
       ↓
Store in cache (with TTL)
       ↓
Return fresh data to user
```

### Caching Strategy

**Cache Key Format:**
```php
$cacheKey = "setup_time_analytics_{$period}_" . md5(json_encode($options));
```

**Cache TTL:**
| Time Period | Cache Duration | Reasoning |
|-------------|----------------|-----------|
| Yesterday | 3600 seconds (1 hour) | Historical, stable data |
| Last 7 Days | 3600 seconds (1 hour) | Week data is fixed |
| Last 30 Days | 7200 seconds (2 hours) | Larger dataset, less frequent changes |
| Custom Range | 1800 seconds (30 minutes) | May update more frequently |
| Today | 300 seconds (5 minutes) | Data actively changing throughout day |

**Cache Tags:** `factory_{factory_id}`, `tier_2`, `kpi`

---

## Time Periods

### Available Time Period Options

Analytics Mode supports the following time periods:

#### Single Day Options
- **Today**: Current day
- **Yesterday**: Previous day

#### Week Options
- **This Week**: Monday to Sunday of current week
- **Last Week**: Monday to Sunday of previous week
- **Last 7 Days**: Rolling 7-day window

#### Month Options
- **This Month**: 1st to last day of current month
- **Last Month**: 1st to last day of previous month
- **Last 14 Days**: Rolling 14-day window
- **Last 30 Days**: Rolling 30-day window

#### Quarter & Year Options
- **This Quarter**: Current quarter
- **Last 60 Days**: Rolling 60-day window
- **Last 90 Days**: Rolling 90-day window

#### Custom Range
- **Custom Date Range**: User selects start and end dates

---

## Comparison Mode

### What is Comparison Mode?

Comparison Mode allows you to **compare current period setup times with a previous period** to identify trends and improvements.

### Comparison Types

#### 1. Previous Period (Default)
Compare with period of same duration immediately before.

**Example:**
```
Current: Oct 1-14 (14 days)
Previous: Sep 17-30 (14 days)
```

#### 2. Previous Week
Compare with same day range in previous week.

**Example:**
```
Current: Oct 8-14
Previous: Oct 1-7
```

#### 3. Previous Month
Compare with same dates in previous month.

**Example:**
```
Current: Oct 1-14
Previous: Sep 1-14
```

#### 4. Previous Year
Compare with exact same dates one year ago.

**Example:**
```
Current: Oct 1-14, 2025
Previous: Oct 1-14, 2024
```

### Comparison Display

**Summary Cards with Trends:**
```
┌────────────────────────────────────┐
│ Avg Daily Setup Time               │
│ 2.5 hrs    ↓ -0.3 hrs vs Prev      │
│            (-10.7%)                 │
│            (was 2.8 hrs)             │
└────────────────────────────────────┘

┌────────────────────────────────────┐
│ Avg Setup Duration per Setup       │
│ 42 min     ↓ -3 min vs Prev        │
│            (-6.7%)                  │
│            (was 45 min)              │
└────────────────────────────────────┘
```

### Trend Interpretation

- **↓ Avg Daily Setup Time**: Good (less wasted time on setup)
- **↓ Avg Setup Duration**: Good (faster setups, better efficiency)
- **↑ Total Setups (same period)**: Could indicate more production variety or scheduling changes
- **Stable Avg Setup Duration**: Consistent, standardized setup processes

---

## Use Cases & Examples

### Use Case 1: Identifying Problem Machines

**Scenario:** Plant manager wants to find which machines have excessive setup times.

**Steps:**
1. Navigate to Setup Time Analytics
2. Select "Last 30 Days"
3. Review "Machine Breakdown" section
4. Identify machines with highest average setup time

**Sample Findings:**
```
Machine Performance Summary (Last 30 Days):

Machine      | Total Setup | Setups | Avg Setup | % of Total | Status
-------------|-------------|--------|-----------|------------|--------
Machine-001  | 10.5 hrs    | 50     | 12.6 min  | 18.5%      | ✓ Excellent
Machine-003  | 18.7 hrs    | 42     | 26.8 min  | 32.9%      | ✓ Good
Machine-005  | 9.3 hrs     | 35     | 16.0 min  | 16.4%      | ✓ Excellent
Machine-007  | 23.5 hrs    | 38     | 37.1 min  | 41.4%      | ⚠️ High
Machine-009  | 14.1 hrs    | 44     | 19.2 min  | 24.8%      | ✓ Good

Total        | 56.8 hrs    | 209    | 16.4 min  | 100%       |

Insights:
- Machine-007 takes ~3x longer to set up than Machine-001
- Machine-007 is 41% of total setup time despite being 1 of 5 machines
- Could indicate: Complex product mix, skill gaps, process issues
```

**Actions:**
- Schedule machine audit for Machine-007
- Check if Machine-007 is used for more complex products
- Review setup procedures documentation
- Assess operator skill levels on Machine-007
- Consider training or process standardization

---

### Use Case 2: Trend Analysis - Are Setup Times Improving?

**Scenario:** Operations manager tracks if improvement initiatives are working.

**Steps:**
1. Navigate to Setup Time Analytics
2. Select "This Month" (Oct 1-31)
3. Enable Comparison: "Previous Month" (Sep 1-30)
4. Review trend indicators

**Sample Findings:**
```
October vs September:

Avg Daily Setup Time:
- October: 2.4 hours
- September: 2.8 hours
- Change: ↓ -0.4 hours (-14.3%) ✓ Improving

Avg Setup Duration:
- October: 42.5 minutes
- September: 48.2 minutes
- Change: ↓ -5.7 min (-11.8%) ✓ Faster setups

Total Setup Time:
- October: 74.4 hours (31 days)
- September: 82.1 hours (30 days)
- Change: ↓ Better despite more days

Improvement Drivers:
✓ Faster individual setups (standardization working)
✓ Lower overall setup burden (better scheduling)
✓ More consistent setup durations across machines
```

**Interpretation:**
The improvement initiative (SMED training, new setup checklist, operator coaching) is working! Setup times declining month-over-month.

---

### Use Case 3: Capacity Planning

**Scenario:** Planner needs to understand how much productive time is available.

**Steps:**
1. Navigate to Setup Time Analytics
2. Select "Last 7 Days"
3. Calculate setup % of available time

**Sample Findings:**
```
Last 7 Days (8-hour shifts):

Date    | Total Setup | Daily Setup % | Available for Production
--------|-------------|---------------|------------------------
Oct 14  | 2.8 hrs     | 35%           | 65% available
Oct 13  | 2.4 hrs     | 30%           | 70% available
Oct 12  | 3.2 hrs     | 40%           | 60% available ⚠️ Low
Oct 11  | 2.6 hrs     | 32.5%         | 67.5% available
Oct 10  | 2.9 hrs     | 36.3%         | 63.7% available
Oct 9   | 2.2 hrs     | 27.5%         | 72.5% available ✓ High
Oct 8   | 2.5 hrs     | 31.3%         | 68.7% available

Average: 32.9% setup time | 67.1% production capacity available

Planning Implications:
- On average, can commit to 67% of theoretical capacity
- Oct 12 was low-production day due to high setup time
- When scheduling high-volume orders, account for 33% setup overhead
- Target: Reduce to 25-30% setup time to unlock 70-75% production capacity
```

**Capacity Formula:**
```
Realistic Production Capacity = Available Production Time - Setup Time
                              = 8 hrs - 2.6 hrs (average)
                              = 5.4 hours per day (67.5% of shift)
```

---

### Use Case 4: Setup Optimization Project ROI

**Scenario:** Engineer planning setup time reduction initiative to prove ROI.

**Steps:**
1. Select "Last 90 Days"
2. Break down setup time by machine
3. Identify optimization opportunities
4. Calculate potential savings

**Sample Analysis:**
```
Setup Time Analysis (Last 90 Days):

Current State:
- Total Setup Time: 238 hours
- Avg Daily: 2.64 hours
- Avg per Setup: 46.3 minutes
- Average Setup % of Shift: 33%

Top 3 Optimization Opportunities:

1. Machine-007 (23.5 hrs of 238 total = 9.9%):
   - Current avg: 37 min per setup
   - Target (industry standard): 20 min
   - Potential savings: 17 min × 38 setups = 10.7 hours

2. Process Standardization (all machines):
   - Current variance: 12-37 min per machine
   - Target: Bring all to 20 min average
   - Potential savings: (46 - 20) × 209 setups = 136 hours

3. Quick Changeover (SMED) Implementation:
   - External tool setup: 15 min → 8 min
   - Material prep: 8 min → 3 min
   - Calibration: 10 min → 7 min
   - Total savings: 13 min per setup × 209 = 45 hours

Combined Optimization:
- Potential savings: 192 hours over 90 days
- Annual projection: 256 hours = 32 productive days
- Dollar value: 256 hours × $50/hr labor = $12,800 saved annually

ROI Calculation:
- SMED training investment: $3,000
- Equipment/tooling: $2,000
- Documentation/standards: $1,000
- Total: $6,000
- Annual savings: $12,800
- **Payback period: 5.6 months**
- **Year 2+: $12,800 pure benefit**
```

---

## Dashboard Mode (Today's Data)

### Display Components

#### 1. Setup Summary Card
Shows real-time summary of today's setup activity

**Display:**
```
┌─────────────────────────────┐
│ Today's Setup Activity      │
│ Last Updated: 14:32         │
├─────────────────────────────┤
│ Active Setups: 1            │
│ Completed Setups: 8         │
│ Total Setup Time: 3.2 hrs   │
│ Avg Setup Duration: 24 min  │
└─────────────────────────────┘
```

#### 2. Currently Setting Up
Shows which machines are currently in setup (Assigned status but not yet Started)

**Display:**
```
Currently Setting Up:

Machine-007 (since 13:45)
- Work Order: WO-2501
- Operator: Maria
- Setup Duration so far: 32 minutes
- [Refresh] Button
```

#### 3. Setup History (Today)
Table of all setups completed today

**Display:**
```
Date/Time  | Machine    | Operator | Setup Time | Status
-----------|------------|----------|------------|--------
14:32      | Machine-007| Maria    | 47 min     | In Progress...
13:45      | Machine-005| Ahmed    | 22 min     | Complete ✓
13:15      | Machine-001| John     | 19 min     | Complete ✓
12:30      | Machine-003| Maria    | 35 min     | Complete ✓
```

---

## Future Enhancements

### Phase 1: Setup Reason Tracking

**Enhanced Setup Analysis**
- Track setup reasons:
  - Product changeover
  - Fixture/tool change
  - Calibration/maintenance
  - Operator break
  - Material change

**Implementation:**
```php
ADD COLUMN setup_reason ENUM('product_change', 'fixture_change', 'calibration', 'break', 'material_change');
```

---

### Phase 2: Setup Time Standards

**Establish Benchmarks**
- Define standard setup times by:
  - Machine type
  - Product family
  - Setup type
  - Operator skill level

**Display:**
```
Actual vs Standard:

Machine-001:
- Standard: 15 min
- Actual: 17 min
- Variance: +2 min (+13%) ⚠️

Indicates: Slightly slower than standard
```

---

### Phase 3: Real-Time Setup Monitoring

**Live Dashboard**
- Show which machines are currently in setup
- Estimate setup completion time
- Alert if setup exceeds standard
- Real-time duration tracking

---

### Phase 4: Operator Performance

**Per-Operator Setup Analysis**
- Setup time by operator
- Identify fastest/slowest operators
- Track improvement over time
- Training effectiveness measurement

**Display:**
```
Operator Setup Performance (October):

Operator   | Total Setups | Avg Time | Best Time | Worst Time
-----------|--------------|----------|-----------|----------
John       | 42          | 43 min   | 25 min    | 75 min
Maria      | 38          | 38 min   | 20 min    | 60 min ✓ Best
Ahmed      | 35          | 51 min   | 30 min    | 90 min ⚠️ High
```

---

### Phase 5: Predictive Analytics

**Machine Learning Integration**
- Predict future setup times based on:
  - Product type
  - Operator
  - Time of day
  - Recent machine maintenance
  - Historical patterns

**Use Case:**
```
Next Setup Prediction (Machine-007):

Next scheduled: WO-2501 (Product-ABC)
Predicted setup time: 38 minutes
Confidence: 92%
Optimal start time: 13:22

Factors:
✓ Product-ABC on Machine-007: typically 35-40 min
✓ Operator Maria: 10% faster than average
⚠️ Last setup on this machine: 45 min (slightly slow)
```

---

### Phase 6: Setup Time Benchmarking

**Multi-Machine Comparison**
- Rank machines by setup efficiency
- Identify best-practices
- Share across fleet

**Display:**
```
Machine Efficiency Ranking:

1. Machine-009: 16 min avg (Top performer) ⭐
2. Machine-005: 18 min avg
3. Machine-001: 21 min avg
4. Machine-003: 27 min avg
5. Machine-007: 37 min avg (132% slower than best)

Action: Apply Machine-009's process to Machine-007
```

---

## Implementation Checklist

### Backend Requirements

- [ ] Verify `work_order_logs` table has `work_order_id`, `status`, `changed_at` fields
- [ ] Ensure indexes on (`factory_id`, `status`, `changed_at`)
- [ ] Verify `work_orders` has relationship to machines
- [ ] Test setup time calculation logic in local environment

### Service Layer

- [ ] Create `getSetupTimeAnalytics()` method in `OperationalKPIService`
- [ ] Create `fetchSetupTimeDistribution()` helper method
- [ ] Create `calculateSetupTimeComparison()` helper method
- [ ] Implement caching with appropriate TTLs
- [ ] Add setup time calculation validation

### Dashboard Component

- [ ] Create Dashboard Mode widget/Livewire component
- [ ] Display today's setup summary
- [ ] Show currently active setups
- [ ] Add manual refresh button
- [ ] Display setup history table for today

### Analytics Component

- [ ] Create Analytics Mode page/resource
- [ ] Summary cards (Total, Avg Daily, Avg per Setup, etc.)
- [ ] Daily breakdown table
- [ ] Machine breakdown table with sorting
- [ ] Comparison mode with trend indicators
- [ ] Time period selector
- [ ] Machine filter dropdown

### Testing

- [ ] Unit tests for setup time calculation
- [ ] Feature tests for analytics display
- [ ] Verify correct log entries are matched
- [ ] Test cache functionality
- [ ] Test comparison calculations

### Documentation

- [ ] Update KPI Registry with Setup Time KPI
- [ ] Add to navigation menu
- [ ] Create user documentation/help
- [ ] Add to API documentation if applicable

---

## Conclusion

Setup Time Per Machine is a critical operational KPI that directly impacts factory profitability and efficiency. By tracking, analyzing, and continuously improving setup times, factories can:

- **Increase productive capacity** without additional equipment investment
- **Reduce costs** by eliminating non-value-added time
- **Improve scheduling** accuracy and reliability
- **Develop operator skills** through data-driven coaching
- **Maximize asset utilization** and ROI

For questions or suggestions, contact the development team or file an issue in the project repository.

---

**Last Updated:** October 21, 2025
**Version:** 1.0 (Planning Document)
**Author:** ProdStream Development Team
