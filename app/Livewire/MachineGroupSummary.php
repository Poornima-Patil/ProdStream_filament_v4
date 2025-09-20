<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class MachineGroupSummary extends Component
{
    public $machineGroup;

    public function mount($machineGroup)
    {
        $this->machineGroup = $machineGroup;
    }


    public function getWorkOrderSummaryData()
    {
        $query = DB::table('work_orders')
            ->join('machines', 'work_orders.machine_id', '=', 'machines.id')
            ->where('machines.machine_group_id', $this->machineGroup->id)
            ->where('work_orders.factory_id', \Filament\Facades\Filament::getTenant()->id);

        $statusDistribution = $query
            ->selectRaw('work_orders.status, COUNT(*) as count')
            ->groupBy('work_orders.status')
            ->get()
            ->keyBy('status');

        $totalOrders = $statusDistribution->sum('count');
        $statusColors = config('work_order_status');

        // Calculate percentages for each status
        $statuses = ['Assigned', 'Start', 'Hold', 'Completed', 'Closed'];
        $statusData = [];

        foreach ($statuses as $status) {
            $count = $statusDistribution->get($status)?->count ?? 0;
            $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
            $statusData[$status] = [
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        return [
            'statusData' => $statusData,
            'totalOrders' => $totalOrders,
            'statusColors' => $statusColors
        ];
    }

    public function getQualityData()
    {
        $query = DB::table('work_orders')
            ->join('machines', 'work_orders.machine_id', '=', 'machines.id')
            ->where('machines.machine_group_id', $this->machineGroup->id)
            ->where('work_orders.factory_id', \Filament\Facades\Filament::getTenant()->id)
            ->whereIn('work_orders.status', ['Completed', 'Closed']);

        $qualityData = $query
            ->selectRaw('
                SUM(work_orders.ok_qtys) as total_ok_qtys,
                SUM(work_orders.scrapped_qtys) as total_scrapped_qtys,
                SUM(work_orders.ok_qtys + work_orders.scrapped_qtys) as total_produced
            ')
            ->first();

        $totalOk = $qualityData->total_ok_qtys ?? 0;
        $totalScrapped = $qualityData->total_scrapped_qtys ?? 0;
        $totalProduced = $qualityData->total_produced ?? 0;

        // Calculate quality rate: ((Produced - Scrapped) / Produced) × 100%
        $qualityRate = 0;
        if ($totalProduced > 0) {
            $qualityRate = (($totalProduced - $totalScrapped) / $totalProduced) * 100;
        }

        return [
            'totalOk' => $totalOk,
            'totalScrapped' => $totalScrapped,
            'totalProduced' => $totalProduced,
            'qualityRate' => $qualityRate
        ];
    }

    public function getChartData()
    {
        $query = DB::table('work_orders')
            ->join('machines', 'work_orders.machine_id', '=', 'machines.id')
            ->where('machines.machine_group_id', $this->machineGroup->id)
            ->where('work_orders.factory_id', \Filament\Facades\Filament::getTenant()->id);

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
            $statusColors['assigned'] ?? '#6b7280',
            $statusColors['start'] ?? '#3b82f6',
            $statusColors['hold'] ?? '#f59e0b',
            $statusColors['completed'] ?? '#10b981',
            $statusColors['closed'] ?? '#8b5cf6',
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
        $workOrderData = $this->getWorkOrderSummaryData();
        $qualityData = $this->getQualityData();

        return view('livewire.machine-group-summary', [
            'workOrderData' => $workOrderData,
            'qualityData' => $qualityData
        ]);
    }
}