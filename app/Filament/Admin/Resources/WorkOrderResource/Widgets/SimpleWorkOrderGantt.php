<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;


use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class SimpleWorkOrderGantt extends Widget
{
    protected static string $view = 'filament.admin.widgets.simple-work-order-gantt';

    protected int | string | array $columnSpan = 'full';

    public function getWorkOrders()
    {
        return WorkOrder::query()
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('start_time')
            ->get()
            ->map(function ($workOrder) {
                $startDate = Carbon::parse($workOrder->start_time)->startOfDay();
                $endDate = Carbon::parse($workOrder->end_time)->startOfDay();

                // Fetch actual start time (earliest 'Start' status using created_at)
                $actualStartTime = WorkOrderLog::where('work_order_id', $workOrder->id)
                    ->where('status', 'Start')
                    ->orderBy('created_at', 'asc')
                    ->value('created_at');

                // Fetch actual end time (latest 'Closed' or 'Completed' status using created_at)
                $actualEndTime = WorkOrderLog::where('work_order_id', $workOrder->id)
                    ->whereIn('status', ['Closed', 'Completed'])
                    ->orderBy('created_at', 'desc')
                    ->value('created_at');

                return [
                    'id' => $workOrder->id,
                    'unique_id' => $workOrder->unique_id,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'actual_start_date' => $actualStartTime ? Carbon::parse($actualStartTime)->format('Y-m-d') : null,
                    'actual_end_date' => $actualEndTime ? Carbon::parse($actualEndTime)->format('Y-m-d') : null,
                    'status' => $workOrder->status,
                ];
            })
            ->values()
            ->toArray();
    }

    // Add this method to control widget visibility
}
