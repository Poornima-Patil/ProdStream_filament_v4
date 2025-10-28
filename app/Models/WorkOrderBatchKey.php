<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderBatchKey extends Model
{
    protected $fillable = [
        'work_order_id',
        'batch_id',
        'batch_number',
        'key_code',
        'quantity_produced',
        'generated_at',
        'is_consumed',
        'consumed_at',
        'consumed_by_work_order_id',
        'consumed_by_batch_number',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'consumed_at' => 'datetime',
            'is_consumed' => 'boolean',
            'quantity_produced' => 'integer',
            'batch_number' => 'integer',
            'consumed_by_batch_number' => 'integer',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WorkOrderBatch::class);
    }

    public function consumedByWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'consumed_by_work_order_id');
    }

    /**
     * Generate a unique key code for this batch
     */
    public static function generateKeyCode(WorkOrder $workOrder, int $batchNumber): string
    {
        $timestamp = now()->format('Ymd');

        return "KEY-{$workOrder->unique_id}-{$batchNumber}-{$timestamp}";
    }

    /**
     * Mark this key as consumed
     */
    public function markAsConsumed(WorkOrder $consumerWorkOrder, int $batchNumber): bool
    {
        if ($this->is_consumed) {
            return false; // Already consumed
        }

        return $this->update([
            'is_consumed' => true,
            'consumed_at' => now(),
            'consumed_by_work_order_id' => $consumerWorkOrder->id,
            'consumed_by_batch_number' => $batchNumber,
        ]);
    }

    /**
     * Check if this key is available for consumption
     */
    public function isAvailable(): bool
    {
        return ! $this->is_consumed;
    }

    /**
     * Scope to get available keys for a specific work order
     */
    public function scopeAvailableForWorkOrder($query, int $workOrderId)
    {
        return $query->where('work_order_id', $workOrderId)
            ->where('is_consumed', false);
    }

    /**
     * Scope to get available keys for any work orders in a group
     */
    public function scopeAvailableInGroup($query, int $groupId)
    {
        return $query->whereHas('workOrder', function ($q) use ($groupId) {
            $q->where('work_order_group_id', $groupId);
        })->where('is_consumed', false);
    }
}
