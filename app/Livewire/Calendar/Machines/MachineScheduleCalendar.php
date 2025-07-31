<?php

namespace App\Livewire\Calendar\Machines;

use App\Models\Machine;
use Livewire\Component;
use Carbon\Carbon;

class MachineScheduleCalendar extends Component
{
    public Machine $machine;
    public string $viewType = 'week';
    public string $currentDate;
    public array $events = [];

    public function mount(Machine $machine, string $viewType = 'week')
    {
        $this->machine = $machine;
        $this->viewType = $viewType;
        $this->currentDate = now()->toDateString();
        $this->loadEvents();
    }

    public function loadEvents()
    {
        $this->events = $this->machine->getCalendarEvents($this->viewType, $this->currentDate);
    }

    public function changeView($viewType)
    {
        $this->viewType = $viewType;
        $this->loadEvents();
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

        $this->loadEvents();
    }

    public function goToToday()
    {
        $this->currentDate = now()->toDateString();
        $this->loadEvents();
    }

    public function jumpToDate($selectedDate)
    {
        if ($selectedDate) {
            $this->currentDate = $selectedDate;
            $this->loadEvents();
        }
    }

    public function jumpToWeek($selectedWeek)
    {
        if ($selectedWeek) {
            // Handle week format (YYYY-WXX) from type="week" input
            if (preg_match('/(\d{4})-W(\d{2})/', $selectedWeek, $matches)) {
                $year = $matches[1];
                $week = $matches[2];
                // Set to the Monday of the selected week using setISODate
                $this->currentDate = Carbon::create($year)->setISODate($year, $week, 1)->toDateString();
            } else {
                // Fallback for date format - set to the start of the week for the selected date
                $this->currentDate = Carbon::parse($selectedWeek)->startOfWeek()->toDateString();
            }
            $this->loadEvents();
        }
    }

    public function jumpToMonth($selectedDate)
    {
        if ($selectedDate) {
            // Set to the first day of the month for the selected date
            $this->currentDate = Carbon::parse($selectedDate)->startOfMonth()->toDateString();
            $this->loadEvents();
        }
    }

    public function getDateRangeProperty()
    {
        $date = Carbon::parse($this->currentDate);

        switch ($this->viewType) {
            case 'day':
                return $date->format('F j, Y');
            case 'week':
                $start = $date->copy()->startOfWeek(Carbon::MONDAY);
                $end = $date->copy()->startOfWeek(Carbon::MONDAY)->addDays(5); // Monday to Saturday
                return $start->format('M j') . ' - ' . $end->format('M j, Y');
            case 'month':
                return $date->format('F Y');
            default:
                return $date->format('F j, Y');
        }
    }

    public function render()
    {
        return view('livewire.calendar.machines.machine-schedule-calendar');
    }
}
