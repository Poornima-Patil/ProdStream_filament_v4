# Production Schedule Adherence KPI Documentation

## Overview
The Production Schedule Adherence KPI tracks how well work orders are being completed according to their scheduled end times. This KPI is crucial for identifying scheduling issues, bottlenecks, and areas where production planning needs improvement.

## KPI Details
- **Category**: Operational KPIs
- **Tier**: 1 (Real-Time & Historical)
- **Service**: `RealTimeKPIService` and `OperationalKPIService`
- **Page**: Accessible from KPI Analytics Dashboard
- **Icon**: Calendar/Schedule icon

## Modes

### Dashboard Mode (Real-Time)
Shows production schedule adherence for **TODAY** only:
- Work orders scheduled to end today and their completion status
- Work orders completed today but scheduled for other dates
- Currently running work orders at risk of missing deadlines

**Service Method**: `RealTimeKPIService::getProductionScheduleAdherence()`

### Analytics Mode (Historical)
Shows production schedule adherence for a **selected time period**:
- All work orders completed within the selected date range
- Categorized by on-time, early, and late completions
- No "at-risk" data (only applies to currently running work orders)
- **Supports period comparisons** when enabled

**Service Method**: `OperationalKPIService::getProductionScheduleAdherenceAnalytics()`

**Comparison Support**: When comparison is enabled, the system fetches data for both the primary period and comparison period, then calculates metrics like:
- Change in on-time rate
- Change in total completions
- Change in late count
- Percentage improvements/declines

## Data Structure

Both modes return the same data structure:

```php
[
    'summary' => [
        'scheduled_today' => int,           // Total WOs scheduled for the period
        'on_time_count' => int,             // WOs completed within ±15 minutes
        'early_count' => int,               // WOs completed >15 minutes early
        'late_count' => int,                // WOs completed >15 minutes late
        'on_time_rate' => float,            // Percentage (0-100)
        'avg_delay_minutes' => int,         // Average delay for late WOs only
        'early_from_future_count' => int,   // Dashboard only: WOs completed today, scheduled for future
        'late_from_past_count' => int,      // Dashboard only: WOs completed today, scheduled for past
        'total_completions_today' => int,   // Total completions in period
    ],
    'scheduled_today' => [
        'on_time' => array,   // Array of work order details
        'early' => array,     // Array of work order details
        'late' => array,      // Array of work order details
    ],
    'other_completions' => [
        'early_from_future' => array,  // Dashboard only
        'late_from_past' => array,     // Dashboard only
    ],
    'at_risk' => [
        'high_risk' => array,     // Dashboard only
        'medium_risk' => array,   // Dashboard only
        'on_track' => array,      // Dashboard only
    ],
    'updated_at' => string,
    'period_label' => string,    // Analytics only
    'date_range' => [            // Analytics only
        'start' => string,
        'end' => string,
    ],
]
```

## Work Order Data Structure

Each work order in the arrays includes:

```php
[
    'id' => int,
    'wo_number' => string,
    'machine_name' => string,
    'machine_asset_id' => string,
    'operator' => string,              // Full name or 'Unassigned'
    'part_number' => string,
    'scheduled_end' => string,         // Formatted date
    'actual_completion' => string,     // Formatted date
    'variance_minutes' => int,         // Positive = early, Negative = late
    'variance_display' => string,      // Human-readable variance

    // At-risk WOs only:
    'hours_remaining' => float,
    'progress_pct' => float,
    'qty_target' => int,
    'qty_produced' => int,
]
```

## Categorization Logic

### On-Time Definition
A work order is considered "on-time" if completed within **±15 minutes** of the scheduled end time.

### Early Definition
A work order is "early" if completed **more than 15 minutes before** the scheduled end time.

### Late Definition
A work order is "late" if completed **more than 15 minutes after** the scheduled end time.

### At-Risk Categories (Dashboard Mode Only)

**High Risk:**
- Less than 2 hours remaining until deadline
- Progress less than 70%

**Medium Risk:**
- Less than 4 hours remaining until deadline
- Progress less than 80%

**On Track:**
- All other running work orders with approaching deadlines (within 8 hours)

