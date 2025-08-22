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
            $targetDate = Carbon::createFromFormat('Y-m-d', $dateInput)->endOfDay();
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use YYYY-MM-DD format.');
            return 1;
        }

        $this->info("Simulating work order execution for Factory: {$factory->name} (ID: {$factoryId})");
        $this->info("Processing work orders ending by: {$targetDate->format('Y-m-d H:i:s')}");

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

                // Log successful completion
                Log::info("Successfully simulated Work Order", [
                    'work_order_id' => $workOrder->id,
                    'work_order_unique_id' => $workOrder->unique_id,
                    'factory_id' => $workOrder->factory_id,
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
            $startTime = Carbon::parse($workOrder->start_time);
            $endTime = Carbon::parse($workOrder->end_time);
            $totalDuration = $startTime->diffInMinutes($endTime);
            $halfwayTime = $startTime->copy()->addMinutes($totalDuration / 2);
            $resumeTime = $halfwayTime->copy()->addMinutes(10); // 10 minute break

            // Generate batch number
            $batchNumber = $this->generateBatchNumber($workOrder);

            // Calculate quantities
            $totalQty = $workOrder->qty;
            $quantities = $this->calculateQuantities($totalQty);

            // Get operator user ID
            $operatorUserId = $this->getOperatorUserId($workOrder);

            // Temporarily disable all WorkOrder model events to prevent automatic log creation
            WorkOrder::withoutEvents(function () use ($workOrder, $batchNumber, $startTime, $halfwayTime, $resumeTime, $endTime, $operatorUserId, $quantities) {

                // Step 1: Start the work order
                $workOrder->update(['status' => 'Start', 'material_batch' => $batchNumber]);
                $startLog = $this->createManualWorkOrderLog($workOrder, 'Start', $startTime, $operatorUserId, 0, 0);

                // Step 2: Process first half and put on Hold
                $workOrder->update(['status' => 'Hold']);
                $holdLog = $this->createManualWorkOrderLog($workOrder, 'Hold', $halfwayTime, $operatorUserId, $quantities['first_half']['ok'], $quantities['first_half']['ko']);

                // Create first quantity entry
                $this->createQuantityEntry($workOrder, $holdLog, $quantities['first_half']);

                // Step 3: Resume from Hold to Start
                $workOrder->update(['status' => 'Start']);
                $resumeLog = $this->createManualWorkOrderLog($workOrder, 'Start', $resumeTime, $operatorUserId, $quantities['first_half']['ok'], $quantities['first_half']['ko']);

                // Step 4: Complete the work order
                $workOrder->update(['status' => 'Completed', 'ok_qtys' => $quantities['total_ok'], 'scrapped_qtys' => $quantities['total_ko']]);
                $completeLog = $this->createManualWorkOrderLog($workOrder, 'Completed', $endTime, $operatorUserId, $quantities['total_ok'], $quantities['total_ko']);

                // Create second quantity entry
                $this->createQuantityEntry($workOrder, $completeLog, $quantities['second_half']);
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
     * Create manual work order log with specific timing and user
     */
    private function createManualWorkOrderLog(WorkOrder $workOrder, string $status, Carbon $timestamp, int $userId, int $okQtys, int $scrappedQtys): WorkOrderLog
    {
        // Calculate FPY for Hold and Completed statuses
        $fpy = 0;
        if (in_array($status, ['Hold', 'Completed']) && ($okQtys + $scrappedQtys) > 0) {
            $fpy = ($okQtys / ($okQtys + $scrappedQtys)) * 100;
        }

        // Create log entry directly in database to avoid any model events
        $logId = DB::table('work_order_logs')->insertGetId([
            'work_order_id' => $workOrder->id,
            'status' => $status,
            'changed_at' => $timestamp,
            'user_id' => $userId,
            'ok_qtys' => $okQtys,
            'scrapped_qtys' => $scrappedQtys,
            'remaining' => $workOrder->qty - ($okQtys + $scrappedQtys),
            'scrapped_reason_id' => $scrappedQtys > 0 ? 1 : null,
            'hold_reason_id' => $status === 'Hold' ? 1 : null,
            'fpy' => $fpy,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
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

        // Use direct database insertion to maintain timestamp consistency
        DB::table('work_order_quantities')->insert([
            'work_order_id' => $workOrder->id,
            'work_order_log_id' => $log->id,
            'ok_quantity' => $quantities['ok'],
            'scrapped_quantity' => $quantities['ko'],
            'reason_id' => $quantities['ko'] > 0 ? 1 : null, // Assuming reason ID 1 exists
            'created_at' => $log->changed_at,
            'updated_at' => $log->changed_at,
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
        $date = Carbon::parse($workOrder->start_time)->format('Ymd');
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
