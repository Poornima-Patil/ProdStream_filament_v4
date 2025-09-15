<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'assetId',
        'status',
        'department_id',
        'factory_id',
        'machine_group_id',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function machineGroup()
    {
        return $this->belongsTo(MachineGroup::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('status', 1); // Assuming 'status' is 1 for Active
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    /**
     * Get scheduled work orders for this machine within a date range
     */
    public function getScheduledWorkOrders($startDate, $endDate)
    {
        return $this->workOrders()
            ->where('factory_id', $this->factory_id)
            ->whereIn('status', ['Assigned', 'Start'])
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->whereBetween('start_time', [$startDate, $endDate])
            ->with(['operator.user', 'bom.purchaseOrder.partNumber'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get currently running work order for this machine
     */
    public function getRunningWorkOrder()
    {
        return $this->workOrders()
            ->where('factory_id', $this->factory_id)
            ->where('status', 'Start')
            ->with(['operator.user', 'bom.purchaseOrder.partNumber'])
            ->first();
    }

    /**
     * Get calendar events for the machine schedule
     */
    public function getCalendarEvents($viewType = 'week', $date = null)
    {
        $date = $date ? Carbon::parse($date) : now();

        // Define date ranges based on view type
        switch ($viewType) {
            case 'day':
                $startDate = $date->copy()->startOfDay();
                $endDate = $date->copy()->endOfDay();
                break;
            case 'week':
                $startDate = $date->copy()->startOfWeek(Carbon::MONDAY);
                $endDate = $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(5)->endOfDay(); // Monday to Saturday
                break;
            case 'month':
                $startDate = $date->copy()->startOfMonth();
                $endDate = $date->copy()->endOfMonth();
                break;
            default:
                $startDate = $date->copy()->startOfWeek(Carbon::MONDAY);
                $endDate = $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(5)->endOfDay(); // Monday to Saturday
        }

        $workOrders = $this->getScheduledWorkOrders($startDate, $endDate);
        $events = [];

        foreach ($workOrders as $workOrder) {
            $operatorName = $workOrder->operator?->user
                ? "{$workOrder->operator->user->first_name} {$workOrder->operator->user->last_name}"
                : 'Unassigned';

            $partNumber = $workOrder->bom?->purchaseOrder?->partNumber?->partnumber ?? 'Unknown Part';

            $events[] = [
                'id' => "wo-{$workOrder->id}",
                'title' => "WO #{$workOrder->unique_id}",
                'subtitle' => $partNumber,
                'start' => $workOrder->start_time->toISOString(),
                'end' => $workOrder->end_time->toISOString(),
                'status' => $workOrder->status,
                'operator' => $operatorName,
                'work_order_id' => $workOrder->id,
                'unique_id' => $workOrder->unique_id,
                'backgroundColor' => $workOrder->status === 'Start' ? '#ef4444' : '#f97316', // Red for running, Orange for planned
                'borderColor' => $workOrder->status === 'Start' ? '#dc2626' : '#ea580c',
                'textColor' => '#ffffff'
            ];
        }

        return $events;
    }
}
