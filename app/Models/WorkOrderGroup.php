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
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'datetime',
            'planned_completion_date' => 'datetime',
            'actual_start_date' => 'datetime',
            'actual_completion_date' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($workOrderGroup) {
            if (empty($workOrderGroup->unique_id)) {
                $workOrderGroup->unique_id = static::generateUniqueId($workOrderGroup->factory_id);
            }
        });
    }

    public static function generateUniqueId(int $factoryId): string
    {
        $factory = Factory::find($factoryId);
        $prefix = $factory ? 'F' . $factory->id : 'F1';

        do {
            $uniqueId = $prefix . '-WG' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
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
     *
     * @return void
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
            'updated_count' => $waitingWorkOrders->count()
        ]);
    }

    /**
     * Initialize all work order statuses when the group is activated
     * Root work orders become 'Assigned', others become 'Waiting'
     *
     * @return void
     */
    public function initializeWorkOrderStatuses(): void
    {
        $workOrders = $this->workOrders()->get();

        foreach ($workOrders as $workOrder) {
            if ($workOrder->is_dependency_root) {
                // Root work orders start as Assigned
                $workOrder->update([
                    'status' => 'Assigned',
                    'dependency_status' => 'ready'
                ]);
            } else {
                // Non-root work orders start as Waiting until dependencies are satisfied
                $workOrder->update([
                    'status' => 'Waiting',
                    'dependency_status' => 'blocked'
                ]);
            }
        }

        \Illuminate\Support\Facades\Log::info('Initialized work order statuses for group', [
            'group_id' => $this->id,
            'work_orders_count' => $workOrders->count()
        ]);
    }
}
