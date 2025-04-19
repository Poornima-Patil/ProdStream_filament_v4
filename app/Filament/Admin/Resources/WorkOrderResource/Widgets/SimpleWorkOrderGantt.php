<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\WithPagination;

class SimpleWorkOrderGantt extends Widget
{
    use WithPagination;

    protected static string $view = 'filament.admin.widgets.simple-work-order-gantt';

    protected int | string | array $columnSpan = 'full';

    public function getWorkOrders()
    {
        ini_set('memory_limit', '256M'); // Increase memory limit for this method

        $perPage = 10; // Number of Work Orders to load per page

        return WorkOrder::query()
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('start_time')
            ->paginate($perPage)
            ->through(function ($workOrder) {
                // Fetch actual start time (earliest 'Start' status using created_at)
                $actualStartTime = WorkOrderLog::where('work_order_id', $workOrder->id)
                    ->where('status', 'Start')
                    ->orderBy('created_at', 'asc')
                    ->value('created_at');

                // Fetch actual end time (latest 'Completed' or 'Closed' status using created_at)
                $actualEndTime = WorkOrderLog::where('work_order_id', $workOrder->id)
                    ->whereIn('status', ['Completed', 'Closed'])
                    ->orderBy('created_at', 'desc')
                    ->value('created_at');

                return [
                    'id' => $workOrder->id,
                    'unique_id' => $workOrder->unique_id,
                    'start_date' => $workOrder->start_time->format('Y-m-d'),
                    'end_date' => $workOrder->end_time->format('Y-m-d'),
                    'actual_start_date' => $actualStartTime ? Carbon::parse($actualStartTime)->format('Y-m-d') : null,
                    'actual_end_date' => $actualEndTime ? Carbon::parse($actualEndTime)->format('Y-m-d') : null,
                    'status' => $workOrder->status, // Ensure the status key is included
                ];
            });
    }

    public function render(): View
    {
        return view(static::$view, [
            'workOrders' => $this->getWorkOrders(), // Pass $workOrders to the view
        ]);
    }
}
