# Work Order Simulation Script

This script simulates the execution of work orders for a given date range. It processes all work orders that have an `end_time` on or before the specified date.

## Features

The script simulates a realistic work order execution flow:

1. **Start**: Work order transitions from "Assigned" to "Start" at the scheduled `start_time`
2. **Material Batch**: Assigns a unique batch number to the work order
3. **Mid-Process Hold**: Places work order on "Hold" at halfway point with partial quantities
4. **Resume**: Resumes work order from "Hold" back to "Start" 
5. **Complete**: Completes work order at scheduled `end_time` with remaining quantities
6. **Quantities**: Distributes OK and KO quantities realistically across the process
7. **Logging**: Creates proper work order logs for each status change

## Usage

```bash
# Simulate all work orders ending by a specific date for a specific factory
php artisan workorder:simulate 2024-12-31 1

# Example: Simulate work orders ending by today for factory ID 2
php artisan workorder:simulate $(date +%Y-%m-%d) 2

# Example: Simulate work orders ending by next week for factory ID 3
php artisan workorder:simulate 2024-09-01 3
```

## Arguments

- `date` (required): Date in YYYY-MM-DD format - simulates all work orders with end_time on or before this date
- `factory_id` (required): Numeric factory ID to filter work orders for a specific factory

## What it does

### Work Order Processing
- Finds all work orders with `status = 'Assigned'` and `end_time <= specified_date` for the specified factory
- Only processes work orders that have:
  - Valid `start_time` and `end_time`
  - Assigned `operator_id`
  - Valid machine assignment
  - Matching `factory_id`

### Simulation Flow
1. **Start Phase** (at `start_time`):
   - Changes status from "Assigned" to "Start"
   - Generates unique batch number (format: `BATCH-YYYYMMDD-MACHINE-XXX`)
   - Creates work order log entry

2. **Hold Phase** (at 50% of duration):
   - Changes status to "Hold" 
   - Creates quantity entry with ~60% of total production
   - Simulates realistic yield (85-98% OK rate)
   - Creates work order log entry with hold reason

3. **Resume Phase** (10 minutes after hold):
   - Changes status back to "Start"
   - Creates work order log entry

4. **Complete Phase** (at `end_time`):
   - Changes status to "Completed"
   - Creates final quantity entry with remaining production
   - Updates work order totals (ok_qtys, scrapped_qtys)
   - Creates final work order log entry with FPY calculation

### Quantity Distribution
- **Total Quantity**: As specified in work order `qty` field
- **Yield Rate**: Randomly assigned between 85-98% (realistic manufacturing)
- **First Half**: ~60% of total quantity processed during first phase
- **Second Half**: Remaining 40% processed during completion phase
- **OK/KO Split**: Applied proportionally to maintain overall yield rate

### Batch Number Generation
Format: `BATCH-YYYYMMDD-MACHINE-XXX`
- Date: Start date of work order
- Machine: Last 3 characters of machine asset ID
- Random: 3-digit random number for uniqueness

## Safety Features

- **Transaction Wrapped**: Each work order processed in database transaction
- **Factory Isolation**: All queries and operations are scoped to the specified factory
- **Error Handling**: Continues processing other work orders if one fails
- **Progress Bar**: Shows processing progress
- **Comprehensive Logging**: Errors and operations logged to Laravel log files with factory context
- **Validation**: Only processes properly configured work orders within the specified factory
- **Operator Validation**: Ensures operators belong to the correct factory context
- **Audit Trail**: Complete logging of all simulation activities for compliance

## Database Updates

### Work Orders Table
- `status`: Updated through the flow (Start → Hold → Start → Completed)
- `material_batch`: Set to generated batch number
- `ok_qtys`: Updated with total OK quantities
- `scrapped_qtys`: Updated with total KO quantities

### Work Order Logs Table
- Creates 4 log entries per work order:
  1. Start status log
  2. Hold status log (with quantities and FPY)
  3. Resume start status log
  4. Completed status log (with final quantities and FPY)

### Work Order Quantities Table
- Creates 2 quantity entries per work order:
  1. First phase quantities (during hold)
  2. Final phase quantities (during completion)

## Output Example

```
Simulating work order execution for Factory: Factory Name (ID: 1)
Processing work orders ending by: 2024-12-31 23:59:59
Found 45 work orders to simulate.
 45/45 [████████████████████████████] 100%

Simulation completed!
Successfully processed: 44
Errors encountered: 1
```

## Requirements

- Work orders must have `status = 'Assigned'`
- Work orders must have valid `start_time` and `end_time`
- Work orders must have assigned `operator_id`
- Work orders must belong to the specified `factory_id`
- Work orders should have associated machine for batch number generation
- Hold reasons and scrap reasons should exist in database (assumes ID 1 exists)
- Factory with specified ID must exist in the database

## Notes

- The script uses `Carbon::setTestNow()` to simulate timestamps accurately
- FPY (First Pass Yield) is calculated for Hold and Completed statuses
- The script respects multi-tenancy and processes work orders only for the specified factory
- All database operations are scoped to the factory_id for complete isolation
- Operators are validated to ensure they belong to the correct factory context
- Realistic production simulation with variable yield rates
- Safe error handling ensures partial failures don't stop the entire process
- Comprehensive audit logging tracks all operations with factory context
- User assignments prioritize factory-specific operators and admins
