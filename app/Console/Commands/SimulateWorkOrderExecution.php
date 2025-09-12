<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WorkOrder;
use App\Models\WorkOrderQuantity;
use App\Models\WorkOrderLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimulateWorkOrderExecution extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workorder:simulate {date : Date to simulate work orders until (YYYY-MM-DD format)} {factory_id : Factory ID to filter work orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate work order execution for all work orders that should be completed by the given date for a specific factory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateInput = $this->argument('date');
        $factoryId = $this->argument('factory_id');

        // Validate factory_id is numeric
        if (!is_numeric($factoryId)) {
            $this->error('Factory ID must be a valid number.');
            return 1;
        }

        // Check if factory exists
        $factory = \App\Models\Factory::find($factoryId);
        if (!$factory) {
            $this->error("Factory with ID {$factoryId} not found.");
            return 1;
        }

        try {
            $targetDate = Carbon::createFromFormat('Y-m-d', $dateInput, config('app.timezone'))->endOfDay();
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use YYYY-MM-DD format.');
            return 1;
        }

        $this->info("Simulating work order execution for Factory: {$factory->name} (ID: {$factoryId})");
        $this->info("Processing work orders ending by: {$targetDate->format('Y-m-d H:i:s')}");
        $this->info("Using timezone: " . config('app.timezone'));

        // Get all work orders that should be completed by the target date for the specific factory
        $workOrders = WorkOrder::where('end_time', '<=', $targetDate)
            ->where('factory_id', $factoryId) // Filter by factory
            ->whereIn('status', ['Assigned']) // Only process assigned work orders
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->whereNotNull('operator_id')
            ->with(['operator', 'machine'])
            ->get();

        if ($workOrders->isEmpty()) {
            $this->info('No work orders found to simulate.');
            return 0;
        }

        $this->info("Found {$workOrders->count()} work orders to simulate.");

        $progressBar = $this->output->createProgressBar($workOrders->count());
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;
        $statusCounts = ['Start' => 0, 'Hold' => 0, 'Completed' => 0];

        foreach ($workOrders as $workOrder) {
            try {
                // Log the start of processing for this work order
                Log::info("Starting simulation for Work Order", [
                    'work_order_id' => $workOrder->id,
                    'work_order_unique_id' => $workOrder->unique_id,
                    'factory_id' => $workOrder->factory_id,
                    'start_time' => $workOrder->start_time,
                    'end_time' => $workOrder->end_time,
                    'operator_id' => $workOrder->operator_id,
                    'machine_id' => $workOrder->machine_id,
                ]);

                $this->simulateWorkOrderExecution($workOrder);
                $successCount++;

                // Refresh work order to get updated status and count it
                $workOrder->refresh();
                $statusCounts[$workOrder->status]++;

                // Log successful completion
                Log::info("Successfully simulated Work Order", [
                    'work_order_id' => $workOrder->id,
                    'work_order_unique_id' => $workOrder->unique_id,
                    'factory_id' => $workOrder->factory_id,
                    'final_status' => $workOrder->status,
                ]);
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error processing Work Order {$workOrder->unique_id} (Factory {$workOrder->factory_id}): " . $e->getMessage());
                Log::error("Work Order Simulation Error", [
                    'work_order_id' => $workOrder->id,
                    'work_order_unique_id' => $workOrder->unique_id,
                    'factory_id' => $workOrder->factory_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errorCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Simulation completed!");
        $this->info("Successfully processed: {$successCount}");
        $this->info("Final Status Distribution:");
        $this->info("  - Start: {$statusCounts['Start']}");
        $this->info("  - Hold: {$statusCounts['Hold']}");
        $this->info("  - Completed: {$statusCounts['Completed']}");
        if ($errorCount > 0) {
            $this->warn("Errors encountered: {$errorCount}");
        }

        // Log summary for audit purposes
        Log::info("Work Order Simulation Summary", [
            'factory_id' => $factoryId,
            'factory_name' => $factory->name,
            'target_date' => $targetDate->format('Y-m-d'),
            'total_work_orders_found' => $workOrders->count(),
            'successfully_processed' => $successCount,
            'errors_encountered' => $errorCount,
            'status_distribution' => $statusCounts,
            'command_execution_time' => now()->toDateTimeString(),
        ]);

        return 0;
    }

    /**
     * Simulate the execution of a single work order
     */
    private function simulateWorkOrderExecution(WorkOrder $workOrder)
    {
        DB::transaction(function () use ($workOrder) {
            // Ensure consistent timezone handling
            $appTimezone = config('app.timezone');
            $startTime = Carbon::parse($workOrder->start_time)->setTimezone($appTimezone);
            $endTime = Carbon::parse($workOrder->end_time)->setTimezone($appTimezone);
            $totalDuration = $startTime->diffInMinutes($endTime);

            // Generate batch number
            $batchNumber = $this->generateBatchNumber($workOrder);

            // Get operator user ID
            $operatorUserId = $this->getOperatorUserId($workOrder);

            // Randomly determine final status: 30% Start, 30% Hold, 40% Completed
            $finalStatus = $this->getRandomFinalStatus();

            // Temporarily disable all WorkOrder model events to prevent automatic log creation
            WorkOrder::withoutEvents(function () use ($workOrder, $batchNumber, $startTime, $endTime, $totalDuration, $operatorUserId, $finalStatus) {

                // Step 1: Start the work order (all work orders start)
                $workOrder->update([
                    'status' => 'Start',
                    'material_batch' => $batchNumber,
                    'ok_qtys' => 0,
                    'scrapped_qtys' => 0
                ]);
                $startLog = $this->createManualWorkOrderLog($workOrder, 'Start', $startTime, $operatorUserId, 0, 0);

                if ($finalStatus === 'Start') {
                    // Work order remains in Start status - no further processing
                    return;
                }

                // For Hold and Completed status, determine when to put on hold
                $holdPercentages = [10, 30, 60, 90]; // Randomize hold timing
                $holdPercentage = $holdPercentages[array_rand($holdPercentages)];
                $holdTime = $startTime->copy()->addMinutes($totalDuration * ($holdPercentage / 100));

                // Calculate quantities based on hold percentage
                $quantities = $this->calculateQuantitiesForHoldPercentage($workOrder->qty, $holdPercentage);

                // Step 2: Put work order on Hold and UPDATE quantities
                $workOrder->update([
                    'status' => 'Hold',
                    'ok_qtys' => $quantities['hold_ok'],
                    'scrapped_qtys' => $quantities['hold_ko']
                ]);
                $holdLog = $this->createManualWorkOrderLog($workOrder, 'Hold', $holdTime, $operatorUserId, $quantities['hold_ok'], $quantities['hold_ko']);

                // Create quantity entry for hold
                $this->createQuantityEntry($workOrder, $holdLog, [
                    'ok' => $quantities['hold_ok'],
                    'ko' => $quantities['hold_ko']
                ]);

                if ($finalStatus === 'Hold') {
                    // Work order remains in Hold status - quantities already updated above
                    return;
                }

                // Step 3: Resume from Hold to Start (only if completing)
                $resumeTime = $holdTime->copy()->addMinutes(rand(5, 20)); // Random break time
                $workOrder->update([
                    'status' => 'Start',
                    'ok_qtys' => $quantities['hold_ok'], // Keep hold quantities
                    'scrapped_qtys' => $quantities['hold_ko']
                ]);
                $resumeLog = $this->createManualWorkOrderLog($workOrder, 'Start', $resumeTime, $operatorUserId, $quantities['hold_ok'], $quantities['hold_ko']);

                // Step 4: Complete the work order with FINAL quantities
                $workOrder->update([
                    'status' => 'Completed',
                    'ok_qtys' => $quantities['total_ok'],
                    'scrapped_qtys' => $quantities['total_ko']
                ]);
                $completeLog = $this->createManualWorkOrderLog($workOrder, 'Completed', $endTime, $operatorUserId, $quantities['total_ok'], $quantities['total_ko']);

                // Create remaining quantity entry for completion
                $this->createQuantityEntry($workOrder, $completeLog, [
                    'ok' => $quantities['remaining_ok'],
                    'ko' => $quantities['remaining_ko']
                ]);
            });
        });
    }
    /**
     * Get operator user ID with proper fallback
     */
    private function getOperatorUserId(WorkOrder $workOrder): int
    {
        // First try to get operator's user ID
        if ($workOrder->operator && $workOrder->operator->user) {
            return $workOrder->operator->user_id;
        }

        // Fallback to factory admin for this factory
        $factoryAdmin = \App\Models\User::where('factory_id', $workOrder->factory_id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Factory Admin');
            })->first();

        if ($factoryAdmin) {
            return $factoryAdmin->id;
        }

        // Final fallback to super admin
        $superAdmin = \App\Models\User::role('Super Admin')->first();
        return $superAdmin?->id ?? 1;
    }

    /**
     * Randomly determine the final status for work order simulation
     */
    private function getRandomFinalStatus(): string
    {
        $random = rand(1, 100);

        if ($random <= 30) {
            return 'Start';  // 30% chance
        } elseif ($random <= 60) {
            return 'Hold';   // 30% chance
        } else {
            return 'Completed'; // 40% chance
        }
    }

    /**
     * Calculate quantities based on hold percentage
     */
    private function calculateQuantitiesForHoldPercentage(int $totalQty, int $holdPercentage): array
    {
        // Simulate realistic production with some defects
        $yieldRate = rand(85, 98) / 100; // 85-98% yield rate
        $totalOk = (int) floor($totalQty * $yieldRate);
        $totalKo = $totalQty - $totalOk;

        // Calculate quantities produced until hold point
        $holdQty = (int) floor($totalQty * ($holdPercentage / 100));
        $holdOk = (int) floor($holdQty * $yieldRate);
        $holdKo = $holdQty - $holdOk;

        // Remaining quantities after hold (for completion)
        $remainingOk = $totalOk - $holdOk;
        $remainingKo = $totalKo - $holdKo;

        // Ensure non-negative values
        $remainingOk = max(0, $remainingOk);
        $remainingKo = max(0, $remainingKo);

        return [
            'hold_ok' => $holdOk,
            'hold_ko' => $holdKo,
            'remaining_ok' => $remainingOk,
            'remaining_ko' => $remainingKo,
            'total_ok' => $totalOk,
            'total_ko' => $totalKo,
        ];
    }

    /**
     * Create manual work order log with specific timing and user
     */
    private function createManualWorkOrderLog(WorkOrder $workOrder, string $status, Carbon $timestamp, int $userId, int $okQtys, int $scrappedQtys): WorkOrderLog
    {
        // Calculate FPY for Hold and Completed statuses
        $fpy = 0;
        if (in_array($status, ['Hold', 'Completed']) && ($okQtys + $scrappedQtys) > 0) {
            $fpy = ($okQtys / ($okQtys + $scrappedQtys)) * 100;
        }

        // Ensure timestamp is in the correct timezone
        $timestampInAppTimezone = $timestamp->setTimezone(config('app.timezone'));
        
        // Create log entry directly in database to avoid any model events
        $logId = DB::table('work_order_logs')->insertGetId([
            'work_order_id' => $workOrder->id,
            'status' => $status,
            'changed_at' => $timestampInAppTimezone->toDateTimeString(),
            'user_id' => $userId,
            'ok_qtys' => $okQtys,
            'scrapped_qtys' => $scrappedQtys,
            'remaining' => $workOrder->qty - ($okQtys + $scrappedQtys),
            'scrapped_reason_id' => $scrappedQtys > 0 ? 1 : null,
            'hold_reason_id' => $status === 'Hold' ? 1 : null,
            'fpy' => $fpy,
            'created_at' => $timestampInAppTimezone->toDateTimeString(),
            'updated_at' => $timestampInAppTimezone->toDateTimeString(),
        ]);

        // Return the log model instance
        return WorkOrderLog::find($logId);
    }

    /**
     * Create quantity entry for work order
     */
    private function createQuantityEntry(WorkOrder $workOrder, WorkOrderLog $log, array $quantities)
    {
        // Ensure the log belongs to the same work order and factory
        if ($log->work_order_id !== $workOrder->id) {
            throw new \Exception("Log ID {$log->id} does not belong to Work Order {$workOrder->id}");
        }

        // Ensure timestamp consistency with app timezone
        $logTimestamp = Carbon::parse($log->changed_at)->setTimezone(config('app.timezone'));
        
        // Use direct database insertion to maintain timestamp consistency
        DB::table('work_order_quantities')->insert([
            'work_order_id' => $workOrder->id,
            'work_order_log_id' => $log->id,
            'ok_quantity' => $quantities['ok'],
            'scrapped_quantity' => $quantities['ko'],
            'reason_id' => $quantities['ko'] > 0 ? 1 : null, // Assuming reason ID 1 exists
            'created_at' => $logTimestamp->toDateTimeString(),
            'updated_at' => $logTimestamp->toDateTimeString(),
        ]);

        // Log the quantity creation for audit purposes
        Log::info("Quantity entry created for Work Order simulation", [
            'work_order_id' => $workOrder->id,
            'work_order_unique_id' => $workOrder->unique_id,
            'factory_id' => $workOrder->factory_id,
            'log_id' => $log->id,
            'status' => $log->status,
            'ok_quantity' => $quantities['ok'],
            'scrapped_quantity' => $quantities['ko'],
        ]);
    }

    /**
     * Generate a batch number for the work order
     */
    private function generateBatchNumber(WorkOrder $workOrder): string
    {
        $date = Carbon::parse($workOrder->start_time)->setTimezone(config('app.timezone'))->format('Ymd');
        $machineCode = $workOrder->machine ? substr($workOrder->machine->assetId, -3) : '001';
        $random = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        return "BATCH-{$date}-{$machineCode}-{$random}";
    }

    /**
     * Calculate OK and KO quantities for the work order
     */
    private function calculateQuantities(int $totalQty): array
    {
        // Simulate realistic production with some defects
        $yieldRate = rand(85, 98) / 100; // 85-98% yield rate
        $totalOk = (int) floor($totalQty * $yieldRate);
        $totalKo = $totalQty - $totalOk;

        // Distribute quantities between first half and second half
        $firstHalfTotal = (int) floor($totalQty * 0.6); // 60% in first half
        $secondHalfTotal = $totalQty - $firstHalfTotal;

        // Calculate OK/KO for each half
        $firstHalfOk = (int) floor($firstHalfTotal * $yieldRate);
        $firstHalfKo = $firstHalfTotal - $firstHalfOk;

        $secondHalfOk = $totalOk - $firstHalfOk;
        $secondHalfKo = $totalKo - $firstHalfKo;

        // Ensure non-negative values
        $secondHalfOk = max(0, $secondHalfOk);
        $secondHalfKo = max(0, $secondHalfKo);

        return [
            'first_half' => [
                'ok' => $firstHalfOk,
                'ko' => $firstHalfKo,
            ],
            'second_half' => [
                'ok' => $secondHalfOk,
                'ko' => $secondHalfKo,
            ],
            'total_ok' => $totalOk,
            'total_ko' => $totalKo,
        ];
    }
}
