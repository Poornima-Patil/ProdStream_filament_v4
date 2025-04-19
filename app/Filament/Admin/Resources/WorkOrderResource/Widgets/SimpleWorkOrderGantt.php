<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\WithPagination;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class SimpleWorkOrderGantt extends Widget
{
    use WithPagination, InteractsWithPageTable;

    protected static string $view = 'filament.admin.widgets.simple-work-order-gantt';

    protected int | string | array $columnSpan = 'full';

    public function getWorkOrders()
    {
        ini_set('memory_limit', '256M');

        $perPage = 10;

        Log::info('Fetching Gantt Work Orders with base table query');

        return $this->getPageTableQuery()
            ->clone()
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('start_time')
            ->paginate($perPage)
            ->through(function ($workOrder) {
                $actualStartTime = WorkOrderLog::where('work_order_id', $workOrder->id)
                    ->where('status', 'Start')
                    ->orderBy('created_at', 'asc')
                    ->value('created_at');

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
                    'status' => $workOrder->status,
                ];
            });
    }

    // ✅ This is the required method for InteractsWithPageTable to work
    public function getTablePage(): string
    {
        return \App\Filament\Admin\Resources\WorkOrderResource\Pages\ListWorkOrders::class;
    }

    public function render(): View
    {
        return view(static::$view, [
            'workOrders' => $this->getWorkOrders(),
        ]);
    }
}
