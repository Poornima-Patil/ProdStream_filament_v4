# Machine Utilization Rate KPI Documentation

**Last Updated:** October 21, 2025
**Status:** Active (Dashboard Mode & Analytics Mode)
**Version:** 2.0

---

## Table of Contents
1. [Overview](#overview)
2. [Critical Rule: Dashboard Always Shows TODAY](#critical-rule-dashboard-always-shows-today)
3. [Two Types of Utilization](#two-types-of-utilization)
4. [Dashboard Mode vs Analytics Mode](#dashboard-mode-vs-analytics-mode)
5. [Code Implementation](#code-implementation)
6. [Data Flow](#data-flow)
7. [Use Cases](#use-cases)
8. [Interpretation Guidelines](#interpretation-guidelines)

---

## Overview

### What is Machine Utilization Rate?

Machine Utilization Rate measures how effectively machines are being used during available production time. This KPI answers the question: **"Are our machines productive, or are they sitting idle?"**

### Key Insight

The system tracks **TWO distinct types of utilization**:
- **Scheduled Utilization** (Factory View) - Includes hold time
- **Active Utilization** (Operator View) - Excludes hold time

The difference between these two metrics reveals time spent in hold/delay states, which is critical for identifying operational bottlenecks.

---

## Critical Rule: Dashboard Always Shows TODAY

### The Golden Rule

**DASHBOARD MODE = TODAY ONLY**

This is a fundamental principle across ALL KPIs in the dashboard:
- Machine Status Dashboard → TODAY
- Work Order Status Dashboard → TODAY
- Production Schedule Adherence Dashboard → TODAY
- **Machine Utilization Rate Dashboard → TODAY**

### Why TODAY Only?

Dashboard mode is designed for **real-time operational monitoring**:
- Production supervisors need to see **current day performance**
- Operators need to know **today's utilization status**
- Managers need to make **immediate decisions** based on today's data
- Historical analysis belongs in Analytics Mode

### Code Evidence

From `RealTimeKPIService.php` line 750-754:

```php
/**
 * Get machine utilization metrics for TODAY
 * Shows both Scheduled Utilization (factory view) and Active Utilization (operator view)
 *
 * DASHBOARD MODE (Real-Time):
 * - Shows ONLY TODAY's data (work orders with start_time = today)
 * - Calculates utilization for current day in real-time
 */
public function getMachineUtilization(bool $skipCache = false): array
```

The first two lines in the method establish TODAY's boundaries:

```php
$today = now()->startOfDay();       // 00:00:00 today
$endOfToday = now()->endOfDay();    // 23:59:59 today
```

### Data Query Filter

Line 789-792 shows the **TODAY filter** applied to work orders:

```php
$workOrders = \App\Models\WorkOrder::where('factory_id', $this->factory->id)
    ->where('machine_id', $machine->id)
    ->whereDate('start_time', $today)  // ← TODAY FILTER
    ->whereIn('status', ['Start', 'Completed', 'Hold'])
    ->get();
```

**Critical:** Only work orders that START today are counted. This ensures:
- Work orders scheduled for yesterday don't affect today's utilization
- Work orders scheduled for tomorrow don't appear in today's dashboard
- Data is always relevant to current day operations

---

## Two Types of Utilization

### 1. Scheduled Utilization (Factory View)

**What it measures:** Percentage of available time with work orders scheduled, **including hold periods**.

**Formula:**
```
Scheduled Utilization = (Scheduled Work Time / Available Time) × 100

Where:
- Scheduled Work Time = Sum of (end_time - start_time) for all work orders with status IN ('Start', 'Completed', 'Hold')
- Available Time = Total shift hours for the day
```

**Code Implementation** (RealTimeKPIService.php:816-833):

```php
// CALCULATION 1: Scheduled Utilization (Factory View)
// Sum of (end_time - start_time) for all work orders, clipped to today
$scheduledSeconds = 0;

foreach ($workOrders as $wo) {
    $start = \Carbon\Carbon::parse($wo->start_time);
    $end = \Carbon\Carbon::parse($wo->end_time);

    // Clip to today's boundaries
    $effectiveStart = $start->lt($today) ? $today->copy() : $start->copy();
    $effectiveEnd = $end->gt($endOfToday) ? $endOfToday->copy() : $end->copy();

    if ($effectiveEnd->gt($effectiveStart)) {
        $scheduledSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
    }
}

$scheduledHours = round($scheduledSeconds / 3600, 2);
```

**Key Points:**
- Uses `work_orders.start_time` and `work_orders.end_time` columns
- Counts entire duration from start to end, including any hold periods
- Work orders spanning multiple days are clipped to today's boundaries
- This is the **factory/management perspective** - measures total time machines were assigned work

**Example:**
```
Machine-001 on Oct 17, 2025
- Available Time: 24 hours (3 shifts × 8 hours)

Work Orders:
- WO1: 06:00-14:00 (8 hours, Status: Completed)
  Includes: 1.5 hours on hold for material
- WO2: 14:00-22:00 (8 hours, Status: Start)
  Includes: 2 hours on hold for quality check

Scheduled Work Time: 8 + 8 = 16 hours (entire duration, including holds)
Scheduled Utilization: (16 / 24) × 100 = 66.67%
```

**Database Field:** `kpi_machine_daily.utilization_rate`

---

### 2. Active Utilization (Operator View)

**What it measures:** Percentage of available time machines were **actively running**, **excluding hold periods**.

**Formula:**
```
Active Utilization = (Active Work Time / Available Time) × 100

Where:
- Active Work Time = Sum of durations when work order status was 'Start' (tracked in work_order_logs)
- Available Time = Total shift hours for the day
- Excludes: All hold periods, setup time, waiting time
```

**Code Implementation** (RealTimeKPIService.php:835-882):

```php
// CALCULATION 2: Active Utilization (Operator View)
// Only count time when status = 'Start' (from work_order_logs)
$activeSeconds = 0;

foreach ($workOrders as $wo) {
    // Get all status change logs for this work order
    $logs = \App\Models\WorkOrderLog::where('work_order_id', $wo->id)
        ->orderBy('changed_at', 'asc')
        ->get(['status', 'changed_at']);

    if ($logs->isEmpty()) {
        continue;
    }

    $previousLog = null;

    foreach ($logs as $log) {
        if ($previousLog && $previousLog->status === 'Start') {
            // Machine was actively running from previousLog to current log
            $startTime = \Carbon\Carbon::parse($previousLog->changed_at);
            $endTime = \Carbon\Carbon::parse($log->changed_at);

            // Clip to today's boundaries
            $effectiveStart = $startTime->lt($today) ? $today->copy() : $startTime->copy();
            $effectiveEnd = $endTime->gt($endOfToday) ? $endOfToday->copy() : $endTime->copy();

            if ($effectiveEnd->gt($effectiveStart)) {
                $activeSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
            }
        }

        $previousLog = $log;
    }

    // Handle ongoing work orders still in 'Start' status
    if ($previousLog && $previousLog->status === 'Start') {
        $startTime = \Carbon\Carbon::parse($previousLog->changed_at);
        $endTime = now();

        // Clip to today's boundaries
        $effectiveStart = $startTime->lt($today) ? $today->copy() : $startTime->copy();
        $effectiveEnd = $endTime->gt($endOfToday) ? $endOfToday->copy() : $endTime->copy();

        if ($effectiveEnd->gt($effectiveStart)) {
            $activeSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
        }
    }
}

$activeHours = round($activeSeconds / 3600, 2);
```

**Key Points:**
- Uses `work_order_logs` table to track precise status changes
- Only counts time between status = 'Start' and next status change
- Precisely excludes hold periods by skipping them in calculation
- This is the **operator/production perspective** - measures actual productive work time

**Calculation Logic:**
```
For each work order:
1. Get all status changes from work_order_logs ordered by changed_at
2. Iterate through logs:
   - If previous status was 'Start' and current status is 'Hold'/'Completed':
     → Count the time difference as active time
   - If status is 'Hold': skip this period (not counted)
3. If work order is still in 'Start' status:
   → Count time from last 'Start' until now()
4. Sum all active time periods
5. Calculate percentage vs available hours
```

**Example:**
```
Machine-001 on Oct 17, 2025
- Available Time: 24 hours

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
Active Utilization: (12.5 / 24) × 100 = 52.08%
```

**Database Field:** `kpi_machine_daily.active_utilization_rate` (for Analytics mode)

---

### Key Differences

| Aspect | Scheduled Utilization | Active Utilization |
|--------|----------------------|-------------------|
| **Perspective** | Factory/Management | Operator/Production |
| **Includes Holds?** | ✅ Yes | ❌ No |
| **Data Source** | `work_orders` table | `work_order_logs` table |
| **Calculation** | end_time - start_time | Sum of 'Start' status durations |
| **Value** | Higher | Lower (excludes holds) |
| **Use Case** | Machine assignment planning | Operator performance |

**Relationship:**
```
Active Utilization ≤ Scheduled Utilization

The difference indicates time spent in hold/delay states:
Hold Time ≈ Scheduled Utilization - Active Utilization
```

**Real Example from Dashboard:**
```
Machine-003 on Oct 17, 2025:
- Scheduled Utilization: 65.0% (15.6 hours scheduled)
- Active Utilization: 52.3% (12.6 hours actively running)
- Difference: 12.7% (3.0 hours in hold)

Interpretation: Machine had work assigned for 65% of the day,
but only ran actively for 52%, with 13% spent waiting/on hold.
```

---

## Dashboard Mode vs Analytics Mode

| Feature | Dashboard Mode | Analytics Mode |
|---------|---------------|----------------|
| **Data Source** | Real-time from `work_orders` & `work_order_logs` tables | Pre-aggregated from `kpi_machine_daily` table |
| **Date Filter** | **TODAY ONLY** | Any historical date range |
| **Refresh** | Manual refresh button | Static historical data |
| **Purpose** | Monitor TODAY's utilization | Analyze historical trends |
| **Use Case** | "How are we doing TODAY?" | "How did we perform last month?" |
| **Status** | ✅ **Active** (Implemented) | ✅ **Active** (Implemented v2.0) |

### Dashboard Mode

**Current Implementation:**

Service: `RealTimeKPIService::getMachineUtilization()`
- File: `app/Services/KPI/RealTimeKPIService.php` (lines 745-957)
- Cache: 5 minutes (300 seconds)
- Refresh: Manual via refresh button

View: `machine-utilization.blade.php`
- File: `resources/views/filament/admin/pages/machine-utilization.blade.php`
- Displays TODAY's data only
- Shows factory-wide summary + per-machine breakdown table

### Analytics Mode

**Status:** ✅ **Active** (Implemented in v2.0)

**Implemented Features:**

Service: `OperationalKPIService::getMachineUtilizationAnalytics()`
- File: `app/Services/KPI/OperationalKPIService.php`
- Data Source: `kpi_machine_daily` table (pre-aggregated daily metrics)
- Cache: Variable TTL based on data freshness (5 min for today, 6 hours for old data)

View: `machine-utilization-analytics.blade.php`
- File: `resources/views/filament/admin/pages/machine-utilization-analytics.blade.php`
- Displays historical utilization metrics with daily breakdown
- Supports multiple time periods and custom date ranges
- Includes period-over-period comparison analysis

**Features:**
✅ Historical date range selection (yesterday, last week, last month, custom range)
✅ Daily breakdown table with pagination
✅ Comparison mode (previous period, previous week/month/quarter/year)
✅ Aggregated data from `kpi_machine_daily` table
✅ Summary cards with trend indicators
✅ Comprehensive comparison analysis section

**Time Period Options:**
- Today, Yesterday
- This Week, Last Week
- This Month, Last Month
- Last 7/14/30/60/90 Days
- This Quarter, This Year
- Custom Date Range

**Comparison Types:**
- Previous Period (same duration)
- Previous Week
- Previous Month
- Previous Quarter
- Same Period Last Year

**Metrics Tracked:**
1. **Scheduled Utilization** - Average % across selected period
2. **Active Utilization** - Average % when machines are running
3. **Uptime Hours** - Total productive machine hours
4. **Downtime Hours** - Total non-productive hours
5. **Units Produced** - Total units manufactured
6. **Work Orders Completed** - Total WOs finished

**Data Structure:**
```php
[
    'primary_period' => [
        'start_date' => '2025-10-14',
        'end_date' => '2025-10-20',
        'label' => 'Last Week',
        'daily_breakdown' => [
            [
                'date' => '2025-10-14',
                'avg_utilization_rate' => 65.5,
                'avg_active_utilization_rate' => 52.3,
                'uptime_hours' => 87.4,
                'downtime_hours' => 21.8,
                'units_produced' => 1250,
                'work_orders_completed' => 15,
                'machines_tracked' => 7,
            ],
            // ... more daily records
        ],
        'summary' => [
            'avg_scheduled_utilization' => 66.2,
            'avg_active_utilization' => 53.1,
            'total_uptime_hours' => 612.8,
            'total_downtime_hours' => 152.2,
            'total_units_produced' => 8750,
            'total_work_orders_completed' => 105,
            'machines_analyzed' => 7,
            'days_analyzed' => 7,
        ],
    ],
    'comparison_period' => [...],  // If comparison enabled
    'comparison_analysis' => [
        'scheduled_utilization' => [
            'current' => 66.2,
            'previous' => 58.5,
            'difference' => 7.7,
            'percentage_change' => 13.16,
            'trend' => 'up',
            'status' => 'improved',
        ],
        // ... more metrics
    ],
]
```

**Smart Caching Strategy:**
```php
protected function getCacheTTL(string $period): int
{
    return match($period) {
        'today' => 300,        // 5 minutes
        'yesterday' => 900,    // 15 minutes
        'last_week' => 3600,   // 1 hour
        'last_month' => 21600, // 6 hours
        default => 1800        // 30 minutes
    };
}
```

**Implementation Details:**

Backend Methods:
1. `getMachineUtilizationAnalytics()` - Main analytics entry point
2. `fetchMachineUtilizationData()` - Queries kpi_machine_daily table
3. `calculateMachineUtilizationComparison()` - Compares periods

Frontend Components:
1. Period header with date range
2. 4 summary cards (scheduled %, active %, uptime, downtime)
3. Daily breakdown table with pagination
4. Comparison analysis section with 6 metric cards
5. Trend indicators (up/down arrows with color coding)

**Visual Indicators:**
- Green: Improved metrics
- Red: Declined metrics
- Trend arrows show direction
- Percentage changes calculated automatically

---

## Code Implementation

### Service Method Structure

**File:** `app/Services/KPI/RealTimeKPIService.php`

```php
public function getMachineUtilization(bool $skipCache = false): array
{
    $callback = function () {
        // 1. Define TODAY boundaries
        $today = now()->startOfDay();
        $endOfToday = now()->endOfDay();

        // 2. Get all machines
        $machines = Machine::where('factory_id', $this->factory->id)->get();

        // 3. Calculate available hours per machine from shifts
        $availableHoursPerMachine = [calculated from factory shifts];

        // 4. Loop through each machine
        foreach ($machines as $machine) {
            // 4a. Get work orders scheduled for TODAY
            $workOrders = WorkOrder::where('machine_id', $machine->id)
                ->whereDate('start_time', $today)  // ← TODAY FILTER
                ->whereIn('status', ['Start', 'Completed', 'Hold'])
                ->get();

            // 4b. Calculate Scheduled Utilization
            // Sum of (end_time - start_time), clipped to today
            $scheduledHours = [calculated];

            // 4c. Calculate Active Utilization
            // Sum of time in 'Start' status from work_order_logs
            $activeHours = [calculated];

            // 4d. Calculate derived metrics
            $holdHours = $scheduledHours - $activeHours;
            $idleHours = $availableHours - $scheduledHours;

            // 4e. Store machine details
            $machineDetails[] = [...];
        }

        // 5. Calculate factory-wide summary
        $factoryScheduledUtilization = [calculated];
        $factoryActiveUtilization = [calculated];

        // 6. Return structured data
        return [
            'summary' => [...],
            'machines' => $machineDetails,
            'updated_at' => now()->toDateTimeString(),
        ];
    };

    // Cache for 5 minutes, or skip cache if manual refresh
    return $skipCache ? $callback() : $this->getCachedKPI('machine_utilization', $callback, 300);
}
```

### Response Data Structure

```php
[
    'summary' => [
        'scheduled_utilization_rate' => 65.5,      // Factory-wide scheduled %
        'active_utilization_rate' => 52.3,         // Factory-wide active %
        'total_machines' => 7,
        'machines_with_work' => 5,
        'machines_idle' => 2,
        'total_scheduled_hours' => 109.2,
        'total_active_hours' => 87.4,
        'total_hold_hours' => 21.8,
        'total_available_hours' => 168.0,          // 7 machines × 24 hours
        'date' => '2025-10-17',                    // TODAY
    ],
    'machines' => [
        [
            'id' => 1,
            'name' => 'Machine-001',
            'asset_id' => 'M001',
            'scheduled_utilization' => 75.0,
            'active_utilization' => 62.5,
            'scheduled_hours' => 18.0,
            'active_hours' => 15.0,
            'available_hours' => 24.0,
            'hold_hours' => 3.0,
            'idle_hours' => 6.0,
            'work_order_count' => 3,
        ],
        // ... more machines
    ],
    'updated_at' => '2025-10-17 14:30:00',
]
```

### Blade Template Integration

**File:** `resources/views/filament/admin/pages/kpi-analytics-dashboard.blade.php`

```blade
{{-- Machine Utilization Rate KPI Content --}}
@if($selectedKPI === 'machine_utilization')
    @if($kpiMode === 'dashboard')
        {{-- Dashboard Mode: Shows TODAY's data only --}}
        @include('filament.admin.pages.machine-utilization')
    @else
        {{-- Analytics Mode: Historical data from kpi_machine_daily table --}}
        <x-filament::card>
            <div class="space-y-4">
                <h2 class="text-lg font-semibold">Machine Utilization Rate - Analytics</h2>
                @php $data = $this->getMachineUtilizationData(); @endphp
                @include('filament.admin.pages.machine-utilization-analytics')
            </div>
        </x-filament::card>
    @endif
@endif
```

**Dashboard View Template:** `resources/views/filament/admin/pages/machine-utilization.blade.php`

Structure:
1. **Factory-Wide Summary Cards**
   - Scheduled Utilization %
   - Active Utilization %
   - Active Machines Count
   - Idle Machines Count

2. **Utilization Gap Indicator**
   - Shows difference between scheduled and active utilization
   - Highlights hold time issues if gap > 10%

3. **Per-Machine Breakdown Table**
   - Sortable columns
   - Progress bars for visual utilization representation
   - Color coding (green > 70%, yellow 40-70%, red < 40%)
   - Shows all metrics per machine

4. **Legend**
   - Explains what each metric means
   - Helps users understand the data

**Analytics View Template:** `resources/views/filament/admin/pages/machine-utilization-analytics.blade.php`

Structure:
1. **Period Header**
   - Selected time period label
   - Date range display
   - Machines analyzed count

2. **Summary Cards** (4 cards with comparison indicators)
   - Scheduled Utilization % with trend
   - Active Utilization % with trend
   - Total Uptime Hours with trend
   - Total Downtime Hours with trend

3. **Daily Breakdown Table**
   - Date column with formatted dates
   - Scheduled Utilization % per day
   - Active Utilization % per day
   - Uptime/Downtime hours per day
   - Units produced per day
   - Work orders completed per day
   - Machines tracked per day
   - Pagination support (10 rows per page)

4. **Comparison Analysis Section** (when enabled)
   - 6 metric comparison cards:
     - Scheduled Utilization comparison
     - Active Utilization comparison
     - Uptime Hours comparison
     - Downtime Hours comparison
     - Units Produced comparison
     - Work Orders Completed comparison
   - Each card shows:
     - Current vs Previous values
     - Difference (absolute)
     - Percentage change
     - Trend indicator (up/down arrow)
     - Status (improved/declined) with color coding

---

## Data Flow

### Real-Time Dashboard Flow

```
┌─────────────────────┐
│  User clicks        │
│  Machine            │
│  Utilization KPI    │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Livewire Component │
│  loads KPI data     │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────────────┐
│  RealTimeKPIService::               │
│  getMachineUtilization()            │
│                                     │
│  Filters:                           │
│  - factory_id = current factory     │
│  - start_time = TODAY               │ ← TODAY FILTER
│  - status IN ('Start','Completed',  │
│               'Hold')               │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│  Query work_orders table            │
│  (for scheduled utilization)        │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│  Query work_order_logs table        │
│  (for active utilization)           │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│  Calculate metrics:                 │
│  - Scheduled hours                  │
│  - Active hours                     │
│  - Hold hours                       │
│  - Idle hours                       │
│  - Utilization percentages          │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│  Cache result (5 minutes)           │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│  Return structured data             │
└──────────┬──────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│  Blade template renders:            │
│  - Summary cards                    │
│  - Per-machine table                │
│  - Visualizations                   │
└─────────────────────────────────────┘
```

### Key Database Tables

**1. `work_orders`** (Current state)
```sql
Fields:
- id, factory_id, machine_id
- start_time, end_time, status
- qty, ok_qtys, scrapped_qtys

Used for:
- Scheduled Utilization calculation
- Determining which machines have work TODAY
```

**2. `work_order_logs`** (Historical changes)
```sql
Fields:
- work_order_id
- status (status at this log entry)
- changed_at (timestamp of status change)
- ok_qtys, scrapped_qtys

Used for:
- Active Utilization calculation
- Precise tracking of 'Start' status duration
```

**3. `shifts`** (Factory configuration)
```sql
Fields:
- factory_id
- name, start_time, end_time

Used for:
- Calculating available hours per machine
- Example: 3 shifts × 8 hours = 24 hours available
```

---

## Use Cases

### Use Case 1: Morning Production Meeting

**Scenario:** Supervisor reviews utilization at start of shift

**Steps:**
1. Open KPI Dashboard
2. Select "Machine Utilization Rate"
3. View TODAY's utilization metrics

**Insights:**
```
Factory-Wide Summary (Today):
- Scheduled Utilization: 68.5%
- Active Utilization: 54.2%
- Gap: 14.3% (3.4 hours in hold)

Action: Investigate hold reasons to improve active utilization
```

### Use Case 2: Identifying Underutilized Machines

**Scenario:** Manager wants to find idle machines

**Steps:**
1. View per-machine breakdown table
2. Sort by Scheduled Utilization (ascending)
3. Identify machines < 40%

**Example Findings:**
```
Machine-005: 22% scheduled utilization (5.3h of 24h)
→ Only 2 work orders today
→ Action: Assign more work to this machine

Machine-003: 38% scheduled utilization (9.1h of 24h)
→ 3 work orders but low hours
→ Action: Review work order scheduling
```

### Use Case 3: Hold Time Analysis

**Scenario:** Operations manager notices gap between scheduled and active utilization

**Steps:**
1. Compare scheduled vs active utilization for each machine
2. Identify machines with large gaps
3. Investigate root causes

**Example:**
```
Machine-007:
- Scheduled: 75% (18h)
- Active: 48% (11.5h)
- Gap: 27% (6.5h in hold)

Hold reasons:
- Material shortage: 4 hours
- Quality check: 2.5 hours

Action: Improve material planning and QC process
```

---

## Interpretation Guidelines

### Scheduled Utilization Thresholds

| Range | Rating | Action |
|-------|--------|--------|
| **> 80%** | Excellent | Machine assignment is optimal |
| **60-80%** | Good | Acceptable utilization |
| **40-60%** | Moderate | Review work order planning |
| **< 40%** | Poor | Urgent attention needed - insufficient work |

### Active Utilization Thresholds

| Range | Rating | Action |
|-------|--------|--------|
| **> 70%** | Excellent | High productive time |
| **50-70%** | Good | Acceptable productive time |
| **30-50%** | Moderate | Investigate hold causes |
| **< 30%** | Poor | Excessive holds/delays |

### Gap Analysis

```
If Scheduled is 75% but Active is 45%:
→ Large gap (30%) indicates excessive hold time
→ Action: Investigate hold reasons (material, quality, maintenance)

If Scheduled is 85% and Active is 80%:
→ Small gap (5%) indicates efficient operations
→ Hold time is minimal and manageable
```

### Color Coding in Dashboard

**Green** (≥ 70%): Excellent utilization
- Machine is well-utilized
- Operations are efficient

**Yellow** (40-69%): Moderate utilization
- Room for improvement
- Monitor for trends

**Red** (< 40%): Poor utilization
- Requires immediate attention
- Investigate causes:
  - Insufficient work scheduled?
  - Long setup times?
  - Equipment issues?

---

## Summary

Machine Utilization Rate KPI provides critical visibility into how effectively production machines are being used. By tracking both Scheduled and Active Utilization, the system reveals not just whether machines have work assigned, but whether they're actually productive.

**Key Takeaways:**

1. **Dashboard shows TODAY only** - This is consistent across all KPIs
2. **Two utilization types** - Scheduled (factory view) vs Active (operator view)
3. **Gap analysis** - Difference reveals hold/delay issues
4. **Real-time monitoring** - Updated every 5 minutes with manual refresh option
5. **Actionable insights** - Color-coded indicators guide immediate action

**Next Steps:**
- Use Dashboard mode daily for operational monitoring
- Use Analytics mode for historical trend analysis
- Investigate machines with < 40% utilization
- Reduce gap between scheduled and active utilization
- Enable comparison mode to track improvements over time

---

## Version History

### Version 2.0 (October 21, 2025)
- ✅ Implemented Analytics Mode
- ✅ Added historical data analysis from kpi_machine_daily table
- ✅ Added period-over-period comparison feature
- ✅ Added daily breakdown table with pagination
- ✅ Added smart caching strategy
- ✅ Created machine-utilization-analytics.blade.php view
- ✅ Updated documentation

### Version 1.0 (October 17, 2025)
- ✅ Implemented Dashboard Mode
- ✅ Real-time utilization tracking
- ✅ Two-tier utilization calculation (Scheduled & Active)
- ✅ Per-machine breakdown
- ✅ Created machine-utilization.blade.php view

---

**Documentation Version:** 2.0
**Last Updated:** October 21, 2025
**Author:** ProdStream Development Team