## Database Queries

### Dashboard Mode Query
```php
// Get WOs SCHEDULED to end TODAY
WorkOrder::where('factory_id', $factory->id)
    ->whereBetween('end_time', [$today, $endOfToday])
    ->whereIn('status', ['Completed', 'Closed'])
    ->with([
        'machine:id,name,assetId',
        'operator.user:id,first_name,last_name',
        'bom.purchaseOrder.partNumber:id,partnumber',
    ])
    ->get();

// Get actual completion times from logs
WorkOrderLog::whereIn('work_order_id', $woIds)
    ->where('status', 'Completed')
    ->get(['work_order_id', 'changed_at']);
```

### Analytics Mode Query
```php
// Get WOs COMPLETED in selected period
WorkOrder::where('factory_id', $factory->id)
    ->whereIn('status', ['Completed', 'Closed'])
    ->whereHas('workOrderLogs', function ($query) use ($startDate, $endDate) {
        $query->where('status', 'Completed')
            ->whereBetween('changed_at', [$startDate, $endDate]);
    })
    ->with([
        'machine:id,name,assetId',
        'operator.user:id,first_name,last_name',
        'bom.purchaseOrder.partNumber:id,partnumber',
    ])
    ->get();
```

## Relationships Used

The service relies on these WorkOrder model relationships:
- `machine` - BelongsTo Machine
- `operator` - BelongsTo Operator
- `operator.user` - Through Operator to User
- `bom` - BelongsTo BOM
- `bom.purchaseOrder` - Through BOM to PurchaseOrder
- `bom.purchaseOrder.partNumber` - Through PurchaseOrder to PartNumber
- `workOrderLogs` - HasMany WorkOrderLog

## Key Implementation Details

### Variance Calculation
```php
$varianceMinutes = $actualCompletion->diffInMinutes($scheduledEnd, false);
```
- Positive value = early (completed before scheduled end)
- Negative value = late (completed after scheduled end)

### Date Range Selection (Analytics Mode)
Uses `BaseKPIService::getDateRange()` which supports:
- `today`, `yesterday`
- `this_week`, `last_week`
- `this_month`, `last_month`
- `this_quarter`, `this_year`
- `7d`, `14d`, `30d`, `60d`, `90d`
- `custom` (with dateFrom and dateTo)

### Caching
- **Dashboard Mode**: 300 seconds (5 minutes)
- **Analytics Mode**: Varies by period (10 minutes to 2 hours)
- Manual refresh available via `refreshData()` method

## UI Components

### Main Page
`app/Filament/Admin/Pages/KPIAnalyticsDashboard.php`
- Handles mode switching (dashboard/analytics)
- Manages time period selection
- Provides pagination and filtering

### Blade Template
`resources/views/filament/admin/pages/production-schedule.blade.php`
- Displays summary metrics
- Shows categorized work orders in collapsible sections
- Includes pagination for large datasets

### Pagination Component
`resources/views/components/machine-table-pagination.blade.php`
- Supports both array and individual prop formats
- Provides responsive pagination controls
- Wire methods: `gotoScheduledTodayPage`, `gotoOtherCompletionsPage`, `gotoAtRiskPage`

## Pagination

Each category supports independent pagination:
- **Scheduled Today**: on_time, early, late
- **Other Completions**: early_from_future, late_from_past
- **At-Risk**: high_risk, medium_risk, on_track

Default: 10 items per page

## Period Comparison (Analytics Mode Only)

When comparison is enabled in Analytics mode, the system provides side-by-side analysis of two time periods.

### Comparison Data Structure

When comparison is enabled, the returned data includes additional fields:

