<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use App\Filament\Admin\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Models\WorkOrder;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WorkOrderStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getCards(): array
    {
        // Get the current factory ID, assuming it's set in the session or context
        $factoryId = auth()->user()->factory_id;

        // Build the query and filter by factory_id and status
        $query = WorkOrder::where('factory_id', $factoryId);

        // Get the total work orders count for the current factory
        $totalWorkOrders = $query->count();

        // Get the count of work orders for each status, filtered by factory_id
        $startedWorkOrders = $query->where('status', 'Start')->count();
        $holdWorkOrders = $query->where('status', 'Hold')->count();
        $completedWorkOrders = $query->where('status', 'Completed')->count();
        $closedWorkOrders = $query->where('status', 'Closed')->count();
        $assignedWorkOrders = $query->where('status', 'Assigned')->count();

        // Return the stats cards
        return [

            Stat::make('Total orders', $this->getPageTableQuery()->count())
                ->description('Total work orders in the system'),

            Stat::make('Assigned WorkOrders', $this->getPageTableQuery()->where('status', 'Assigned')->count())
                ->description('Work orders that are assigned'),

            Stat::make('Setup WorkOrders', $this->getPageTableQuery()->where('status', 'Setup')->count())
                ->description('Work orders in setup phase'),

            Stat::make('Started WorkOrders', $this->getPageTableQuery()->where('status', 'Start')->count())
                ->description('Started work orders in the system'),

            Stat::make('Hold WorkOrders', $this->getPageTableQuery()->where('status', 'Hold')->count())
                ->description('Hold work orders in the system'),

            Stat::make('Completed WorkOrders', $this->getPageTableQuery()->where('status', 'Completed')->count())
                ->description('Completed Work Orders'),

            Stat::make('Closed WorkOrders', $this->getPageTableQuery()->where('status', 'Closed')->count())
                ->description('Closed Work Orders'),

        ];
    }

    protected function getTablePage(): string
    {
        return ListWorkOrders::class;
    }

    public function renderGanttChart()
    {
        $workOrders = $this->getPageTableQuery()->get();

        return view('work-order-stats', [
            'workOrders' => $workOrders,
        ]);
    }
}
