<?php

namespace App\Livewire\Calendar\Machines;

use App\Models\Machine;
use Carbon\Carbon;
use Livewire\Component;

class MachineScheduleGantt extends Component
{
    public Machine $machine;

    public string $viewType = 'week';

    public string $currentDate;

    public array $ganttData = [];

    public bool $isExpanded = true;
    
    public bool $showAllRows = true;

    public function mount(Machine $machine, string $viewType = 'week')
    {
        $this->machine = $machine;
        $this->viewType = $viewType;
        $this->currentDate = now()->toDateString();
        $this->loadGanttData();
    }

    public function loadGanttData()
    {
        $this->ganttData = $this->getMachineGanttData($this->viewType, $this->currentDate);
    }

    public function getMachineGanttData($viewType, $currentDate)
    {
        $date = Carbon::parse($currentDate);
        $range = $this->getDateRange($viewType, $date);
        
        // Get work orders for this machine (same logic as Advanced Gantt)
        $workOrders = $this->machine->workOrders()
            ->with(['workOrderLogs', 'operator.user'])
            ->get();

        $tasks = [];

        foreach ($workOrders as $workOrder) {

            // Create task data for horizontal Gantt bars
            $task = [
                'work_order_id' => $workOrder->id,
                'work_order_name' => $workOrder->unique_id,
                'work_order' => $workOrder, // Pass the full work order object
                'planned_start' => $workOrder->start_time ? Carbon::parse($workOrder->start_time) : null,
                'planned_end' => $workOrder->end_time ? Carbon::parse($workOrder->end_time) : null,
                'actual_start' => null,
                'actual_end' => null,
                'status' => $workOrder->status,
                'progress' => 0
            ];

            // Get actual start/end from work order logs
            $logs = $workOrder->workOrderLogs()->orderBy('created_at')->get();
            $startLog = $logs->where('status', 'Start')->first();
            $completedLog = $logs->where('status', 'Completed')->first();

            if ($startLog) {
                $task['actual_start'] = Carbon::parse($startLog->created_at);
            }
            if ($completedLog) {
                $task['actual_end'] = Carbon::parse($completedLog->created_at);
            }

            // Calculate progress
            if ($task['planned_start'] && $task['planned_end']) {
                $totalDuration = $task['planned_start']->diffInHours($task['planned_end']);
                if ($task['actual_start']) {
                    if ($task['actual_end']) {
                        $task['progress'] = 100;
                    } else {
                        $elapsed = $task['actual_start']->diffInHours(now());
                        $task['progress'] = min(100, ($elapsed / max(1, $totalDuration)) * 100);
                    }
                }
            }

            $tasks[] = $task;
        }

        return [
            'tasks' => $tasks,
            'machine' => $this->machine,
            'date_range' => $this->getDateRange($viewType, $date),
            'time_slots' => $this->getTimeSlots($viewType, $date)
        ];
    }

    public function getDateRange($viewType, $date)
    {
        switch ($viewType) {
            case 'day':
                return [
                    'start' => $date->copy()->startOfDay(),
                    'end' => $date->copy()->endOfDay()
                ];
            case 'week':
                return [
                    'start' => $date->copy()->startOfWeek(Carbon::MONDAY),
                    'end' => $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)->endOfDay()
                ];
            case 'month':
                return [
                    'start' => $date->copy()->startOfMonth(),
                    'end' => $date->copy()->endOfMonth()
                ];
            default:
                return [
                    'start' => $date->copy()->startOfDay(),
                    'end' => $date->copy()->endOfDay()
                ];
        }
    }

    public function getTimeSlots($viewType, $date)
    {
        $range = $this->getDateRange($viewType, $date);
        $slots = [];

        switch ($viewType) {
            case 'day':
                // 2-hour intervals for day view (12 slots)
                for ($hour = 0; $hour < 24; $hour += 2) {
                    $slots[] = $range['start']->copy()->addHours($hour);
                }
                break;
            case 'week':
                // Daily intervals for week view (7 slots)
                for ($day = 0; $day < 7; $day++) {
                    $slots[] = $range['start']->copy()->addDays($day);
                }
                break;
            case 'month':
                // Weekly intervals for month view
                $current = $range['start']->copy()->startOfWeek();
                while ($current->lte($range['end'])) {
                    if ($current->gte($range['start']) && $current->lte($range['end'])) {
                        $slots[] = $current->copy();
                    }
                    $current->addWeek();
                }
                // Ensure we have at least the days of the month
                if (empty($slots)) {
                    $current = $range['start']->copy();
                    while ($current->lte($range['end'])) {
                        $slots[] = $current->copy();
                        $current->addDays(3); // Every 3 days for month view
                    }
                }
                break;
        }

        return $slots;
    }

    public function changeView($viewType)
    {
        $this->viewType = $viewType;
        $this->loadGanttData();
    }

    public function navigateDate($direction)
    {
        $date = Carbon::parse($this->currentDate);

        switch ($this->viewType) {
            case 'day':
                $this->currentDate = $direction === 'next'
                    ? $date->addDay()->toDateString()
                    : $date->subDay()->toDateString();
                break;
            case 'week':
                $this->currentDate = $direction === 'next'
                    ? $date->addWeek()->toDateString()
                    : $date->subWeek()->toDateString();
                break;
            case 'month':
                $this->currentDate = $direction === 'next'
                    ? $date->addMonth()->toDateString()
                    : $date->subMonth()->toDateString();
                break;
        }

        $this->loadGanttData();
    }

    public function goToToday()
    {
        $this->currentDate = now()->toDateString();
        $this->loadGanttData();
    }

    public function jumpToDate($selectedDate)
    {
        if ($selectedDate) {
            $this->currentDate = $selectedDate;
            $this->loadGanttData();
        }
    }

    public function jumpToWeek($selectedWeek)
    {
        if ($selectedWeek) {
            if (preg_match('/(\d{4})-W(\d{2})/', $selectedWeek, $matches)) {
                $year = $matches[1];
                $week = $matches[2];
                $this->currentDate = Carbon::create($year)->setISODate($year, $week, 1)->toDateString();
            } else {
                $this->currentDate = Carbon::parse($selectedWeek)->startOfWeek()->toDateString();
            }
            $this->loadGanttData();
        }
    }

    public function jumpToMonth($selectedDate)
    {
        if ($selectedDate) {
            $this->currentDate = Carbon::parse($selectedDate)->startOfMonth()->toDateString();
            $this->loadGanttData();
        }
    }

    public function toggleExpanded()
    {
        $this->isExpanded = !$this->isExpanded;
    }
    
    public function toggleShowAllRows()
    {
        $this->showAllRows = !$this->showAllRows;
    }

    public function getDateRangeProperty()
    {
        $date = Carbon::parse($this->currentDate);

        switch ($this->viewType) {
            case 'day':
                return $date->format('F j, Y');
            case 'week':
                $start = $date->copy()->startOfWeek(Carbon::MONDAY);
                $end = $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(6);
                return $start->format('M j').' - '.$end->format('M j, Y');
            case 'month':
                return $date->format('F Y');
            default:
                return $date->format('F j, Y');
        }
    }

    public function render()
    {
        return view('livewire.calendar.machines.machine-schedule-gantt');
    }
}