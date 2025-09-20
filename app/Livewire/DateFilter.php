<?php

namespace App\Livewire;

use Livewire\Component;
use Carbon\Carbon;

class DateFilter extends Component
{
    public $fromDate;
    public $toDate;

    public function mount()
    {
        $this->toDate = Carbon::now()->format('Y-m-d');
        $this->fromDate = Carbon::now()->subDays(30)->format('Y-m-d');
    }

    public function applyFilter()
    {
        if ($this->fromDate && $this->toDate) {
            if (Carbon::parse($this->fromDate)->gt(Carbon::parse($this->toDate))) {
                $this->addError('fromDate', 'From date cannot be later than To date.');
                return;
            }
            $this->resetErrorBag();
            // Emit event or handle filter application
            $this->dispatch('dateFilterApplied', $this->fromDate, $this->toDate);
        }
    }

    public function clearFilter()
    {
        $this->toDate = Carbon::now()->format('Y-m-d');
        $this->fromDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->resetErrorBag();
        $this->dispatch('dateFilterCleared');
    }

    public function render()
    {
        return view('livewire.date-filter');
    }
}