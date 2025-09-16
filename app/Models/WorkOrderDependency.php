<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderDependency extends Model
{
    protected $fillable = [
        'work_order_group_id',
        'predecessor_work_order_id',
        'successor_work_order_id',
        'required_quantity',
        'dependency_type',
        'is_satisfied',
        'satisfied_at',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'is_satisfied' => 'boolean',
            'satisfied_at' => 'datetime',
            'conditions' => 'array',
            'required_quantity' => 'integer',
        ];
    }

    public function workOrderGroup(): BelongsTo
    {
        return $this->belongsTo(WorkOrderGroup::class);
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'predecessor_work_order_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'successor_work_order_id');
    }

    public function checkSatisfaction(): bool
    {
        if ($this->dependency_type === 'quantity_based') {
            // For now, we'll use the quantity from the work order (ok_qtys)
            // In a real implementation, you might use batches or another quantity tracking method
            $totalProduced = $this->predecessor->ok_qtys ?? 0;

            if ($totalProduced >= $this->required_quantity) {
                $this->update([
                    'is_satisfied' => true,
                    'satisfied_at' => now(),
                ]);

                return true;
            }
        } elseif ($this->dependency_type === 'completion_based') {
            if ($this->predecessor->status === 'Completed') {
                $this->update([
                    'is_satisfied' => true,
                    'satisfied_at' => now(),
                ]);

                return true;
            }
        }

        return $this->is_satisfied;
    }

    public function getAvailableQuantity(): int
    {
        // For now, use ok_qtys from the work order
        // In a real implementation, you might use batches or another tracking method
        return $this->predecessor->ok_qtys ?? 0;
    }

    public function getSatisfactionProgressAttribute(): float
    {
        if ($this->dependency_type === 'quantity_based') {
            $totalProduced = $this->getAvailableQuantity();
            return min(100, round(($totalProduced / $this->required_quantity) * 100, 2));
        }

        return $this->is_satisfied ? 100 : 0;
    }
}