```php
[
    // Primary period data (always present)
    'primary_period' => [
        'start_date' => string,
        'end_date' => string,
        'label' => string,
        'summary' => array,           // Same structure as regular summary
        'scheduled_today' => array,   // Same structure as regular scheduled_today
        'other_completions' => array,
        'at_risk' => array,
    ],

    // Comparison period data (only when enabled)
    'comparison_period' => [
        'start_date' => string,
        'end_date' => string,
        'label' => string,
        'summary' => array,
        'scheduled_today' => array,
        'other_completions' => array,
        'at_risk' => array,
    ],

    // Comparison analysis (only when enabled)
    'comparison_analysis' => [
        'total_completions' => [
            'current' => int,
            'previous' => int,
            'difference' => int,
            'percentage_change' => float,
            'trend' => 'up'|'down',
        ],
        'on_time_rate' => [
            'current' => float,
            'previous' => float,
            'difference' => float,
            'percentage_change' => float,
            'trend' => 'up'|'down',
            'status' => 'improved'|'declined',
        ],
        'on_time_count' => [...],
        'early_count' => [...],
        'late_count' => [...],
        'avg_delay_minutes' => [...],
    ],

    // Backward compatibility - top-level summary still present
    'summary' => array,
    'scheduled_today' => array,
    // ... other top-level fields
]
```

### Comparison Types

The system supports multiple comparison types:

1. **Previous Period** (`previous_period`): Same duration as primary period, immediately preceding it
   - Example: If primary is "This Week" (Oct 13-17), comparison is "Previous Week" (Oct 6-12)

2. **Previous Week** (`previous_week`): The week before the current week
   - Always compares to Sunday-Saturday of the previous week

3. **Previous Month** (`previous_month`): The month before the current month
   - Always compares to the full previous calendar month

4. **Previous Quarter** (`previous_quarter`): The quarter before the current quarter
   - Q1: Jan-Mar, Q2: Apr-Jun, Q3: Jul-Sep, Q4: Oct-Dec

5. **Same Period Last Year** (`previous_year`): Same dates, one year ago
   - Example: If primary is Oct 13-17 2025, comparison is Oct 13-17 2024

### Comparison Metrics

For each metric, the comparison analysis provides:

- **current**: Value from the primary period
- **previous**: Value from the comparison period
- **difference**: Absolute difference (current - previous)
- **percentage_change**: Percentage change from previous to current
  - Calculated as: `((current - previous) / previous) * 100`
  - Returns `0` if previous value is `0`
- **trend**: Direction of change (`'up'` or `'down'`)
- **status**: Whether change is positive (`'improved'`) or negative (`'declined'`)
  - For on_time_rate and on_time_count: Higher is better (improved)
  - For late_count and avg_delay_minutes: Lower is better (improved)

### Example Comparison Output

```php
'comparison_analysis' => [
    'on_time_rate' => [
        'current' => 85.50,      // This week: 85.5% on-time
        'previous' => 78.20,     // Last week: 78.2% on-time
        'difference' => 7.30,    // Improved by 7.3 percentage points
        'percentage_change' => 9.33,  // 9.33% increase
        'trend' => 'up',
        'status' => 'improved',  // Higher on-time rate is better
    ],
    'late_count' => [
        'current' => 8,
        'previous' => 15,
        'difference' => -7,      // 7 fewer late WOs
        'percentage_change' => -46.67,  // 46.67% decrease
        'trend' => 'down',
        'status' => 'improved',  // Fewer late WOs is better
    ],
]
```

### How to Enable Comparison

Set the following options when calling the analytics method:

```php
$service->getProductionScheduleAdherenceAnalytics([
    'time_period' => 'this_week',
    'enable_comparison' => true,
    'comparison_type' => 'previous_period',  // or any other supported type
]);
```

In the UI, users can:
1. Toggle the "Compare with previous period" switch
2. Select comparison type from the dropdown
3. View the comparison analysis alongside primary data

## Recent Enhancements (October 2025)

### Enhancement #1: Period Comparison Support
**Added**: October 17, 2025

**Description**: Implemented period comparison functionality for Analytics mode, allowing users to compare production schedule adherence metrics across different time periods.

**Implementation** (app/Services/KPI/OperationalKPIService.php:278-522):
- Refactored `getProductionScheduleAdherenceAnalytics()` to support comparison
- Created `fetchProductionScheduleAdherenceData()` helper method to fetch data for any period
- Created `calculateScheduleAdherenceComparison()` to analyze differences between periods
- Returns both primary and comparison period data when enabled
- Maintains backward compatibility by keeping top-level summary fields

