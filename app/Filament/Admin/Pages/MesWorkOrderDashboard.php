<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

use App\Models\WorkOrder;
use App\Models\Machine;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MesWorkOrderDashboard extends Page
{
    protected static string $view = 'filament.admin.pages.mes-work-order-dashboard';
    protected static ?string $navigationGroup = 'Work Order Reports';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'MES Work Order Dashboard';

    public $activeOrders = 0;
    public $pendingOrders = 0;
    public $completedToday = 0;
    public $avgUtilization = '0%';
    public $machines = [];
    public $machineStatuses = [];
    public $workOrders = [];
    public $operators = [];
    public $overallYield = '0%';
    public $completionRate = '0%';
    public $defectRate = '0%';
    public $totalWorkOrders = 0;
    public $completedOrders = 0;
    public $inProgressOrders = 0;
    public $onHold = 0;
    public $statusDistribution = [];

    public $filterStatus = '';
    public $filterMachine = '';
    public $filterOperator = '';
    public $filterDateFrom = '';
    public $filterDateTo = '';
    public $activeTab = 'real-time';

    public function mount()
    {
        // Set default date range to last month
        $this->filterDateFrom = now()->subMonth()->format('Y-m-d');
        $this->filterDateTo = now()->format('Y-m-d');

        $this->loadData();
    }

    public function updated($property)
    {
        if (in_array($property, ['filterStatus', 'filterMachine', 'filterOperator', 'filterDateFrom', 'filterDateTo'])) {
            $this->loadWorkOrders();
            $this->loadSummaryStats(); // Update summary stats when filters change
        }
    }

    private function loadData()
    {
        $this->loadSummaryStats();

        // Machines
        $machines = Machine::where('factory_id', Auth::user()->factory_id)->get();
        $this->machines = $machines->map(function ($machine) {
            return [
                'name' => $machine->name,
                'status' => $machine->status ?? 'IDLE',
            ];
        })->toArray();

        // Machine Statuses for the monitor
        $this->machineStatuses = $machines->map(function ($machine) {
            $utilization = rand(20, 95);
            $statuses = ['RUNNING', 'IDLE', 'MAINTENANCE'];
            $status = $machine->status ?? $statuses[array_rand($statuses)];

            return [
                'name' => $machine->name,
                'status' => $status,
                'utilization' => $utilization,
                'current_work_order' => $status === 'RUNNING' ? 'WO-' . rand(2024, 2030) . '-' . str_pad(rand(1, 999), 4, '0', STR_PAD_LEFT) : null,
            ];
        })->toArray();

        // Operators
        $operators = Operator::with('user')->where('factory_id', Auth::user()->factory_id)->get();
        $this->operators = $operators->map(function ($operator) {
            return [
                'id' => $operator->id,
                'name' => $operator->user ? $operator->user->getFilamentName() : 'Unknown User',
            ];
        })->toArray();

        $this->loadWorkOrders();

        // Calculate status distribution
        $this->calculateStatusDistribution();

        // Overall stats (placeholders)
        $this->overallYield = '93.10%';
        $this->completionRate = '30.91%';
        $this->defectRate = '6.90%';
        $this->avgUtilization = '63%';
    }

    private function calculateStatusDistribution()
    {
        // Apply same filters as other queries
        $query = WorkOrder::query()->where('factory_id', Auth::user()->factory_id);
        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        $totalOrders = $query->count();
        Log::info('Total work orders for factory: ' . $totalOrders);

        if ($totalOrders > 0) {
            $statusCounts = (clone $query)->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            Log::info('Status counts from database:', $statusCounts);

            $this->statusDistribution = [];
            foreach ($statusCounts as $status => $count) {
                $this->statusDistribution[$status] = round(($count / $totalOrders) * 100, 1);
            }

            Log::info('Calculated status distribution:', $this->statusDistribution);
        } else {
            // Fallback data for demo purposes
            $this->statusDistribution = [
                'Completed' => 20.7,
                'Start' => 17.3,
                'Hold' => 14.7,
                'Quality_Check' => 14.0,
                'Assigned' => 16.7,
                'Cancelled' => 16.7
            ];

            Log::info('Using fallback status distribution:', $this->statusDistribution);
        }
    }

    public function getStatusDistributionData()
    {
        $this->calculateStatusDistribution();
        return $this->statusDistribution;
    }

    public function getMachineUtilizationData()
    {
        // Return sample machine utilization data
        return [
            'machines' => ['LATHE-001', 'MILL-002', 'MILL-001', 'CNC-001', 'ASSEMBLY-001', 'PRESS-002', 'CNC-002', 'LATHE-002', 'PRESS-001', 'CNC-003'],
            'utilization' => [25, 30, 28, 26, 35, 32, 40, 30, 22, 45],
            'yield' => [95, 92, 88, 85, 90, 87, 93, 89, 86, 91]
        ];
    }

    private function loadSummaryStats()
    {
        // Apply filters to summary stats
        $query = WorkOrder::query()->where('factory_id', Auth::user()->factory_id);
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterMachine) {
            $machine = Machine::where('name', $this->filterMachine)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
            }
        }
        if ($this->filterOperator) {
            $query->where('operator_id', $this->filterOperator);
        }
        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        // Calculate filtered stats
        $this->activeOrders = (clone $query)->where('status', 'Start')->count();
        $this->pendingOrders = (clone $query)->where('status', 'Assigned')->count();
        $this->completedToday = (clone $query)->whereDate('end_time', today())->where('status', 'Completed')->count();
        $this->totalWorkOrders = (clone $query)->count();
        $this->completedOrders = (clone $query)->where('status', 'Completed')->count();
        $this->inProgressOrders = (clone $query)->where('status', 'Start')->count();
        $this->onHold = (clone $query)->where('status', 'Hold')->count();
    }

    private function loadWorkOrders()
    {
        // Work Orders (filtered)
        $query = WorkOrder::query()->with(['machine', 'operator'])->where('factory_id', Auth::user()->factory_id);
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterMachine) {
            $machine = Machine::where('name', $this->filterMachine)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
            }
        }
        if ($this->filterOperator) {
            $query->where('operator_id', $this->filterOperator);
        }
        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }
        $workOrders = $query->with(['machine', 'operator.user', 'bom.purchaseOrder.partNumber'])->limit(20)->get();
        $this->workOrders = $workOrders->map(function ($wo) {
            // Get part number and revision through the relationship chain
            $partNumber = 'N/A';
            if ($wo->bom && $wo->bom->purchaseOrder && $wo->bom->purchaseOrder->partNumber) {
                $partNumber = $wo->bom->purchaseOrder->partNumber->partnumber .
                    '_' . ($wo->bom->purchaseOrder->partNumber->revision ?? '');
            }

            // Calculate real progress based on produced quantities vs total quantity
            $totalQty = $wo->qty ?? 0;
            $producedQty = ($wo->ok_qtys ?? 0) + ($wo->scrapped_qtys ?? 0);
            $progress = $totalQty > 0 ? round(($producedQty / $totalQty) * 100, 1) : 0;

            // Calculate real yield based on ok vs total produced
            $yield = $producedQty > 0 ? round((($wo->ok_qtys ?? 0) / $producedQty) * 100, 1) : 0;

            return [
                'number' => $wo->unique_id ?? $wo->id,
                'part_number' => $partNumber,
                'machine' => optional($wo->machine)->name ?? 'N/A',
                'operator' => $wo->operator && $wo->operator->user ? $wo->operator->user->getFilamentName() : 'N/A',
                'status' => $wo->status,
                'progress' => $progress,
                'ok' => $wo->ok_qtys ?? 0,
                'ko' => $wo->scrapped_qtys ?? 0,
                'yield' => $yield,
            ];
        })->toArray();
    }

    public function clearFilters()
    {
        $this->filterStatus = '';
        $this->filterMachine = '';
        $this->filterOperator = '';
        // Reset date range to default (last month)
        $this->filterDateFrom = now()->subMonth()->format('Y-m-d');
        $this->filterDateTo = now()->format('Y-m-d');
        $this->loadWorkOrders();
        $this->loadSummaryStats();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;

        // If switching to overview tab, recalculate status distribution
        if ($tab === 'overview') {
            $this->calculateStatusDistribution();
        }
    }
}
