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

    public $timeRange = 'week';
    public $selectedDate;

    public function mount()
    {
        // Default to current week
        $this->selectedDate = now()->startOfWeek()->format('Y-m-d');
    }

    public function getWorkOrders()
    {
        ini_set('memory_limit', '256M');

        Log::info('Fetching Gantt Work Orders with filtered table query');

        // Get the same filtered query as the table
        $query = $this->getPageTableQuery()
            ->clone()
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where('factory_id', \Illuminate\Support\Facades\Auth::user()->factory_id);

        // Apply the same date filtering logic as Advanced Gantt Chart
        if ($this->selectedDate) {
            $selectedDate = Carbon::parse($this->selectedDate);
            $startOfWeek = $selectedDate->copy()->startOfWeek();
            $endOfWeek = $selectedDate->copy()->endOfWeek();

            $query->where(function ($q) use ($startOfWeek, $endOfWeek) {
                $q->where(function ($subQ) use ($startOfWeek, $endOfWeek) {
                    $subQ->whereBetween('start_time', [$startOfWeek, $endOfWeek])
                        ->orWhereBetween('end_time', [$startOfWeek, $endOfWeek])
                        ->orWhere(function ($dateQ) use ($startOfWeek, $endOfWeek) {
                            $dateQ->where('start_time', '<=', $startOfWeek)
                                ->where('end_time', '>=', $endOfWeek);
                        });
                });
            });
        }

        return $query->orderBy('start_time')
            ->with(['workOrderLogs', 'machine', 'operator.user']) // Eager load relationships
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
                    'ok_qtys' => $workOrder->ok_qtys ?? 0,
                    'scrapped_qtys' => $workOrder->scrapped_qtys ?? 0,
                    'actual_start_date' => $actualStartTime ? $actualStartTime->format('Y-m-d') : null,
                    'actual_end_date' => $actualEndTime ? $actualEndTime->format('Y-m-d') : null,
                    'status' => $workOrder->status,
                    'machine_name' => $workOrder->machine?->name ?? 'N/A',
                    'operator_name' => $workOrder->operator?->user?->getFilamentName() ?? 'Unassigned',
                    'workOrderLogs' => $logs, // <-- Pass logs to Blade!
                ];
            });
    }

    public function previousWeek()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subWeek()->format('Y-m-d');
    }

    public function today()
    {
        $this->selectedDate = now()->startOfWeek()->format('Y-m-d');
    }

    public function nextWeek()
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addWeek()->format('Y-m-d');
    }

    public function getTablePage(): string
    {
        return \App\Filament\Admin\Resources\WorkOrderResource\Pages\ListWorkOrders::class;
    }

    public function render(): View
    {
        return view(static::$view, [
            'workOrders' => $this->getWorkOrders(),
            'timeRange' => $this->timeRange,
            'selectedDate' => $this->selectedDate,
        ]);
    }

    public function getViewData(): array
    {
        return [
            'workOrders' => $this->getWorkOrders(),
            'timeRange' => $this->timeRange,
            'selectedDate' => $this->selectedDate,
        ];
    }
}
