<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use App\Models\WorkOrderLog;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SimpleWorkOrderGantt extends Widget
{
    use InteractsWithPageTable;

    protected static string $view = 'filament.admin.widgets.simple-work-order-gantt';

    protected int|string|array $columnSpan = 'full';

    public function getWorkOrders()
    {
        ini_set('memory_limit', '256M');

        \Log::info('Fetching Gantt Work Orders with base table query');

        return $this->getPageTableQuery()
            ->clone()
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('start_time')
            ->with('workOrderLogs') // <-- Eager load logs!
            ->get()
            ->map(function ($workOrder) {
                // Fetch logs as array
                $logs = $workOrder->workOrderLogs
                    ->map(function ($log) {
                        return [
                            'status' => $log->status,
                            'changed_at' => $log->changed_at ? $log->changed_at->toDateTimeString() : null,
                        ];
                    })
                    ->toArray();

                // Fetch actual start time (earliest 'Start' status using changed_at)
                $actualStartTime = $workOrder->workOrderLogs
                    ->where('status', 'Start')
                    ->sortBy('changed_at')
                    ->first()?->changed_at;

                // Fetch actual end time based on status
                if ($workOrder->status === 'Hold') {
                    $actualEndTime = $workOrder->workOrderLogs
                        ->where('status', 'Hold')
                        ->sortByDesc('changed_at')
                        ->first()?->changed_at;
                } else {
                    $actualEndTime = $workOrder->workOrderLogs
                        ->whereIn('status', ['Completed', 'Closed'])
                        ->sortByDesc('changed_at')
                        ->first()?->changed_at;
                }

                return [
                    'id' => $workOrder->id,
                    'unique_id' => $workOrder->unique_id,
                    'start_date' => $workOrder->start_time->format('Y-m-d'),
                    'end_date' => $workOrder->end_time->format('Y-m-d'),
                    'qty' => $workOrder->qty,
                    'ok_qtys' => $workOrder->ok_qtys,
                    'scrapped_qtys' => $workOrder->scrapped_qtys,
                    'actual_start_date' => $actualStartTime ? $actualStartTime->format('Y-m-d') : null,
                    'actual_end_date' => $actualEndTime ? $actualEndTime->format('Y-m-d') : null,
                    'status' => $workOrder->status,
                    'workOrderLogs' => $logs, // <-- Pass logs to Blade!
                ];
            });
    }

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
