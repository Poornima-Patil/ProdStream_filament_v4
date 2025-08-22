<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class AdvancedWorkOrderGantt extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Gantt Chart';
    protected static string $view = 'filament.admin.pages.advanced-work-order-gantt';

    public $timeRange = 'week';
    public $selectedDate;
    protected static ?string $navigationGroup = 'Work Order Reports';
    public function mount()
    {
        $this->timeRange = request()->get('timeRange', 'week');
        $this->selectedDate = request()->get('selectedDate', now()->startOfWeek()->format('Y-m-d'));
    }

    public function getWorkOrders()
    {
        $user = Auth::user();
        $factoryId = $user?->factory_id;

        // First, let's get all work orders for this factory to see what we have
        $allWorkOrders = WorkOrder::where('factory_id', $factoryId)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->with('workOrderLogs')
            ->get();

        $query = WorkOrder::where('factory_id', $factoryId)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->with('workOrderLogs')
            ->orderBy('start_time');

        if ($this->selectedDate) {
            $selectedDate = Carbon::parse($this->selectedDate);

            if ($this->timeRange === 'week') {
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
            } elseif ($this->timeRange === 'day') {
                $startOfDay = $selectedDate->copy()->startOfDay();
                $endOfDay = $selectedDate->copy()->endOfDay();
                $query->where(function ($q) use ($startOfDay, $endOfDay) {
                    $q->where(function ($subQ) use ($startOfDay, $endOfDay) {
                        $subQ->whereBetween('start_time', [$startOfDay, $endOfDay])
                            ->orWhereBetween('end_time', [$startOfDay, $endOfDay])
                            ->orWhere(function ($dateQ) use ($startOfDay, $endOfDay) {
                                $dateQ->where('start_time', '<=', $startOfDay)
                                    ->where('end_time', '>=', $endOfDay);
                            });
                    });
                });
            } elseif ($this->timeRange === 'month') {
                $startOfMonth = $selectedDate->copy()->startOfMonth();
                $endOfMonth = $selectedDate->copy()->endOfMonth();
                $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                    $q->where(function ($subQ) use ($startOfMonth, $endOfMonth) {
                        $subQ->whereBetween('start_time', [$startOfMonth, $endOfMonth])
                            ->orWhereBetween('end_time', [$startOfMonth, $endOfMonth])
                            ->orWhere(function ($dateQ) use ($startOfMonth, $endOfMonth) {
                                $dateQ->where('start_time', '<=', $startOfMonth)
                                    ->where('end_time', '>=', $endOfMonth);
                            });
                    });
                });
            }
        }

        $filteredWorkOrders = $query->get();

        // Log debugging information
        Log::info('Work Orders Debug Info', [
            'factory_id' => $factoryId,
            'selected_date' => $this->selectedDate,
            'time_range' => $this->timeRange,
            'total_work_orders' => $allWorkOrders->count(),
            'filtered_work_orders' => $filteredWorkOrders->count(),
            'first_few_work_orders' => $allWorkOrders->take(3)->map(function ($wo) {
                return [
                    'id' => $wo->id,
                    'unique_id' => $wo->unique_id,
                    'start_time' => $wo->start_time,
                    'end_time' => $wo->end_time,
                ];
            })->toArray()
        ]);

        return $filteredWorkOrders;
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
