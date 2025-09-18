<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderGroupLog extends Model
{
    protected $fillable = [
        'work_order_group_id',
        'factory_id',
        'event_type',
        'event_description',
        'triggered_work_order_id',
        'triggering_work_order_id',
        'previous_status',
        'new_status',
        'metadata',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function workOrderGroup(): BelongsTo
    {
        return $this->belongsTo(WorkOrderGroup::class);
    }

    public function triggeredWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'triggered_work_order_id');
    }

    public function triggeringWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'triggering_work_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the factory as owner for Filament tenancy
     * Direct relationship to Factory using factory_id column
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }




    /**
     * Create a dependency satisfaction log entry
     */
    public static function logDependencySatisfied(WorkOrder $triggeredWorkOrder, array $triggeringWorkOrders): void
    {
        $triggeringList = collect($triggeringWorkOrders)->pluck('predecessor_unique_id')->join(', ');

        self::create([
            'work_order_group_id' => $triggeredWorkOrder->work_order_group_id,
            'factory_id' => $triggeredWorkOrder->factory_id,
            'event_type' => 'dependency_satisfied',
            'event_description' => "Work Order {$triggeredWorkOrder->unique_id} moved from Waiting to Assigned after dependencies were satisfied by: {$triggeringList}",
            'triggered_work_order_id' => $triggeredWorkOrder->id,
            'triggering_work_order_id' => $triggeringWorkOrders[0]['predecessor_id'] ?? null,
            'previous_status' => 'Waiting',
            'new_status' => 'Assigned',
            'metadata' => [
                'triggering_work_orders' => $triggeringWorkOrders,
                'dependency_count' => count($triggeringWorkOrders),
            ],
        ]);
    }

    /**
     * Create a work order status change log entry
     */
    public static function logStatusChange(WorkOrder $workOrder, string $previousStatus, string $newStatus, ?User $user = null): void
    {
        if (!$workOrder->work_order_group_id) {
            return;
        }

        self::create([
            'work_order_group_id' => $workOrder->work_order_group_id,
            'factory_id' => $workOrder->factory_id,
            'event_type' => 'status_change',
            'event_description' => "Work Order {$workOrder->unique_id} status changed from {$previousStatus} to {$newStatus}",
            'triggered_work_order_id' => $workOrder->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'user_id' => $user?->id ?? auth()->id(),
            'metadata' => [
                'machine_id' => $workOrder->machine_id,
                'operator_id' => $workOrder->operator_id,
            ],
        ]);
    }

    /**
     * Create a work order completion triggering log entry
     */
    public static function logWorkOrderTriggeredNext(WorkOrder $completedWorkOrder, array $nextWorkOrders): void
    {
        if (!$completedWorkOrder->work_order_group_id) {
            return;
        }

        $nextWorkOrdersList = collect($nextWorkOrders)->pluck('unique_id')->join(', ');

        self::create([
            'work_order_group_id' => $completedWorkOrder->work_order_group_id,
            'factory_id' => $completedWorkOrder->factory_id,
            'event_type' => 'work_order_triggered',
            'event_description' => "Work Order {$completedWorkOrder->unique_id} completion triggered the following work orders: {$nextWorkOrdersList}",
            'triggering_work_order_id' => $completedWorkOrder->id,
            'metadata' => [
                'triggered_work_orders' => $nextWorkOrders,
                'completion_time' => now(),
            ],
        ]);
    }
}
