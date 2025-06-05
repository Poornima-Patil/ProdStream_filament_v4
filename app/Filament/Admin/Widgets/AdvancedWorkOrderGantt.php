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
        $query = WorkOrder::whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('start_time');

        if ($this->selectedDate) {
            $selectedDate = Carbon::parse($this->selectedDate);

            if ($this->timeRange === 'week') {
                $startOfWeek = $selectedDate->copy()->startOfWeek();
                $endOfWeek = $selectedDate->copy()->endOfWeek();
                \Log::info('Filtering work orders for the week:', [
                    'startOfWeek' => $startOfWeek->toDateTimeString(),
                    'endOfWeek' => $endOfWeek->toDateTimeString(),
                ]);
                $query->where('start_time', '<=', $endOfWeek)
                      ->where('end_time', '>=', $startOfWeek);

            } elseif ($this->timeRange === 'day') {
                $startOfDay = $selectedDate->copy()->startOfDay();
                $endOfDay = $selectedDate->copy()->endOfDay();
                \Log::info('Filtering work orders for the day:', [
                    'selectedDate' => $selectedDate->toDateString(),
                ]);
                $query->where('start_time', '<=', $endOfDay)
                      ->where('end_time', '>=', $startOfDay);

            } elseif ($this->timeRange === 'month') {
                $startOfMonth = $selectedDate->copy()->startOfMonth();
                $endOfMonth = $selectedDate->copy()->endOfMonth();
                \Log::info('Filtering work orders for the month:', [
                    'year' => $selectedDate->year,
                    'month' => $selectedDate->month,
                ]);
                $query->where('start_time', '<=', $endOfMonth)
                      ->where('end_time', '>=', $startOfMonth);
            }

            \Log::info('Selected date:', [
                'selectedDate' => $selectedDate,
            ]);
        }

        $workOrders = $query->get();

        \Log::info('Filtered work orders:', $workOrders->toArray());

        return $workOrders;
    }

    public function render(): View
    {
        $this->timeRange = request()->get('timeRange', 'week'); // Default to 'week'
        $this->selectedDate = request()->get('selectedDate', now()->startOfWeek()->format('Y-m-d')); // Default to the current week

    // If week format, convert to the Monday of that week at 00:00:00
    if ($this->timeRange === 'week' && preg_match('/^\d{4}-W\d{2}$/', $this->selectedDate)) {
        $dt = \DateTime::createFromFormat('o-\WW', $this->selectedDate);
        if ($dt) {
            $this->selectedDate = $dt->format('Y-m-d'); // always just the date, no time
        }
    }


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
