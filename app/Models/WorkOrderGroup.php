<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderGroup extends Model
{
    /** @use HasFactory<\Database\Factories\WorkOrderGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'unique_id',
        'status',
        'planner_id',
        'factory_id',
        'planned_start_date',
        'planned_completion_date',
        'actual_start_date',
        'actual_completion_date',
        'metadata',
        'batch_configuration',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'datetime',
            'planned_completion_date' => 'datetime',
            'actual_start_date' => 'datetime',
            'actual_completion_date' => 'datetime',
            'metadata' => 'array',
            'batch_configuration' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($workOrderGroup) {
            if (empty($workOrderGroup->unique_id)) {
                $workOrderGroup->unique_id = static::generateUniqueId($workOrderGroup->factory_id);
            }
        });

        static::updating(function ($workOrderGroup) {
            // Validate dependencies before allowing activation
            if ($workOrderGroup->isDirty('status') && $workOrderGroup->status === 'active') {
                if (! $workOrderGroup->canActivate()) {
                    $errors = $workOrderGroup->getActivationValidationErrors();

                    // Reset status to previous value to prevent activation
                    $workOrderGroup->status = $workOrderGroup->getOriginal('status');

                    // Store validation errors in session for Filament to show
                    session()->flash('workorder_group_validation_errors', $errors);

                    // Prevent the update from proceeding
                    return false;
                }
            }
        });

        static::deleting(function ($workOrderGroup) {
            // Delete all associated work orders when the group is deleted
            $workOrderGroup->workOrders()->delete();
        });
    }

    public static function generateUniqueId(int $factoryId): string
    {
        $factory = Factory::find($factoryId);
        $prefix = $factory ? 'F'.$factory->id : 'F1';

        do {
            $uniqueId = $prefix.'-WG'.str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('unique_id', $uniqueId)->exists());

        return $uniqueId;
    }

    public function planner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planner_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->orderBy('sequence_order');
    }

    public function workOrderGroupLogs(): HasMany
    {
        return $this->hasMany(WorkOrderGroupLog::class)->orderBy('created_at', 'desc');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(WorkOrderDependency::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        $totalWorkOrders = $this->workOrders()->count();

        if ($totalWorkOrders === 0) {
            return 0;
        }

        $completedWorkOrders = $this->workOrders()->where('status', 'Completed')->count();

        return round(($completedWorkOrders / $totalWorkOrders) * 100, 2);
    }

    public function getTotalQuantityAttribute(): int
    {
        return $this->workOrders()->sum('qty');
    }

    public function getCompletedQuantityAttribute(): int
    {
        return $this->workOrders()->where('status', 'Completed')->sum('ok_qtys');
    }

    public function canStart(): bool
    {
        return $this->status === 'active' && $this->workOrders()->where('is_dependency_root', true)->exists();
    }

    /**
     * Check if the group can be activated (has proper dependencies set up)
     */
    public function canActivate(): bool
    {
        $workOrders = $this->workOrders()->get();

        // Must have at least 2 work orders to require dependencies
        if ($workOrders->count() < 2) {
            return true; // Single work order groups don't need dependencies
        }

        // Must have at least one root work order
        $rootWorkOrders = $workOrders->where('is_dependency_root', true);
        if ($rootWorkOrders->isEmpty()) {
            return false;
        }

        // Must have at least one non-root work order
        $dependentWorkOrders = $workOrders->where('is_dependency_root', false);
        if ($dependentWorkOrders->isEmpty()) {
            return false;
        }

        // All non-root work orders must have dependencies defined
        foreach ($dependentWorkOrders as $dependentWO) {
            $hasDependency = $this->dependencies()
                ->where('successor_work_order_id', $dependentWO->id)
                ->exists();

            if (! $hasDependency) {
                return false; // Found a non-root WO without dependencies
            }
        }

        return true;
    }

    /**
     * Get validation errors for group activation
     */
    public function getActivationValidationErrors(): array
    {
        $errors = [];
        $workOrders = $this->workOrders()->get();

        if ($workOrders->count() < 2) {
            return $errors; // Single work order groups are always valid
        }

        $rootWorkOrders = $workOrders->where('is_dependency_root', true);
        $dependentWorkOrders = $workOrders->where('is_dependency_root', false);

        if ($rootWorkOrders->isEmpty()) {
            $errors[] = 'Group must have at least one root work order (is_dependency_root = true)';
        }

        if ($dependentWorkOrders->isEmpty()) {
            $errors[] = 'Group must have at least one dependent work order (is_dependency_root = false)';
        }

        // Check each dependent work order for missing dependencies
        $missingDependencies = [];
        foreach ($dependentWorkOrders as $dependentWO) {
            $hasDependency = $this->dependencies()
                ->where('successor_work_order_id', $dependentWO->id)
                ->exists();

            if (! $hasDependency) {
                $missingDependencies[] = $dependentWO->unique_id;
            }
        }

        if (! empty($missingDependencies)) {
            $errors[] = 'The following work orders have no dependencies defined: '.implode(', ', $missingDependencies);
        }

        return $errors;
    }

    public function markAsStarted(): void
    {
        if ($this->actual_start_date === null) {
            $this->update([
                'actual_start_date' => now(),
                'status' => 'active',
            ]);
        }
    }

    public function checkCompletion(): void
    {
        $totalWorkOrders = $this->workOrders()->count();
        $completedWorkOrders = $this->workOrders()->where('status', 'Completed')->count();

        if ($totalWorkOrders > 0 && $completedWorkOrders === $totalWorkOrders) {
            $this->update([
                'status' => 'completed',
                'actual_completion_date' => now(),
            ]);
        }
    }

    /**
     * Update status of waiting work orders based on their dependencies
     * This should be called when any work order in the group updates its quantities
     */
    public function updateWaitingWorkOrderStatuses(): void
    {
        $waitingWorkOrders = $this->workOrders()
            ->where('status', 'Waiting')
            ->get();

        foreach ($waitingWorkOrders as $workOrder) {
            $workOrder->updateStatusBasedOnDependencies();
        }

        \Illuminate\Support\Facades\Log::info('Updated waiting work order statuses for group', [
            'group_id' => $this->id,
            'updated_count' => $waitingWorkOrders->count(),
        ]);
    }

    /**
     * Initialize all work order statuses when the group is activated
     * Root work orders become 'Assigned', others become 'Waiting'
     */
    public function initializeWorkOrderStatuses(): void
    {
        $workOrders = $this->workOrders()->get();

        foreach ($workOrders as $workOrder) {
            if ($workOrder->is_dependency_root) {
                // Root work orders start as Assigned
                $workOrder->update([
                    'status' => 'Assigned',
                    'dependency_status' => 'ready',
                ]);
            } else {
                // Non-root work orders start as Waiting until dependencies are satisfied
                $workOrder->update([
                    'status' => 'Waiting',
                    'dependency_status' => 'blocked',
                ]);
            }
        }

        \Illuminate\Support\Facades\Log::info('Initialized work order statuses for group', [
            'group_id' => $this->id,
            'work_orders_count' => $workOrders->count(),
        ]);
    }

    /**
     * Get batch size for a specific work order
     */
    public function getBatchSizeForWorkOrder(int $workOrderId): ?int
    {
        if (! $this->batch_configuration) {
            return null;
        }

        return $this->batch_configuration[$workOrderId] ?? null;
    }

    /**
     * Set batch size for a specific work order
     */
    public function setBatchSizeForWorkOrder(int $workOrderId, int $batchSize): void
    {
        $config = $this->batch_configuration ?? [];
        $config[$workOrderId] = $batchSize;
        $this->update(['batch_configuration' => $config]);
    }

    /**
     * Get all batch configurations for work orders in this group
     */
    public function getBatchConfigurations(): array
    {
        if (! $this->batch_configuration) {
            return [];
        }

        $workOrders = $this->workOrders()->get()->keyBy('id');
        $configurations = [];

        foreach ($this->batch_configuration as $workOrderId => $batchSize) {
            if (isset($workOrders[$workOrderId])) {
                $configurations[] = [
                    'work_order_id' => $workOrderId,
                    'work_order_name' => $workOrders[$workOrderId]->unique_id,
                    'batch_size' => $batchSize,
                ];
            }
        }

        return $configurations;
    }

    /**
     * Check if a work order has completed enough quantity for a batch
     */
    public function hasWorkOrderCompletedBatch(WorkOrder $workOrder): bool
    {
        $batchSize = $this->getBatchSizeForWorkOrder($workOrder->id);

        if (! $batchSize) {
            return false; // No batch configuration set
        }

        // Get current in-progress batch
        $currentBatch = $workOrder->batches()->where('status', 'in_progress')->first();

        if (! $currentBatch) {
            return false; // No active batch
        }

        // Calculate total OK quantities produced in current batch
        $totalOkQtys = $workOrder->ok_qtys ?? 0;

        return $totalOkQtys >= $batchSize;
    }

    /**
     * Auto-complete batch if work order has reached batch size
     */
    public function autoCompleteBatchIfReady(WorkOrder $workOrder): bool
    {
        if (! $this->hasWorkOrderCompletedBatch($workOrder)) {
            return false;
        }

        $currentBatch = $workOrder->batches()->where('status', 'in_progress')->first();

        if ($currentBatch) {
            $batchSize = $this->getBatchSizeForWorkOrder($workOrder->id);

            return $currentBatch->completeBatch($batchSize);
        }

        return false;
    }
}
