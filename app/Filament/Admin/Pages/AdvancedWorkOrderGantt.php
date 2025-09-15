<?php

namespace App\Filament\Admin\Pages;

use App\Models\WorkOrder;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdvancedWorkOrderGantt extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Gantt Chart';

    protected string $view = 'filament.admin.pages.advanced-work-order-gantt';

    public $timeRange = 'week';

    public $selectedDate;

    protected static string | \UnitEnum | null $navigationGroup = 'Work Order Reports';

    public function mount()
    {
        $this->timeRange = request()->get('timeRange', 'week');
        $this->selectedDate = request()->get('selectedDate', now()->setTimezone(config('app.timezone'))->startOfWeek()->format('Y-m-d'));
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
            $selectedDate = Carbon::parse($this->selectedDate)->setTimezone(config('app.timezone'));

            if ($this->timeRange === 'week') {
                $startOfWeek = $selectedDate->copy()->startOfWeek();
                $endOfWeek = $selectedDate->copy()->endOfWeek()->endOfDay();
                // Debug for our specific WO
                if ($specificWO = $allWorkOrders->where('unique_id', 'W0001_090325_O0039_082025_2040_8466936861_B')->first()) {
                    $woStart = Carbon::parse($specificWO->start_time);
                    $woEnd = Carbon::parse($specificWO->end_time);
                    Log::info('Week Filter Debug', [
                        'wo_start' => $woStart->toDateTimeString(),
                        'wo_end' => $woEnd->toDateTimeString(),
                        'week_start' => $startOfWeek->toDateTimeString(),
                        'week_end' => $endOfWeek->toDateTimeString(),
                        'start_lte_week_end' => $woStart <= $endOfWeek,
                        'end_gte_week_start' => $woEnd >= $startOfWeek,
                        'should_include' => $woStart <= $endOfWeek && $woEnd >= $startOfWeek,
                    ]);
                }

                $query->where(function ($q) use ($startOfWeek, $endOfWeek) {
                    $q->where(function ($subQ) use ($startOfWeek, $endOfWeek) {
                        $subQ->where('start_time', '<=', $endOfWeek)
                            ->where('end_time', '>=', $startOfWeek);
                    });
                });
            } elseif ($this->timeRange === 'day') {
                $startOfDay = $selectedDate->copy()->startOfDay();
                $endOfDay = $selectedDate->copy()->endOfDay();

                // Debug for our specific WO
                if ($specificWO = $allWorkOrders->where('unique_id', 'W0001_090325_O0039_082025_2040_8466936861_B')->first()) {
                    $woStart = Carbon::parse($specificWO->start_time);
                    $woEnd = Carbon::parse($specificWO->end_time);
                    Log::info('Day Filter Debug', [
                        'selected_date' => $this->selectedDate,
                        'wo_start' => $woStart->toDateTimeString(),
                        'wo_end' => $woEnd->toDateTimeString(),
                        'day_start' => $startOfDay->toDateTimeString(),
                        'day_end' => $endOfDay->toDateTimeString(),
                        'start_lte_day_end' => $woStart <= $endOfDay,
                        'end_gte_day_start' => $woEnd >= $startOfDay,
                        'should_include' => $woStart <= $endOfDay && $woEnd >= $startOfDay,
                    ]);
                }

                $query->where(function ($q) use ($startOfDay, $endOfDay) {
                    $q->where(function ($subQ) use ($startOfDay, $endOfDay) {
                        $subQ->where('start_time', '<=', $endOfDay)
                            ->where('end_time', '>=', $startOfDay);
                    });
                });
            } elseif ($this->timeRange === 'month') {
                $startOfMonth = $selectedDate->copy()->startOfMonth();
                $endOfMonth = $selectedDate->copy()->endOfMonth()->endOfDay();
                $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                    $q->where(function ($subQ) use ($startOfMonth, $endOfMonth) {
                        $subQ->where('start_time', '<=', $endOfMonth)
                            ->where('end_time', '>=', $startOfMonth);
                    });
                });
            }
        }

        $filteredWorkOrders = $query->get();

        // Debug specific work order
        $specificWO = $allWorkOrders->where('unique_id', 'W0001_090325_O0039_082025_2040_8466936861_B')->first();
        if ($specificWO) {
            Log::info('Specific WO Debug', [
                'unique_id' => $specificWO->unique_id,
                'start_time' => $specificWO->start_time,
                'end_time' => $specificWO->end_time,
                'selected_date' => $this->selectedDate,
                'time_range' => $this->timeRange,
                'is_in_filtered' => $filteredWorkOrders->contains('id', $specificWO->id),
                'sql_query' => $query->toSql(),
                'query_bindings' => $query->getBindings(),
            ]);
        }

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
            })->toArray(),
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
