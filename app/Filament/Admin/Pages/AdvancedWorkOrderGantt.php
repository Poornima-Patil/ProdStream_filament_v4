<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;


class AdvancedWorkOrderGantt extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.admin.pages.advanced-work-order-gantt';

    public $timeRange = 'week';
    public $selectedDate;

    public function mount()
    {
        $this->timeRange = request()->get('timeRange', 'week');
        $this->selectedDate = request()->get('selectedDate', now()->startOfWeek()->format('Y-m-d'));
    }

    public function getWorkOrders()
    {
        $query = WorkOrder::where('factory_id', Auth::user()?->factory_id)
    ->whereNotNull('start_time')
    ->whereNotNull('end_time')
    ->orderBy('start_time');

        if ($this->selectedDate) {
            $selectedDate = Carbon::parse($this->selectedDate);

            if ($this->timeRange === 'week') {
                $startOfWeek = $selectedDate->copy()->startOfWeek();
                $endOfWeek = $selectedDate->copy()->endOfWeek();
                $query->where('start_time', '<=', $endOfWeek)
                      ->where('end_time', '>=', $startOfWeek);
            } elseif ($this->timeRange === 'day') {
                $startOfDay = $selectedDate->copy()->startOfDay();
                $endOfDay = $selectedDate->copy()->endOfDay();
                $query->where('start_time', '<=', $endOfDay)
                      ->where('end_time', '>=', $startOfDay);
            } elseif ($this->timeRange === 'month') {
                $startOfMonth = $selectedDate->copy()->startOfMonth();
                $endOfMonth = $selectedDate->copy()->endOfMonth();
                $query->where('start_time', '<=', $endOfMonth)
                      ->where('end_time', '>=', $startOfMonth);
            }
        }

        return $query->get();
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