**Comparison Metrics Calculated**:
- Total completions (difference and percentage change)
- On-time rate (with trend and status)
- On-time count (with trend and status)
- Early count (with trend)
- Late count (with trend and status - lower is better)
- Average delay minutes (with trend and status - lower is better)

**Usage**:
```php
$service->getProductionScheduleAdherenceAnalytics([
    'time_period' => 'this_week',
    'enable_comparison' => true,
    'comparison_type' => 'previous_week',  // Options: previous_period, previous_week, previous_month, previous_quarter, previous_year
]);
```

### Enhancement #2: Analytics Mode Comparison Display
**Added**: October 17, 2025

**Description**: Created dedicated analytics view template that properly displays comparison data with visual indicators (arrows, percentage changes) for Production Schedule Adherence KPI.

**Implementation**:

1. **New Analytics View Template** (resources/views/filament/admin/pages/production-schedule-analytics.blade.php):
   - Dedicated template for Analytics mode
   - Displays primary metrics with comparison indicators
   - Shows percentage changes with color-coded arrows
   - Includes detailed comparison analysis section
   - Maintains consistent styling with Machine Status Analytics

2. **Dashboard Logic Update** (resources/views/filament/admin/pages/kpi-analytics-dashboard.blade.php:1522-1529):
   ```blade
   {{-- Production Schedule Adherence KPI Content --}}
   @if($selectedKPI === 'production_schedule')
       @if($kpiMode === 'analytics')
           @include('filament.admin.pages.production-schedule-analytics')
       @else
           @include('filament.admin.pages.production-schedule')
       @endif
   @endif
   ```
   - Conditionally includes analytics template when in Analytics mode
   - Maintains backward compatibility with Dashboard mode

3. **Comparison Display Features**:
   - **Metric Cards with Comparison**: Each metric (Total Completions, On-Time Rate, Avg Delay, On-Time Count) shows:
     - Large current value
     - Comparison arrow (up/down) with percentage change
     - Color-coded based on whether change is positive or negative
     - Contextual interpretation (e.g., for delay, less is better)

   - **Status Breakdown with Comparison**: On-Time, Early, and Late counts each show:
     - Current count with icon
     - Comparison indicator with percentage change
     - Appropriate color coding

   - **Detailed Comparison Analysis Section**: When comparison is enabled, shows:
     - Side-by-side current vs previous values
     - Absolute difference and percentage change
     - Comparison period label and date range
     - Organized in cards for easy scanning

4. **Comparison Indicator Pattern**:
   ```blade
   @if(isset($comparisonAnalysis['on_time_rate']))
       @php
           $comparison = $comparisonAnalysis['on_time_rate'];
           $isPositive = $comparison['difference'] > 0;
       @endphp
       <div class="flex items-center gap-1 text-sm font-medium
            {{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
           @if($isPositive)
               <x-heroicon-s-arrow-up class="w-4 h-4" />
           @else
               <x-heroicon-s-arrow-down class="w-4 h-4" />
           @endif
           <span>{{ abs($comparison['percentage_change']) }}%</span>
       </div>
   @endif
   ```

5. **Context-Aware Color Coding**:
   - **On-Time Rate, On-Time Count**: Higher is better (green for up, red for down)
   - **Late Count, Avg Delay**: Lower is better (green for down, red for up)
   - Ensures visual indicators match business logic

**Visual Elements**:
- Green arrow up / Red arrow down for comparison indicators
- Large metric cards with icons (clipboard, check-circle, clock, document-check)
- Breakdown cards with color-coded backgrounds (green for on-time, blue for early, red for late)
- Comparison summary section with detailed side-by-side analysis
- Date range and period labels for context

**User Experience**:
- Clear visual hierarchy with large primary metrics
- At-a-glance comparison indicators
- Detailed breakdown available in comparison section
- Consistent with Machine Status Analytics comparison display
- Responsive design for different screen sizes

## Recent Bug Fixes (October 2025)

### Fix #1: Incorrect Relationships in Analytics Mode
**Issue**: When selecting "This Week" or other time periods in analytics mode, the system threw an error: "Undefined array key 'summary'"

