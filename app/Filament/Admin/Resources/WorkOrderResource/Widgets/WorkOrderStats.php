<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use App\Filament\Admin\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Models\WorkOrder;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

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

            Card::make('Total orders', $this->getPageTableQuery()->count())
                ->description('Total work orders in the system'),

            Card::make('Started WorkOrders', $this->getPageTableQuery()->where('status', 'Start')->count())
                ->description('Started work orders in the system'),

            Card::make('Hold WorkOrders', $this->getPageTableQuery()->where('status', 'Hold')->count())
                ->description('Hold work orders in the system'),

            Card::make('Completed WorkOrders', $this->getPageTableQuery()->where('status', 'Completed')->count())
                ->description('Completed Work Orders'),

            Card::make('Closed WorkOrders', $this->getPageTableQuery()->where('status', 'Closed')->count())
                ->description('Closeed Work Orders'),

            Card::make('Assigned WorkOrders', $this->getPageTableQuery()->where('status', 'Assigned')->count())
                ->description('Work orders that are assigned'),

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
