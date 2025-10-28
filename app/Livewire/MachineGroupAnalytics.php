<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MachineGroupAnalytics extends Component
{
    public $machineGroup;

    public function mount($machineGroup)
    {
        $this->machineGroup = $machineGroup;
    }

    public function getWorkOrderStatusDistribution()
    {
        if (! $this->machineGroup) {
            return collect();
        }

        $query = DB::table('work_orders')
            ->join('machines', 'work_orders.machine_id', '=', 'machines.id')
            ->where('machines.machine_group_id', $this->machineGroup->id);

        // Add tenant filter only if tenant is available
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant?->id) {
            $query->where('work_orders.factory_id', $tenant->id);
        }

        return $query->selectRaw('work_orders.status, COUNT(*) as count')
            ->groupBy('work_orders.status')
            ->get()
            ->keyBy('status');
    }

    public function getQualityData()
    {
        if (! $this->machineGroup) {
            return null;
        }

        $query = DB::table('work_orders')
            ->join('machines', 'work_orders.machine_id', '=', 'machines.id')
            ->where('machines.machine_group_id', $this->machineGroup->id)
            ->whereIn('work_orders.status', ['Completed', 'Closed']);

        // Add tenant filter only if tenant is available
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant?->id) {
            $query->where('work_orders.factory_id', $tenant->id);
        }

        return $query->selectRaw('
                SUM(work_orders.ok_qtys) as total_ok_qtys,
                SUM(work_orders.scrapped_qtys) as total_scrapped_qtys,
                SUM(work_orders.ok_qtys + work_orders.scrapped_qtys) as total_produced
            ')
            ->first();
    }

    public function render()
    {
        return view('livewire.machine-group-analytics');
    }
}
