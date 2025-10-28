<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkOrderBatch extends Model
{
    protected $fillable = [
        'work_order_id',
        'batch_number',
        'planned_quantity',
        'actual_quantity',
        'status',
        'keys_required',
        'keys_consumed',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'integer',
            'actual_quantity' => 'integer',
            'batch_number' => 'integer',
            'keys_required' => 'array',
            'keys_consumed' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batchKey(): HasOne
    {
        return $this->hasOne(WorkOrderBatchKey::class, 'batch_id');
    }

    public function keyConsumptions(): HasMany
    {
        return $this->hasMany(BatchKeyConsumption::class, 'consumer_batch_id');
    }

    /**
     * Create a new batch for a work order
     */
    public static function createBatch(
        WorkOrder $workOrder,
        int $plannedQuantity,
        array $keysRequired = []
    ): self {
        // Get the next batch number for this work order
        $nextBatchNumber = self::where('work_order_id', $workOrder->id)->max('batch_number') + 1;

        return self::create([
            'work_order_id' => $workOrder->id,
            'batch_number' => $nextBatchNumber,
            'planned_quantity' => $plannedQuantity,
            'status' => 'planned',
            'keys_required' => $keysRequired,
        ]);
    }

    /**
     * Start this batch (requires keys if work order is in a group)
     */
    public function startBatch(array $consumedKeys = []): bool
    {
        if ($this->status !== 'planned') {
            return false; // Can only start planned batches
        }

        // For grouped work orders, validate required keys
        if ($this->workOrder->work_order_group_id && ! empty($this->keys_required)) {
            if (empty($consumedKeys)) {
                return false; // Keys required but none provided
            }

            // Validate that all required keys are available and consume them
            foreach ($consumedKeys as $keyId) {
                $key = WorkOrderBatchKey::find($keyId);
                if (! $key || ! $key->isAvailable()) {
                    return false; // Key not available
                }

                // Create consumption record
                BatchKeyConsumption::createConsumption($this, $key);
            }

            // Log key consumption
            WorkOrderGroupLog::logKeyConsumption($this->workOrder, $consumedKeys, $this->batch_number);
        }

        return $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'keys_consumed' => $consumedKeys,
        ]);
    }

    /**
     * Complete this batch and generate a key
     */
    public function completeBatch(int $actualQuantity): bool
    {
        if ($this->status !== 'in_progress') {
            return false; // Can only complete in-progress batches
        }

        $this->update([
            'status' => 'completed',
            'actual_quantity' => $actualQuantity,
            'completed_at' => now(),
        ]);

        // Generate a key for grouped work orders
        if ($this->workOrder->work_order_group_id) {
            $this->generateBatchKey($actualQuantity);
        }

        return true;
    }

    /**
     * Generate a batch key for this completed batch
     */
    protected function generateBatchKey(int $quantity): WorkOrderBatchKey
    {
        $keyCode = WorkOrderBatchKey::generateKeyCode($this->workOrder, $this->batch_number);

        $batchKey = WorkOrderBatchKey::create([
            'work_order_id' => $this->work_order_id,
            'batch_id' => $this->id,
            'batch_number' => $this->batch_number,
            'key_code' => $keyCode,
            'quantity_produced' => $quantity,
            'generated_at' => now(),
        ]);

        // Log key generation
        WorkOrderGroupLog::logKeyGeneration($this->workOrder, $keyCode, $this->batch_number, $quantity);

        return $batchKey;
    }

    /**
     * Check if this batch can start (has required keys available)
     */
    public function canStart(): bool
    {
        if ($this->status !== 'planned') {
            return false;
        }

        // Individual work orders can always start
        if (! $this->workOrder->work_order_group_id) {
            return true;
        }

        // For grouped work orders, check if required keys are available
        if (empty($this->keys_required)) {
            return true; // No keys required
        }

        // Check if enough keys are available from predecessor work orders
        foreach ($this->keys_required as $requirement) {
            $availableKeys = WorkOrderBatchKey::availableForWorkOrder($requirement['work_order_id'])->count();
            if ($availableKeys < $requirement['quantity']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get progress percentage for this batch
     */
    public function getProgressPercentage(): float
    {
        if ($this->status === 'completed') {
            return 100.0;
        }

        if ($this->status === 'in_progress' && $this->actual_quantity) {
            return min(100, ($this->actual_quantity / $this->planned_quantity) * 100);
        }

        return 0.0;
    }

    /**
     * Scope for batches in a specific status
     */
    public function scopeInStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for batches of work orders in a specific group
     */
    public function scopeInGroup($query, int $groupId)
    {
        return $query->whereHas('workOrder', function ($q) use ($groupId) {
            $q->where('work_order_group_id', $groupId);
        });
    }
}
