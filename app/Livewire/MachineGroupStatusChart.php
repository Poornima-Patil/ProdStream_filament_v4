<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class MachineGroupStatusChart extends Component
{
    public $record;
    public $fromDate;
    public $toDate;

    public function mount($record, $fromDate = null, $toDate = null)
    {
        $this->record = $record;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    public function getData()
    {
        if (!$this->record?->id) {
            return [
                'labels' => [],
                'datasets' => []
            ];
        }

        // Build the base query
        $query = DB::table('work_orders')
            ->join('machines', 'work_orders.machine_id', '=', 'machines.id')
            ->where('machines.machine_group_id', $this->record->id);

        // Add tenant filter only if tenant is available
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant?->id) {
            $query->where('work_orders.factory_id', $tenant->id);
        }

        // No date filtering - show all work orders

        $statusDistribution = $query
            ->selectRaw('work_orders.status, COUNT(*) as count')
            ->groupBy('work_orders.status')
            ->get()
            ->keyBy('status');

        $statuses = ['Assigned', 'Start', 'Hold', 'Completed', 'Closed'];
        $counts = [];
        $statusColors = config('work_order_status');

        foreach ($statuses as $status) {
            $counts[] = $statusDistribution->get($status)?->count ?? 0;
        }

        $colors = [
            $statusColors['assigned'],
            $statusColors['start'],
            $statusColors['hold'],
            $statusColors['completed'],
            $statusColors['closed'],
        ];

        return [
            'labels' => $statuses,
            'datasets' => [
                [
                    'label' => 'Work Orders',
                    'data' => $counts,
                    'backgroundColor' => $colors,
                    'borderWidth' => 2,
                    'hoverOffset' => 10,
                ],
            ],
        ];
    }

    public function render()
    {
        return view('livewire.machine-group-status-chart');
    }
}