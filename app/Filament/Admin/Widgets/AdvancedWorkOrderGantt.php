<?php

namespace App\Filament\Admin\Widgets;

use App\Models\WorkOrder;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class AdvancedWorkOrderGantt extends Widget
{
    protected static string $view = 'filament.admin.widgets.advanced-work-order-gantt';

    protected int|string|array $columnSpan = 'full';

    public $timeRange = 'week'; // Default time range

    public $selectedDate = null; // Selected date for filtering

    public function getWorkOrders()
    {
        // Fetch all work orders
        $query = WorkOrder::whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('start_time');

        // Apply filtering based on the selected time range
        if ($this->selectedDate) {
            $selectedDate = Carbon::parse($this->selectedDate);

            if ($this->timeRange === 'week') {
                \Log::info('Filtering work orders for the week:', [
                    'startOfWeek' => $selectedDate->startOfWeek()->toDateTimeString(),
                    'endOfWeek' => $selectedDate->endOfWeek()->toDateTimeString(),
                ]);
                $query->whereBetween('start_time', [$selectedDate->startOfWeek(), $selectedDate->endOfWeek()])
                    ->whereBetween('end_time', [$selectedDate->startOfWeek(), $selectedDate->endOfWeek()]);
            } elseif ($this->timeRange === 'day') {
                \Log::info('Filtering work orders for the day:', [
                    'selectedDate' => $selectedDate->toDateString(),
                ]);
                $query->whereDate('start_time', $selectedDate->toDateString())
                    ->whereDate('end_time', $selectedDate->toDateString());
            } elseif ($this->timeRange === 'month') {
                \Log::info('Filtering work orders for the month:', [
                    'year' => $selectedDate->year,
                    'month' => $selectedDate->month,
                ]);
                $query->whereYear('start_time', $selectedDate->year)
                    ->whereMonth('start_time', $selectedDate->month)
                    ->whereYear('end_time', $selectedDate->year)
                    ->whereMonth('end_time', $selectedDate->month);
            }
        }

        $workOrders = $query->get();

        // Log the filtered work orders
        \Log::info('Filtered work orders:', $workOrders->toArray());

        return $workOrders;
    }

    public function render(): View
    {
        $this->timeRange = request()->get('timeRange', 'week'); // Default to 'week'
        $this->selectedDate = request()->get('selectedDate', now()->startOfWeek()->format('Y-m-d')); // Default to the current week

        \Log::info('AdvancedWorkOrderGantt render:', [
            'timeRange' => $this->timeRange,
            'selectedDate' => $this->selectedDate,
        ]);

        return view('filament.admin.widgets.advanced-work-order-gantt', [
            'workOrders' => $this->getWorkOrders(),
            'timeRange' => $this->timeRange,
            'selectedDate' => $this->selectedDate,
        ]);
    }
}
