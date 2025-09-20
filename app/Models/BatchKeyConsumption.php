<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchKeyConsumption extends Model
{
    protected $fillable = [
        'consumer_work_order_id',
        'consumer_batch_id',
        'consumer_batch_number',
        'consumed_key_id',
        'quantity_consumed',
        'consumption_timestamp',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'consumption_timestamp' => 'datetime',
            'metadata' => 'array',
            'quantity_consumed' => 'integer',
            'consumer_batch_number' => 'integer',
        ];
    }

    public function consumerWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'consumer_work_order_id');
    }

    public function consumerBatch(): BelongsTo
    {
        return $this->belongsTo(WorkOrderBatch::class, 'consumer_batch_id');
    }

    public function consumedKey(): BelongsTo
    {
        return $this->belongsTo(WorkOrderBatchKey::class, 'consumed_key_id');
    }

    /**
     * Create a consumption record and mark the key as consumed
     */
    public static function createConsumption(
        WorkOrderBatch $consumerBatch,
        WorkOrderBatchKey $key,
        array $metadata = []
    ): self {
        $consumption = self::create([
            'consumer_work_order_id' => $consumerBatch->work_order_id,
            'consumer_batch_id' => $consumerBatch->id,
            'consumer_batch_number' => $consumerBatch->batch_number,
            'consumed_key_id' => $key->id,
            'quantity_consumed' => $key->quantity_produced,
            'consumption_timestamp' => now(),
            'metadata' => $metadata,
        ]);

        // Mark the key as consumed
        $key->markAsConsumed($consumerBatch->workOrder, $consumerBatch->batch_number);

        return $consumption;
    }

    /**
     * Get all consumptions for a specific batch
     */
    public function scopeForBatch($query, int $batchId)
    {
        return $query->where('consumer_batch_id', $batchId);
    }

    /**
     * Get all consumptions for a specific work order
     */
    public function scopeForWorkOrder($query, int $workOrderId)
    {
        return $query->where('consumer_work_order_id', $workOrderId);
    }
}
