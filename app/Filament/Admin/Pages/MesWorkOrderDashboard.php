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
    public $totalOkQty = 0;
    public $totalKoQty = 0;
    public $totalPlannedQty = 0;
    public $statusDistribution = [];

    public $filterStatus = '';
    public $filterMachine = '';
    public $filterOperator = '';
    public $filterDateFrom = '';
    public $filterDateTo = '';
    public $activeTab = 'real-time';

    // Date range properties
    public $startDate = '';
    public $endDate = '';
    public $selectedStatus = '';
    public $selectedMachine = '';
    public $selectedOperator = '';
    public $filteredCount = 0;
    public $totalRecords = 0;

    public function mount()
    {
        // Set default date range to last 6 months to ensure we get some data
        $this->startDate = now()->subMonths(6)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');

        // Also set legacy filter dates for backward compatibility
        $this->filterDateFrom = $this->startDate;
        $this->filterDateTo = $this->endDate;

        Log::info('Dashboard mounting with date range: ' . $this->startDate . ' to ' . $this->endDate);
        Log::info('User factory ID: ' . (Auth::user()->factory_id ?? 'not set'));

        $this->loadData();
        $this->updateRecordCounts();

        // Debug: Check if we have any work orders at all for this factory
        $totalWOsInFactory = WorkOrder::where('factory_id', Auth::user()->factory_id)->count();
        Log::info('Total work orders in factory ' . Auth::user()->factory_id . ': ' . $totalWOsInFactory);

        if ($totalWOsInFactory == 0) {
            Log::warning('No work orders found for factory ' . Auth::user()->factory_id);
        }
    }

    public function updated($property)
    {
        Log::info('Dashboard property updated: ' . $property);

        // Handle new date range properties
        if (in_array($property, ['selectedStatus', 'selectedMachine', 'selectedOperator', 'startDate', 'endDate'])) {
            Log::info('Filter updated - Status: ' . $this->selectedStatus . ', Machine: ' . $this->selectedMachine . ', Operator: ' . $this->selectedOperator);

            // Update legacy filter properties for backward compatibility
            $this->filterStatus = $this->selectedStatus;
            $this->filterMachine = $this->selectedMachine;
            $this->filterOperator = $this->selectedOperator;
            $this->filterDateFrom = $this->startDate;
            $this->filterDateTo = $this->endDate;

            $this->loadWorkOrders();
            $this->calculateStatusDistribution();
            $this->calculateKPIs();
            $this->updateRecordCounts();
        }

        // Legacy support
        if (in_array($property, ['filterStatus', 'filterMachine', 'filterOperator', 'filterDateFrom', 'filterDateTo'])) {
            Log::info('Legacy filter updated');
            $this->loadWorkOrders();
            $this->calculateStatusDistribution();
            $this->calculateKPIs();
            $this->updateRecordCounts();
        }
    }

    private function loadData()
    {
        // Machines
        $machines = Machine::where('factory_id', Auth::user()->factory_id)->get();
        $this->machines = $machines->map(function ($machine) {
            return [
                'name' => $machine->name,
                'status' => $machine->status ?? 'IDLE',
            ];
        })->toArray();

        // Machine Statuses for the monitor - REAL data from database
        $this->machineStatuses = $machines->map(function ($machine) {
            // Get real work orders for this machine
            $machineWorkOrders = WorkOrder::where('factory_id', Auth::user()->factory_id)
                ->where('machine_id', $machine->id);

            // Apply date filters
            if ($this->filterDateFrom) {
                $machineWorkOrders->whereDate('created_at', '>=', $this->filterDateFrom);
            }
            if ($this->filterDateTo) {
                $machineWorkOrders->whereDate('created_at', '<=', $this->filterDateTo);
            }

            $totalOrders = $machineWorkOrders->count();
            $completedOrders = (clone $machineWorkOrders)->where('status', 'Completed')->count();
            $activeOrders = (clone $machineWorkOrders)->whereIn('status', ['Start', 'In Progress'])->count();
            $currentWorkOrder = (clone $machineWorkOrders)->whereIn('status', ['Start', 'In Progress'])->first();

            // Determine machine status based on work orders
            $status = 'IDLE';
            if ($activeOrders > 0) {
                $status = 'RUNNING';
            } elseif ($machine->status === 'Maintenance') {
                $status = 'MAINTENANCE';
            }

            // Calculate real utilization
            $utilization = $totalOrders > 0 ?
                round((($completedOrders + ($activeOrders * 0.5)) / $totalOrders) * 100) : 0;

            return [
                'name' => $machine->name,
                'status' => $status,
                'utilization' => $utilization,
                'current_wo' => $currentWorkOrder ? ($currentWorkOrder->unique_id ?: 'WO-' . $currentWorkOrder->id) : null,
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

        // Calculate real KPI values based on current data
        $this->calculateKPIs();
    }

    private function calculateKPIs()
    {
        // Apply same filters as other queries
        $query = WorkOrder::query()->where('factory_id', Auth::user()->factory_id);
        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }
        if ($this->filterStatus && $this->filterStatus != 'all') {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterMachine && $this->filterMachine != 'all') {
            $machine = Machine::where('name', $this->filterMachine)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
            }
        }
        if ($this->filterOperator && $this->filterOperator != 'all') {
            $query->where('operator_id', $this->filterOperator);
        }

        $workOrders = $query->get();
        $totalOrders = $workOrders->count();
        $this->totalWorkOrders = $totalOrders;

        // Calculate all status counts from the same filtered query
        $completedOrders = $workOrders->where('status', 'Completed')->count();
        $inProgressOrders = $workOrders->whereIn('status', ['In Progress', 'Start'])->count();
        $pendingOrders = $workOrders->where('status', 'Assigned')->count();
        $onHoldOrders = $workOrders->where('status', 'Hold')->count();

        $this->completedOrders = $completedOrders;
        $this->inProgressOrders = $inProgressOrders;
        $this->pendingOrders = $pendingOrders;
        $this->onHold = $onHoldOrders;

        // Calculate real-time stats
        $this->activeOrders = $inProgressOrders; // Active = In Progress
        $this->completedToday = $workOrders->where('status', 'Completed')
            ->filter(function ($wo) {
                return $wo->end_time && \Carbon\Carbon::parse($wo->end_time)->isToday();
            })->count();

        if ($totalOrders > 0) {
            // Calculate completion rate (Completed orders / Total orders)
            $completionRate = round(($completedOrders / $totalOrders) * 100, 2);
            $this->completionRate = $completionRate . '%';

            // Calculate overall yield and quantities from REAL data
            $totalProduced = $workOrders->sum('produced_qty') ?: ($workOrders->sum('ok_qtys') + $workOrders->sum('scrapped_qtys'));
            $totalOkQty = $workOrders->sum('ok_qtys');
            $totalPlannedQty = $workOrders->sum('qty');

            $this->totalOkQty = $totalOkQty;
            $this->totalKoQty = $totalProduced - $totalOkQty;
            $this->totalPlannedQty = $totalPlannedQty;

            if ($totalProduced > 0) {
                $overallYield = round(($totalOkQty / $totalProduced) * 100, 2);
                $this->overallYield = $overallYield . '%';

                // Calculate defect rate (1 - Yield)
                $defectRate = round(((1 - ($totalOkQty / $totalProduced)) * 100), 2);
                $this->defectRate = $defectRate . '%';
            } else {
                $this->overallYield = '0%';
                $this->defectRate = '0%';
            }
        } else {
            // No work orders found - set everything to 0
            $this->overallYield = '0%';
            $this->completionRate = '0%';
            $this->defectRate = '0%';
            $this->totalWorkOrders = 0;
            $this->completedOrders = 0;
            $this->inProgressOrders = 0;
            $this->pendingOrders = 0;
            $this->activeOrders = 0;
            $this->completedToday = 0;
            $this->totalOkQty = 0;
            $this->totalKoQty = 0;
            $this->totalPlannedQty = 0;
        }

        // Calculate real machine utilization from actual work order data
        $this->calculateRealMachineUtilization();
    }

    private function calculateStatusDistribution()
    {
        // Apply same filters as other queries
        $query = WorkOrder::query()->where('factory_id', Auth::user()->factory_id);

        // Apply filters in the same order as other methods
        if ($this->startDate || $this->filterDateFrom) {
            $dateFrom = $this->startDate ?: $this->filterDateFrom;
            $query->whereDate('created_at', '>=', $dateFrom);
            Log::info('Status distribution - filtering from date: ' . $dateFrom);
        }
        if ($this->endDate || $this->filterDateTo) {
            $dateTo = $this->endDate ?: $this->filterDateTo;
            $query->whereDate('created_at', '<=', $dateTo);
            Log::info('Status distribution - filtering to date: ' . $dateTo);
        }

        // Apply status filter - the chart should reflect the filtered results
        if ($this->selectedStatus || $this->filterStatus) {
            $statusFilter = $this->selectedStatus ?: $this->filterStatus;
            if ($statusFilter && $statusFilter != 'all') {
                $query->where('status', $statusFilter);
                Log::info('Status distribution - filtering by status: ' . $statusFilter);
            }
        }

        // Apply machine and operator filters
        if ($this->selectedMachine || $this->filterMachine) {
            $machineName = $this->selectedMachine ?: $this->filterMachine;
            $machine = Machine::where('name', $machineName)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
                Log::info('Status distribution - filtering by machine: ' . $machineName . ' (ID: ' . $machine->id . ')');
            }
        }

        if ($this->selectedOperator || $this->filterOperator) {
            $operatorId = $this->selectedOperator ?: $this->filterOperator;
            $query->where('operator_id', $operatorId);
            Log::info('Status distribution - filtering by operator: ' . $operatorId);
        }

        $totalOrders = $query->count();
        Log::info('Status distribution - total orders after filters: ' . $totalOrders);

        if ($totalOrders > 0) {
            $statusCounts = (clone $query)->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            Log::info('Status distribution - raw counts from database:', $statusCounts);

            $this->statusDistribution = [];
            foreach ($statusCounts as $status => $count) {
                $this->statusDistribution[$status] = $count;
            }

            Log::info('Status distribution - final distribution:', $this->statusDistribution);
        } else {
            // No data - empty distribution
            $this->statusDistribution = [];
            Log::info('Status distribution - no work orders found, using empty distribution');
        }
    }

    private function calculateRealMachineUtilization()
    {
        // Get machines for this factory and calculate real utilization
        $machines = Machine::where('factory_id', Auth::user()->factory_id)->get();
        $totalUtilization = 0;
        $machineCount = 0;

        foreach ($machines as $machine) {
            // Calculate utilization based on work orders assigned to this machine
            $machineWorkOrders = WorkOrder::where('factory_id', Auth::user()->factory_id)
                ->where('machine_id', $machine->id);

            // Apply date filters
            if ($this->filterDateFrom) {
                $machineWorkOrders->whereDate('created_at', '>=', $this->filterDateFrom);
            }
            if ($this->filterDateTo) {
                $machineWorkOrders->whereDate('created_at', '<=', $this->filterDateTo);
            }

            $totalOrders = $machineWorkOrders->count();
            $completedOrders = (clone $machineWorkOrders)->where('status', 'Completed')->count();
            $activeOrders = (clone $machineWorkOrders)->whereIn('status', ['Start', 'In Progress'])->count();

            // Calculate utilization as percentage of work completion + active work
            $utilization = $totalOrders > 0 ?
                round((($completedOrders + ($activeOrders * 0.5)) / $totalOrders) * 100) : 0;

            $totalUtilization += $utilization;
            $machineCount++;
        }

        // Calculate average utilization
        $avgUtilization = $machineCount > 0 ? round($totalUtilization / $machineCount) : 0;
        $this->avgUtilization = $avgUtilization . '%';
    }

    public function getStatusDistributionData()
    {
        $this->calculateStatusDistribution();
        return $this->statusDistribution;
    }

    public function getMachineUtilizationData()
    {
        // Build the base query with current filters (same as used for work orders)
        $query = WorkOrder::query()->where('work_orders.factory_id', Auth::user()->factory_id);

        // Apply ALL current filters
        if ($this->filterDateFrom) {
            $query->whereDate('work_orders.created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('work_orders.created_at', '<=', $this->filterDateTo);
        }
        if ($this->filterStatus) {
            $query->where('work_orders.status', $this->filterStatus);
        }
        if ($this->filterMachine) {
            $machine = Machine::where('name', $this->filterMachine)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('work_orders.machine_id', $machine->id);
            }
        }
        if ($this->filterOperator) {
            $query->where('work_orders.operator_id', $this->filterOperator);
        }

        // Get machines that have work orders matching the filters
        $machineUtilization = $query
            ->join('machines', 'work_orders.machine_id', '=', 'machines.id')
            ->select(
                'machines.id',
                'machines.name',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(CASE WHEN work_orders.status = "Completed" THEN 1 ELSE 0 END) as completed_orders'),
                DB::raw('SUM(CASE WHEN work_orders.status IN ("Start", "In Progress") THEN 1 ELSE 0 END) as active_orders')
            )
            ->groupBy('machines.id', 'machines.name')
            ->get();

        $machineData = [];
        $utilizationData = [];

        foreach ($machineUtilization as $machine) {
            $totalOrders = $machine->total_orders;
            $completedOrders = $machine->completed_orders;
            $activeOrders = $machine->active_orders;

            // Calculate utilization: completed orders + 50% weight for active orders
            $utilization = $totalOrders > 0 ?
                round((($completedOrders + ($activeOrders * 0.5)) / $totalOrders) * 100) : 0;

            $machineData[] = $machine->name;
            $utilizationData[] = $utilization;
        }

        // If no machines found (no work orders match filters), show placeholder
        if (empty($machineData)) {
            $machineData = ['No Machines'];
            $utilizationData = [0];
        }

        return [
            'machines' => $machineData,
            'utilization' => $utilizationData,
        ];
    }

    private function loadWorkOrders()
    {
        // Work Orders (filtered)
        $query = WorkOrder::query()->with(['machine', 'operator'])->where('factory_id', Auth::user()->factory_id);

        Log::info('Loading work orders for factory: ' . Auth::user()->factory_id);

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
            Log::info('Filtering by status: ' . $this->filterStatus);
        }
        if ($this->filterMachine) {
            $machine = Machine::where('name', $this->filterMachine)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
                Log::info('Filtering by machine: ' . $this->filterMachine . ' (ID: ' . $machine->id . ')');
            }
        }
        if ($this->filterOperator) {
            $query->where('operator_id', $this->filterOperator);
            Log::info('Filtering by operator: ' . $this->filterOperator);
        }
        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
            Log::info('Filtering from date: ' . $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
            Log::info('Filtering to date: ' . $this->filterDateTo);
        }

        // Get the SQL query for debugging
        Log::info('Work order query SQL: ' . $query->toSql());
        Log::info('Work order query bindings: ', $query->getBindings());

        $workOrders = $query->with(['machine', 'operator.user', 'bom.purchaseOrder.partNumber'])->limit(20)->get();

        Log::info('Found ' . $workOrders->count() . ' work orders');

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
                'id' => $wo->id, // Add the database ID for linking
                'factory_id' => $wo->factory_id, // Add factory ID for the URL
                'wo_number' => $wo->unique_id ?? ('WO-' . $wo->id),
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

        Log::info('Mapped work orders count: ' . count($this->workOrders));
        if (count($this->workOrders) > 0) {
            Log::info('First work order: ', $this->workOrders[0]);
        }
    }

    public function clearFilters()
    {
        $this->filterStatus = '';
        $this->filterMachine = '';
        $this->filterOperator = '';
        $this->selectedStatus = '';
        $this->selectedMachine = '';
        $this->selectedOperator = '';

        // Reset date range to default (this month)
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->filterDateFrom = $this->startDate;
        $this->filterDateTo = $this->endDate;

        $this->loadWorkOrders();
        $this->calculateStatusDistribution();
        $this->calculateKPIs();
        $this->updateRecordCounts();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;

        // If switching to overview tab, recalculate status distribution
        if ($tab === 'overview') {
            $this->calculateStatusDistribution();
        }
    }

    public function setDateRange($period)
    {
        switch ($period) {
            case 'today':
                $this->startDate = now()->format('Y-m-d');
                $this->endDate = now()->format('Y-m-d');
                break;
            case 'week':
                $this->startDate = now()->startOfWeek()->format('Y-m-d');
                $this->endDate = now()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->startDate = now()->startOfMonth()->format('Y-m-d');
                $this->endDate = now()->endOfMonth()->format('Y-m-d');
                break;
            case 'quarter':
                $this->startDate = now()->startOfQuarter()->format('Y-m-d');
                $this->endDate = now()->endOfQuarter()->format('Y-m-d');
                break;
            case 'year':
                $this->startDate = now()->startOfYear()->format('Y-m-d');
                $this->endDate = now()->endOfYear()->format('Y-m-d');
                break;
            case 'all':
                $this->startDate = '';
                $this->endDate = '';
                break;
        }

        // Update legacy properties
        $this->filterDateFrom = $this->startDate;
        $this->filterDateTo = $this->endDate;

        // Refresh data
        $this->loadWorkOrders();
        $this->calculateStatusDistribution();
        $this->calculateKPIs();
        $this->updateRecordCounts();
    }

    private function updateRecordCounts()
    {
        // Get total records without filters
        $this->totalRecords = WorkOrder::where('factory_id', Auth::user()->factory_id)->count();

        // Get filtered records
        $query = WorkOrder::query()->where('factory_id', Auth::user()->factory_id);

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }
        if ($this->selectedStatus) {
            $query->where('status', $this->selectedStatus);
        }
        if ($this->selectedMachine) {
            $machine = Machine::where('name', $this->selectedMachine)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
            }
        }
        if ($this->selectedOperator) {
            $query->where('operator_id', $this->selectedOperator);
        }

        $this->filteredCount = $query->count();
    }

    public function getChartConfig()
    {
        $this->calculateStatusDistribution();
        return [
            'statusData' => [
                'completed' => $this->statusDistribution['Completed'] ?? 0,
                'start' => $this->statusDistribution['Start'] ?? 0,
                'assigned' => $this->statusDistribution['Assigned'] ?? 0,
                'hold' => $this->statusDistribution['Hold'] ?? ($this->statusDistribution['On Hold'] ?? 0)
            ],
            'machineData' => $this->getMachineUtilizationData()
        ];
    }
}
