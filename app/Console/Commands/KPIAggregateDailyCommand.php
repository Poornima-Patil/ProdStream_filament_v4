<?php

namespace App\Console\Commands;

use App\Models\Factory;
use App\Models\KPI\MachineDaily;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class KPIAggregateDailyCommand extends Command
{
    protected $signature = 'kpi:aggregate-daily
                            {date? : The date to aggregate (YYYY-MM-DD format, defaults to yesterday)}
                            {--to= : End date for range aggregation (YYYY-MM-DD format)}
                            {--factory= : Specific factory ID to aggregate (defaults to all factories)}
                            {--force : Force re-aggregation even if data already exists}';

    protected $description = 'Aggregate daily Machine Status KPI metrics from work order data';

    public function handle(): int
    {
        $startDate = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : Carbon::yesterday();

        $endDate = $this->option('to')
            ? Carbon::parse($this->option('to'))
            : $startDate->copy();

        $factoryId = $this->option('factory');
        $force = $this->option('force');

        // Validation
        if ($startDate->isFuture()) {
            $this->error('Cannot aggregate future dates');

            return self::FAILURE;
        }

        if ($endDate->lt($startDate)) {
            $this->error('End date must be after start date');

            return self::FAILURE;
        }

        // Get factories to process
        $factories = $factoryId
            ? Factory::where('id', $factoryId)->get()
            : Factory::all();

        if ($factories->isEmpty()) {
            $this->error($factoryId ? "Factory with ID {$factoryId} not found" : 'No factories found');

            return self::FAILURE;
        }

        // Process date range
        $this->info("Processing date range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info('Factories: '.($factoryId ? "Factory {$factoryId}" : 'All factories'));

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $this->info("Total days to process: {$totalDays}");
        $this->newLine();

        $stats = [
            'total_days' => 0,
            'total_factories' => $factories->count(),
            'total_machines' => 0,
            'total_records' => 0,
            'errors' => 0,
        ];

        foreach ($factories as $factory) {
            $this->info("Processing Factory: {$factory->name} (ID: {$factory->id})");

            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $this->processDayForFactory($factory, $currentDate, $force, $stats);
                $currentDate->addDay();
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('=== Aggregation Summary ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Days Processed', $stats['total_days']],
                ['Factories', $stats['total_factories']],
                ['Unique Machines', $stats['total_machines']],
                ['Records Created/Updated', $stats['total_records']],
                ['Errors', $stats['errors']],
            ]
        );

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function processDayForFactory(Factory $factory, Carbon $date, bool $force, array &$stats): void
    {
        $dateString = $date->toDateString();

        // Check if data already exists
        if (! $force) {
            $existingCount = MachineDaily::where('factory_id', $factory->id)
                ->whereDate('summary_date', $date)
                ->count();

            if ($existingCount > 0) {
                $this->line("  ⏭️  {$dateString}: Skipping (data exists, use --force to re-aggregate)");

                return;
            }
        }

        // Get all work orders for this date
        $workOrders = WorkOrder::where('factory_id', $factory->id)
            ->whereDate('start_time', $date)
            ->whereIn('status', ['Start', 'Completed', 'Hold'])
            ->with('machine:id,name,assetId')
            ->get();

        if ($workOrders->isEmpty()) {
            $this->line("  ℹ️  {$dateString}: No work orders found");

            return;
        }

        // Group by machine
        $workOrdersByMachine = $workOrders->groupBy('machine_id');
        $machinesProcessed = 0;

        // Get available hours for this factory
        $availableHours = $this->getAvailableHours($factory);

        foreach ($workOrdersByMachine as $machineId => $machineWorkOrders) {
            try {
                $this->aggregateMachineDay($factory, $machineId, $date, $machineWorkOrders, $availableHours);
                $machinesProcessed++;
                $stats['total_records']++;
            } catch (\Exception $e) {
                $this->error("  ❌ Error processing machine {$machineId} on {$dateString}: {$e->getMessage()}");
                Log::error('KPI aggregation error', [
                    'factory_id' => $factory->id,
                    'machine_id' => $machineId,
                    'date' => $dateString,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $stats['errors']++;
            }
        }

        $stats['total_days']++;
        $stats['total_machines'] = max($stats['total_machines'], $workOrdersByMachine->count());

        $this->line("  ✅ {$dateString}: Processed {$machinesProcessed} machines");
    }

    protected function aggregateMachineDay(
        Factory $factory,
        int $machineId,
        Carbon $date,
        $workOrders,
        float $availableHours
    ): void {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        // Calculate metrics
        $utilizationData = $this->calculateUtilization($workOrders, $dayStart, $dayEnd, $availableHours);
        $activeUtilizationData = $this->calculateActiveUtilization($workOrders, $dayStart, $dayEnd, $availableHours);
        $productionData = $this->calculateProduction($workOrders);
        $qualityData = $this->calculateQuality($workOrders);

        // Upsert into database
        MachineDaily::updateOrCreate(
            [
                'factory_id' => $factory->id,
                'machine_id' => $machineId,
                'summary_date' => $date->toDateString(),
            ],
            [
                'utilization_rate' => $utilizationData['utilization_rate'],
                'active_utilization_rate' => $activeUtilizationData['active_utilization_rate'],
                'uptime_hours' => $utilizationData['uptime_hours'],
                'downtime_hours' => $utilizationData['downtime_hours'],
                'planned_downtime_hours' => 0,
                'unplanned_downtime_hours' => $utilizationData['downtime_hours'],
                'units_produced' => $productionData['units_produced'],
                'work_orders_completed' => $productionData['work_orders_completed'],
                'average_cycle_time' => $productionData['average_cycle_time'],
                'quality_rate' => $qualityData['quality_rate'],
                'scrap_rate' => $qualityData['scrap_rate'],
                'first_pass_yield' => $qualityData['first_pass_yield'],
                'calculated_at' => now(),
            ]
        );
    }

    protected function calculateUtilization($workOrders, Carbon $dayStart, Carbon $dayEnd, float $availableHours): array
    {
        $totalProductionSeconds = 0;

        // Only count Start and Completed work orders for uptime
        $productiveWorkOrders = $workOrders->whereIn('status', ['Start', 'Completed']);

        foreach ($productiveWorkOrders as $wo) {
            $start = Carbon::parse($wo->start_time);
            $end = Carbon::parse($wo->end_time);

            // Only count time within the target date
            $effectiveStart = $start->lt($dayStart) ? $dayStart->copy() : $start->copy();
            $effectiveEnd = $end->gt($dayEnd) ? $dayEnd->copy() : $end->copy();

            if ($effectiveEnd->gt($effectiveStart)) {
                $totalProductionSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
            }
        }

        $uptimeHours = round($totalProductionSeconds / 3600, 2);
        $downtimeHours = round($availableHours - $uptimeHours, 2);

        $utilizationRate = $availableHours > 0
            ? round(min(($uptimeHours / $availableHours) * 100, 100), 2)
            : 0.00;

        return [
            'utilization_rate' => $utilizationRate,
            'uptime_hours' => $uptimeHours,
            'downtime_hours' => max(0, $downtimeHours),
        ];
    }

    protected function calculateActiveUtilization($workOrders, Carbon $dayStart, Carbon $dayEnd, float $availableHours): array
    {
        $totalActiveSeconds = 0;

        foreach ($workOrders as $wo) {
            // Get all status logs for this work order
            $logs = WorkOrderLog::where('work_order_id', $wo->id)
                ->orderBy('changed_at', 'asc')
                ->get(['status', 'changed_at']);

            if ($logs->isEmpty()) {
                continue;
            }

            $previousLog = null;
            foreach ($logs as $log) {
                if ($previousLog && $previousLog->status === 'Start') {
                    // Calculate time in 'Start' status
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

            // Handle if work order is still in 'Start' status
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

        $activeHours = round($totalActiveSeconds / 3600, 2);

        $activeUtilizationRate = $availableHours > 0
            ? round(min(($activeHours / $availableHours) * 100, 100), 2)
            : 0.00;

        return [
            'active_utilization_rate' => $activeUtilizationRate,
            'active_hours' => $activeHours,
        ];
    }

    protected function calculateProduction($workOrders): array
    {
        $unitsProduced = $workOrders->sum('ok_qtys');
        $workOrdersCompleted = $workOrders->where('status', 'Completed')->count();

        // Calculate average cycle time
        $totalProductionMinutes = 0;
        $totalUnitsForCycleTime = 0;

        foreach ($workOrders as $wo) {
            if ($wo->ok_qtys > 0) {
                $start = Carbon::parse($wo->start_time);
                $end = Carbon::parse($wo->end_time);
                $minutes = $start->diffInMinutes($end);

                $totalProductionMinutes += $minutes;
                $totalUnitsForCycleTime += $wo->ok_qtys;
            }
        }

        $averageCycleTime = $totalUnitsForCycleTime > 0
            ? round($totalProductionMinutes / $totalUnitsForCycleTime, 2)
            : 0.00;

        return [
            'units_produced' => $unitsProduced,
            'work_orders_completed' => $workOrdersCompleted,
            'average_cycle_time' => $averageCycleTime,
        ];
    }

    protected function calculateQuality($workOrders): array
    {
        $okUnits = $workOrders->sum('ok_qtys');
        $scrappedUnits = $workOrders->sum('scrapped_qtys');
        $totalUnits = $okUnits + $scrappedUnits;

        $qualityRate = $totalUnits > 0
            ? round(($okUnits / $totalUnits) * 100, 2)
            : 100.00;

        $scrapRate = $totalUnits > 0
            ? round(($scrappedUnits / $totalUnits) * 100, 2)
            : 0.00;

        $firstPassYield = $qualityRate;

        return [
            'quality_rate' => $qualityRate,
            'scrap_rate' => $scrapRate,
            'first_pass_yield' => $firstPassYield,
        ];
    }

    protected function getAvailableHours(Factory $factory): float
    {
        static $cache = [];

        if (isset($cache[$factory->id])) {
            return $cache[$factory->id];
        }

        $shifts = Shift::where('factory_id', $factory->id)->get();

        if ($shifts->isEmpty()) {
            $cache[$factory->id] = 24.0;

            return 24.0;
        }

        $totalHours = 0;

        foreach ($shifts as $shift) {
            $start = Carbon::createFromTimeString($shift->start_time);
            $end = Carbon::createFromTimeString($shift->end_time);

            if ($end->lt($start)) {
                $end->addDay();
            }

            $totalHours += $end->diffInHours($start, true);
        }

        $cache[$factory->id] = $totalHours;

        return $totalHours;
    }
}