**Root Cause**: The `OperationalKPIService::getProductionScheduleAdherenceAnalytics()` method was attempting to load non-existent relationships:
- Used `partMaster` instead of `bom.purchaseOrder.partNumber`
- Used `assignedUser` instead of `operator.user`
- Referenced `asset_id` instead of `assetId`

**Solution** (app/Services/KPI/OperationalKPIService.php:293-343):
```php
// Corrected eager loading
->with([
    'machine:id,name,assetId',
    'operator.user:id,first_name,last_name',
    'bom.purchaseOrder.partNumber:id,partnumber',
])

// Corrected property access
$operatorName = $wo->operator?->user
    ? "{$wo->operator->user->first_name} {$wo->operator->user->last_name}"
    : 'Unassigned';

$partNumber = $wo->bom?->purchaseOrder?->partNumber?->partnumber ?? 'N/A';
```

### Fix #2: Pagination Component Props Mismatch
**Issue**: Error "Undefined variable $pagination" when rendering pagination controls

**Root Cause**: The pagination component expected a single `$pagination` array prop, but templates were passing individual props like `:currentPage`, `:totalPages`, etc.

**Solution** (resources/views/components/machine-table-pagination.blade.php:1-21):
```blade
@props([
    'pagination' => null,
    'currentPage' => null,
    'totalPages' => null,
    'total' => null,
    'from' => null,
    'to' => null,
    'status',
    'wireMethod' => 'gotoPage'
])

@php
    // Support both array and individual props
    if ($pagination) {
        $currentPage = $pagination['current_page'];
        $totalPages = $pagination['total_pages'];
        $total = $pagination['total'];
        $from = $pagination['from'];
        $to = $pagination['to'];
    }
@endphp
```

The component now supports both calling conventions:
```blade
<!-- Array format -->
<x-machine-table-pagination :pagination="$paginationArray" status="running" />

<!-- Individual props format -->
<x-machine-table-pagination
    :currentPage="1"
    :totalPages="5"
    :total="50"
    :from="1"
    :to="10"
    status="running"
/>
```

## Performance Optimization Tips

1. **Use Analytics Mode for Historical Analysis**: Dashboard mode is optimized for real-time data, not historical reporting
2. **Limit Date Ranges**: Queries over 90 days may be slow without proper indexing
3. **Enable Caching**: Don't skip cache unless absolutely necessary (manual refresh)
4. **Add Database Indexes**:
   ```sql
   -- On work_orders table
   INDEX idx_factory_end_time (factory_id, end_time, status)

   -- On work_order_logs table
   INDEX idx_completion_logs (status, changed_at, work_order_id)
   ```

## Troubleshooting

### "All data shows zero" in Analytics Mode
**Possible Causes**:
1. No work orders completed in the selected time period
2. Work orders don't have `end_time` set
3. Work orders don't have completion logs in `work_order_logs` table

**Debugging**:
```php
// Check for completed work orders in period
$count = WorkOrderLog::where('status', 'Completed')
    ->whereBetween('changed_at', [$startDate, $endDate])
    ->count();
```

### "Undefined array key" Errors
**Possible Causes**:
1. Service method returning wrong data structure
2. Missing relationships on WorkOrder model
3. Blade template expecting different data format

**Solution**: Verify the service method returns all required keys as documented in "Data Structure" section above.

## Future Enhancements

1. **Trend Analysis**: Add weekly/monthly trend charts
2. **Root Cause Tracking**: Link delays to hold reasons
3. **Machine-Level Breakdown**: Show adherence by machine
4. **Operator Performance**: Show adherence by operator
5. **Export Functionality**: CSV/Excel export of adherence data
6. **Notifications**: Alert when on-time rate drops below threshold
7. **Predictive Analytics**: Predict which WOs are likely to be late

## Related Documentation
- [KPI Dashboard Design](./KPI_DASHBOARD_DESIGN.md)
- [KPI Classification Guide](./KPI_CLASSIFICATION_GUIDE.md)
- [KPI Optimization Implementation Plan](./KPI_OPTIMIZATION_IMPLEMENTATION_PLAN.md)
