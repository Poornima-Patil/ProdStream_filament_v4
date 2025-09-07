<?php

namespace App\Filament\Admin\Pages;

use App\Models\Machine;
use App\Models\Operator;
use App\Models\WorkOrder;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
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

    // Pagination properties
    public $currentPage = 1;

    public $perPage = 25;

    public $totalPages = 1;

    public array $pivotFilters = [
        'workOrderNo' => false,
        'machine' => false,
        'operator' => false,
        'status' => false,
        'startTime' => false,
    ];

    public array $selectedFilterValues = [
        'workOrderNo' => [],
        'machine' => [],
        'operator' => [],
        'status' => [],
        'startTime' => [],
    ];

    public array $pivotRows = [];

    public array $pivotColumns = [];

    public array $pivotValues = [];

    public array $pivotData = [];

    public bool $pivotGenerated = false;

    public function mount()
    {
        // Set default date range to last 6 months to ensure we get some data
        $this->startDate = now()->subMonths(6)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');

        // Also set legacy filter dates for backward compatibility
        $this->filterDateFrom = $this->startDate;
        $this->filterDateTo = $this->endDate;

        Log::info('Dashboard mounting with date range: '.$this->startDate.' to '.$this->endDate);
        Log::info('User factory ID: '.(Auth::user()->factory_id ?? 'not set'));

        $this->loadData();
        $this->updateRecordCounts();

        // Debug: Check if we have any work orders at all for this factory
        $totalWOsInFactory = WorkOrder::where('factory_id', Auth::user()->factory_id)->count();
        Log::info('Total work orders in factory '.Auth::user()->factory_id.': '.$totalWOsInFactory);

        if ($totalWOsInFactory == 0) {
            Log::warning('No work orders found for factory '.Auth::user()->factory_id);
        }
    }

    public function updated($property)
    {
        Log::info('Dashboard property updated: '.$property);

        // Handle new date range properties
        if (in_array($property, ['selectedStatus', 'selectedMachine', 'selectedOperator', 'startDate', 'endDate'])) {
            Log::info('Filter updated - Status: '.$this->selectedStatus.', Machine: '.$this->selectedMachine.', Operator: '.$this->selectedOperator);

            // Update legacy filter properties for backward compatibility
            $this->filterStatus = $this->selectedStatus;
            $this->filterMachine = $this->selectedMachine;
            $this->filterOperator = $this->selectedOperator;
            $this->filterDateFrom = $this->startDate;
            $this->filterDateTo = $this->endDate;

            // Reset pagination when filters change
            $this->currentPage = 1;

            $this->loadWorkOrders();
            $this->calculateStatusDistribution();
            $this->calculateKPIs();
            $this->updateRecordCounts();
        }

        // Legacy support
        if (in_array($property, ['filterStatus', 'filterMachine', 'filterOperator', 'filterDateFrom', 'filterDateTo'])) {
            Log::info('Legacy filter updated');
            // Reset pagination when filters change
            $this->currentPage = 1;
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
                'current_wo' => $currentWorkOrder ? ($currentWorkOrder->unique_id ?: 'WO-'.$currentWorkOrder->id) : null,
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
        // Apply same filters as other queries using unified filter variables
        $query = WorkOrder::query()->where('factory_id', Auth::user()->factory_id);

        // Use unified filter variables with fallback to legacy
        $dateFromFilter = $this->startDate ?: $this->filterDateFrom;
        $dateToFilter = $this->endDate ?: $this->filterDateTo;
        $statusFilter = $this->selectedStatus ?: $this->filterStatus;
        $machineFilter = $this->selectedMachine ?: $this->filterMachine;
        $operatorFilter = $this->selectedOperator ?: $this->filterOperator;

        if ($dateFromFilter) {
            $query->whereDate('created_at', '>=', $dateFromFilter);
        }
        if ($dateToFilter) {
            $query->whereDate('created_at', '<=', $dateToFilter);
        }
        if ($statusFilter && $statusFilter != 'all') {
            $query->where('status', $statusFilter);
        }
        if ($machineFilter && $machineFilter != 'all') {
            $machine = Machine::where('name', $machineFilter)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
            }
        }
        if ($operatorFilter && $operatorFilter != 'all') {
            $query->where('operator_id', $operatorFilter);
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
            $this->completionRate = $completionRate.'%';

            // Calculate overall yield and quantities from REAL data
            $totalProduced = $workOrders->sum('produced_qty') ?: ($workOrders->sum('ok_qtys') + $workOrders->sum('scrapped_qtys'));
            $totalOkQty = $workOrders->sum('ok_qtys');
            $totalPlannedQty = $workOrders->sum('qty');

            $this->totalOkQty = $totalOkQty;
            $this->totalKoQty = $totalProduced - $totalOkQty;
            $this->totalPlannedQty = $totalPlannedQty;

            if ($totalProduced > 0) {
                $overallYield = round(($totalOkQty / $totalProduced) * 100, 2);
                $this->overallYield = $overallYield.'%';

                // Calculate defect rate (1 - Yield)
                $defectRate = round(((1 - ($totalOkQty / $totalProduced)) * 100), 2);
                $this->defectRate = $defectRate.'%';
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
        // Apply ALL current filters including status filter for consistent chart display
        $query = WorkOrder::query()->where('factory_id', Auth::user()->factory_id);

        // Use unified filter variables with fallback to legacy
        $dateFromFilter = $this->startDate ?: $this->filterDateFrom;
        $dateToFilter = $this->endDate ?: $this->filterDateTo;
        $statusFilter = $this->selectedStatus ?: $this->filterStatus;
        $machineFilter = $this->selectedMachine ?: $this->filterMachine;
        $operatorFilter = $this->selectedOperator ?: $this->filterOperator;

        // Apply ALL filters including status filter
        if ($dateFromFilter) {
            $query->whereDate('created_at', '>=', $dateFromFilter);
            Log::info('Status distribution - filtering from date: '.$dateFromFilter);
        }
        if ($dateToFilter) {
            $query->whereDate('created_at', '<=', $dateToFilter);
            Log::info('Status distribution - filtering to date: '.$dateToFilter);
        }
        if ($statusFilter && $statusFilter != 'all') {
            $query->where('status', $statusFilter);
            Log::info('Status distribution - filtering by status: '.$statusFilter);
        }
        if ($machineFilter && $machineFilter != 'all') {
            $machine = Machine::where('name', $machineFilter)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
                Log::info('Status distribution - filtering by machine: '.$machineFilter.' (ID: '.$machine->id.')');
            }
        }
        if ($operatorFilter && $operatorFilter != 'all') {
            $query->where('operator_id', $operatorFilter);
            Log::info('Status distribution - filtering by operator: '.$operatorFilter);
        }

        // Apply pivot filters if active
        if ($this->hasActiveFilters()) {
            $query = $this->applyPivotFilters($query);
            Log::info('Applied pivot filters to status distribution');
        }

        $totalOrders = $query->count();
        Log::info('Status distribution - total orders after ALL filters: '.$totalOrders);

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
        $this->avgUtilization = $avgUtilization.'%';
    }

    public function getStatusDistributionData()
    {
        $this->calculateStatusDistribution();

        return $this->statusDistribution;
    }

    public function getMachineUtilizationData()
    {
        // Build the base query with ALL current filters applied
        $query = WorkOrder::query()->where('work_orders.factory_id', Auth::user()->factory_id);

        // Use unified filter variables with fallback to legacy
        $dateFromFilter = $this->startDate ?: $this->filterDateFrom;
        $dateToFilter = $this->endDate ?: $this->filterDateTo;
        $statusFilter = $this->selectedStatus ?: $this->filterStatus;
        $machineFilter = $this->selectedMachine ?: $this->filterMachine;
        $operatorFilter = $this->selectedOperator ?: $this->filterOperator;

        // Apply ALL current filters
        if ($dateFromFilter) {
            $query->whereDate('work_orders.created_at', '>=', $dateFromFilter);
        }
        if ($dateToFilter) {
            $query->whereDate('work_orders.created_at', '<=', $dateToFilter);
        }
        if ($statusFilter && $statusFilter != 'all') {
            $query->where('work_orders.status', $statusFilter);
        }
        if ($machineFilter && $machineFilter != 'all') {
            $machine = Machine::where('name', $machineFilter)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('work_orders.machine_id', $machine->id);
            }
        }
        if ($operatorFilter && $operatorFilter != 'all') {
            $query->where('work_orders.operator_id', $operatorFilter);
        }

        // Apply pivot filters if active
        if ($this->hasActiveFilters()) {
            $query = $this->applyPivotFilters($query);
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
            $machineData = ['No Data Available'];
            $utilizationData = [0];
        }

        Log::info('Machine utilization data with filters applied:', [
            'machines' => $machineData,
            'utilization' => $utilizationData,
        ]);

        return [
            'machines' => $machineData,
            'utilization' => $utilizationData,
        ];
    }

    private function loadWorkOrders()
    {
        // Work Orders (filtered)
        $query = WorkOrder::query()
            ->with(['machine', 'operator.user', 'bom.purchaseOrder.partNumber'])
            ->where('factory_id', Auth::user()->factory_id);

        // Additional constraint: if machine_id is set, ensure machine belongs to same factory
        $query->where(function ($q) {
            $q->whereNull('machine_id')
                ->orWhereHas('machine', function ($subQuery) {
                    $subQuery->where('factory_id', Auth::user()->factory_id);
                });
        });

        // Additional constraint: if operator_id is set, ensure operator belongs to same factory
        $query->where(function ($q) {
            $q->whereNull('operator_id')
                ->orWhereHas('operator', function ($subQuery) {
                    $subQuery->where('factory_id', Auth::user()->factory_id);
                });
        });

        Log::info('Loading work orders for factory: '.Auth::user()->factory_id);

        // Use the new filter variables (selectedStatus, etc.) with fallback to legacy variables
        $statusFilter = $this->selectedStatus ?: $this->filterStatus;
        $machineFilter = $this->selectedMachine ?: $this->filterMachine;
        $operatorFilter = $this->selectedOperator ?: $this->filterOperator;
        $dateFromFilter = $this->startDate ?: $this->filterDateFrom;
        $dateToFilter = $this->endDate ?: $this->filterDateTo;

        if ($statusFilter) {
            $query->where('status', $statusFilter);
            Log::info('Filtering by status: '.$statusFilter);
        }
        if ($machineFilter) {
            $machine = Machine::where('name', $machineFilter)->where('factory_id', Auth::user()->factory_id)->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
                Log::info('Filtering by machine: '.$machineFilter.' (ID: '.$machine->id.')');
            }
        }
        if ($operatorFilter) {
            $query->where('operator_id', $operatorFilter);
            Log::info('Filtering by operator: '.$operatorFilter);
        }
        if ($dateFromFilter) {
            $query->whereDate('created_at', '>=', $dateFromFilter);
            Log::info('Filtering from date: '.$dateFromFilter);
        }
        if ($dateToFilter) {
            $query->whereDate('created_at', '<=', $dateToFilter);
            Log::info('Filtering to date: '.$dateToFilter);
        }

        // Apply pivot filters if active
        if ($this->hasActiveFilters()) {
            $query = $this->applyPivotFilters($query);
            Log::info('Applied pivot filters', $this->selectedFilterValues);
        }

        // Get total count for pagination first
        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();
        $this->totalPages = max(1, ceil($totalCount / $this->perPage));

        // Ensure current page is within valid range
        if ($this->currentPage > $this->totalPages) {
            $this->currentPage = $this->totalPages;
        }
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }

        // Get the SQL query for debugging
        Log::info('Work order query SQL: '.$query->toSql());
        Log::info('Work order query bindings: ', $query->getBindings());
        Log::info('Total work orders found: '.$totalCount.', Page: '.$this->currentPage.'/'.$this->totalPages);

        // Apply pagination
        $offset = ($this->currentPage - 1) * $this->perPage;
        $workOrders = $query->offset($offset)->limit($this->perPage)->get();

        Log::info('Loaded '.$workOrders->count().' work orders for page '.$this->currentPage);

        // Debug: Check for cross-factory references
        foreach ($workOrders as $wo) {
            if ($wo->machine && $wo->machine->factory_id != $wo->factory_id) {
                Log::warning('Cross-factory machine detected - WO: '.$wo->id.' (factory: '.$wo->factory_id.'), Machine: '.$wo->machine->id.' (factory: '.$wo->machine->factory_id.')');
            }
            if ($wo->operator && $wo->operator->factory_id != $wo->factory_id) {
                Log::warning('Cross-factory operator detected - WO: '.$wo->id.' (factory: '.$wo->factory_id.'), Operator: '.$wo->operator->id.' (factory: '.$wo->operator->factory_id.')');
            }
        }

        $this->workOrders = $workOrders->map(function ($wo) {
            // Get part number and revision through the relationship chain
            $partNumber = 'N/A';
            if ($wo->bom && $wo->bom->purchaseOrder && $wo->bom->purchaseOrder->partNumber) {
                $partNumber = $wo->bom->purchaseOrder->partNumber->partnumber.
                    '_'.($wo->bom->purchaseOrder->partNumber->revision ?? '');
            }

            // Calculate real progress based on produced quantities vs total quantity
            $totalQty = $wo->qty ?? 0;
            $producedQty = ($wo->ok_qtys ?? 0) + ($wo->scrapped_qtys ?? 0);
            $progress = $totalQty > 0 ? round(($producedQty / $totalQty) * 100, 1) : 0;

            // Calculate real yield based on ok vs total produced
            $yield = $producedQty > 0 ? round((($wo->ok_qtys ?? 0) / $producedQty) * 100, 1) : 0;

            // Ensure machine and operator belong to the same factory as the work order
            $machineName = 'N/A';
            if ($wo->machine && $wo->machine->factory_id == $wo->factory_id) {
                $machineName = $wo->machine->name;
            } elseif ($wo->machine) {
                // Log a warning if machine belongs to different factory
                Log::warning('Work Order '.$wo->id.' has machine from different factory. WO Factory: '.$wo->factory_id.', Machine Factory: '.$wo->machine->factory_id);
                $machineName = 'Cross-Factory Machine';
            }

            $operatorName = 'N/A';
            if ($wo->operator && $wo->operator->factory_id == $wo->factory_id && $wo->operator->user) {
                $operatorName = $wo->operator->user->getFilamentName();
            } elseif ($wo->operator && $wo->operator->factory_id != $wo->factory_id) {
                // Log a warning if operator belongs to different factory
                Log::warning('Work Order '.$wo->id.' has operator from different factory. WO Factory: '.$wo->factory_id.', Operator Factory: '.$wo->operator->factory_id);
                $operatorName = 'Cross-Factory Operator';
            } elseif ($wo->operator && ! $wo->operator->user) {
                $operatorName = 'Unassigned';
            }

            return [
                'id' => $wo->id,
                'factory_id' => $wo->factory_id,
                'wo_number' => $wo->unique_id ?? ('WO-'.$wo->id),
                'number' => $wo->unique_id ?? $wo->id,
                'part_number' => $partNumber,
                'machine' => $machineName,
                'operator' => $operatorName,
                'status' => $wo->status,
                'progress' => $progress,
                'ok' => $wo->ok_qtys ?? 0,
                'ko' => $wo->scrapped_qtys ?? 0,
                'yield' => $yield,
                'start_time' => $wo->start_time,
            ];
        })->toArray();

        Log::info('Mapped work orders count: '.count($this->workOrders));
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

        // Reset pagination
        $this->currentPage = 1;

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

        // Reset pagination when date range changes
        $this->currentPage = 1;

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

    // Pagination methods
    public function nextPage()
    {
        if ($this->currentPage < $this->totalPages) {
            $this->currentPage++;
            $this->loadWorkOrders();
        }
    }

    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            $this->loadWorkOrders();
        }
    }

    public function goToPage($page)
    {
        $page = max(1, min($page, $this->totalPages));
        if ($page != $this->currentPage) {
            $this->currentPage = $page;
            $this->loadWorkOrders();
        }
    }

    public function changePerPage($perPage)
    {
        $this->perPage = $perPage;
        $this->currentPage = 1; // Reset to first page
        $this->loadWorkOrders();
    }

    public function getChartConfig()
    {
        // Always recalculate with current filters
        $this->calculateStatusDistribution();

        // Create a flexible status data structure that handles filtered states
        $statusData = [];

        if (! empty($this->statusDistribution)) {
            // If we have status distribution data, use it directly
            foreach ($this->statusDistribution as $status => $count) {
                // Map status names to consistent keys for the frontend
                switch ($status) {
                    case 'Completed':
                        $statusData['Completed'] = $count;
                        break;
                    case 'Start':
                    case 'In Progress':
                        $statusData['Start'] = ($statusData['Start'] ?? 0) + $count;
                        break;
                    case 'Assigned':
                        $statusData['Assigned'] = $count;
                        break;
                    case 'Hold':
                    case 'On Hold':
                        $statusData['Hold'] = ($statusData['Hold'] ?? 0) + $count;
                        break;
                    default:
                        // For any specific status (when filtered), preserve the exact name
                        $statusData[$status] = $count;
                        break;
                }
            }
        } else {
            // No data available - return empty structure
            $statusData = [];
        }

        Log::info('Chart config status data:', $statusData);

        return [
            'statusData' => $statusData,
            'machineData' => $this->getMachineUtilizationData(),
        ];
    }

    /**
     * Debug method to identify cross-factory data integrity issues
     * Call this method if you want to check for data inconsistencies
     */
    public function checkDataIntegrity()
    {
        $userFactoryId = Auth::user()->factory_id;
        Log::info('Checking data integrity for factory: '.$userFactoryId);

        // Check work orders with cross-factory machine references
        $crossFactoryMachines = WorkOrder::where('factory_id', $userFactoryId)
            ->whereHas('machine', function ($q) use ($userFactoryId) {
                $q->where('factory_id', '!=', $userFactoryId);
            })
            ->with('machine')
            ->get();

        if ($crossFactoryMachines->count() > 0) {
            Log::warning('Found '.$crossFactoryMachines->count().' work orders with cross-factory machine references');
            foreach ($crossFactoryMachines as $wo) {
                Log::warning('WO ID: '.$wo->id.', WO Factory: '.$wo->factory_id.', Machine: '.$wo->machine->name.', Machine Factory: '.$wo->machine->factory_id);
            }
        }

        // Check work orders with cross-factory operator references
        $crossFactoryOperators = WorkOrder::where('factory_id', $userFactoryId)
            ->whereHas('operator', function ($q) use ($userFactoryId) {
                $q->where('factory_id', '!=', $userFactoryId);
            })
            ->with('operator.user')
            ->get();

        if ($crossFactoryOperators->count() > 0) {
            Log::warning('Found '.$crossFactoryOperators->count().' work orders with cross-factory operator references');
            foreach ($crossFactoryOperators as $wo) {
                $operatorName = $wo->operator->user ? $wo->operator->user->getFilamentName() : 'Unknown';
                Log::warning('WO ID: '.$wo->id.', WO Factory: '.$wo->factory_id.', Operator: '.$operatorName.', Operator Factory: '.$wo->operator->factory_id);
            }
        }

        return [
            'cross_factory_machines' => $crossFactoryMachines->count(),
            'cross_factory_operators' => $crossFactoryOperators->count(),
        ];
    }

    public function updatedPivotFilters($value, $key)
    {
        // When a filter is activated, select all values by default
        if ($value) {
            $fieldMap = [
                'workOrderNo' => 'wo_number',
                'machine' => 'machine',
                'operator' => 'operator',
                'status' => 'status',
                'startTime' => 'start_time',
            ];

            if (isset($fieldMap[$key])) {
                $this->selectedFilterValues[$key] = $this->getUniqueFieldValues($fieldMap[$key]);
            }
        } else {
            // When filter is deactivated, clear selected values
            $this->selectedFilterValues[$key] = [];
        }
    }

    public function hasActiveFilters(): bool
    {
        return collect($this->pivotFilters)->contains(true);
    }

    public function getUniqueFieldValues(string $field, bool $formatDate = false): array
    {
        // Always get values from the base filtered data (before pivot filters)
        // This ensures all values remain visible regardless of pivot filter selections
        $baseWorkOrders = $this->getBaseFilteredWorkOrders();

        if (empty($baseWorkOrders)) {
            return [];
        }

        $values = collect($baseWorkOrders)
            ->pluck($field)
            ->filter(function ($value) {
                return $value !== null && $value !== '';
            })
            ->unique()
            ->values()
            ->toArray();

        if ($formatDate && $field === 'start_time') {
            $values = array_map(function ($value) {
                return date('Y-m-d', strtotime($value));
            }, $values);
            $values = array_unique($values);
            sort($values);
        }

        return $values;
    }

    private function getBaseFilteredWorkOrders()
    {
        // Get work orders with only the main filters (not pivot filters)
        // This provides the base set for showing all available filter values
        $query = WorkOrder::query()
            ->with(['machine', 'operator.user', 'bom.purchaseOrder.partNumber'])
            ->where('factory_id', Auth::user()->factory_id);

        // Apply ONLY the main filters (date range, status, machine, operator)
        // Do NOT apply pivot filters here
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

        $workOrders = $query->get();

        // Map to array format like in loadWorkOrders
        return $workOrders->map(function ($wo) {
            $partNumber = 'N/A';
            if ($wo->bom && $wo->bom->purchaseOrder && $wo->bom->purchaseOrder->partNumber) {
                $partNumber = $wo->bom->purchaseOrder->partNumber->partnumber.
                    '_'.($wo->bom->purchaseOrder->partNumber->revision ?? '');
            }

            $machineName = 'N/A';
            if ($wo->machine && $wo->machine->factory_id == $wo->factory_id) {
                $machineName = $wo->machine->name;
            }

            $operatorName = 'N/A';
            if ($wo->operator && $wo->operator->factory_id == $wo->factory_id && $wo->operator->user) {
                $operatorName = $wo->operator->user->getFilamentName();
            }

            return [
                'wo_number' => $wo->unique_id ?? ('WO-'.$wo->id),
                'machine' => $machineName,
                'operator' => $operatorName,
                'status' => $wo->status,
                'start_time' => $wo->start_time ? $wo->start_time->format('Y-m-d') : null,
                'part_number' => $partNumber,
                'ok_qty' => $wo->ok_qtys ?? 0,
                'ko_qty' => $wo->scrapped_qtys ?? 0,
                'qty' => $wo->qty ?? 0,
            ];
        })->toArray();
    }

    public function toggleFilterValue(string $filterType, string $value)
    {
        if (! isset($this->selectedFilterValues[$filterType])) {
            $this->selectedFilterValues[$filterType] = [];
        }

        $index = array_search($value, $this->selectedFilterValues[$filterType]);

        if ($index !== false) {
            // Remove value if already selected
            unset($this->selectedFilterValues[$filterType][$index]);
            $this->selectedFilterValues[$filterType] = array_values($this->selectedFilterValues[$filterType]);
        } else {
            // Add value if not selected
            $this->selectedFilterValues[$filterType][] = $value;
        }

        // Reset pagination when filters change
        $this->currentPage = 1;

        // DON'T reload work orders here - this was causing the values to disappear
        // $this->loadWorkOrders();
        // $this->calculateStatusDistribution();
        // $this->calculateKPIs();
        // $this->updateRecordCounts();

        // Only regenerate pivot table if it was already generated
        if ($this->pivotGenerated) {
            $this->generatePivotTable();
        }
    }

    public function addToPivotSection($field, $section)
    {
        // Log the incoming field for debugging
        Log::info('Adding field to pivot section', ['field' => $field, 'section' => $section]);

        // Remove from other sections first
        $this->removeFromAllPivotSections($field);

        // Add to the specified section
        switch ($section) {
            case 'rows':
                if (! in_array($field, $this->pivotRows)) {
                    $this->pivotRows[] = $field;
                }
                break;
            case 'columns':
                if (! in_array($field, $this->pivotColumns)) {
                    $this->pivotColumns[] = $field;
                }
                break;
            case 'values':
                if (! in_array($field, $this->pivotValues)) {
                    $this->pivotValues[] = $field;
                }
                break;
        }

        Log::info('Pivot sections after adding field', [
            'rows' => $this->pivotRows,
            'columns' => $this->pivotColumns,
            'values' => $this->pivotValues,
        ]);
    }

    public function removeFromPivotSection($field, $section)
    {
        switch ($section) {
            case 'rows':
                $this->pivotRows = array_values(array_filter($this->pivotRows, fn ($item) => $item !== $field));
                break;
            case 'columns':
                $this->pivotColumns = array_values(array_filter($this->pivotColumns, fn ($item) => $item !== $field));
                break;
            case 'values':
                $this->pivotValues = array_values(array_filter($this->pivotValues, fn ($item) => $item !== $field));
                break;
        }
    }

    private function removeFromAllPivotSections($field)
    {
        $this->pivotRows = array_values(array_filter($this->pivotRows, fn ($item) => $item !== $field));
        $this->pivotColumns = array_values(array_filter($this->pivotColumns, fn ($item) => $item !== $field));
        $this->pivotValues = array_values(array_filter($this->pivotValues, fn ($item) => $item !== $field));
    }

    public function generatePivotTable()
    {
        if (empty($this->pivotRows) && empty($this->pivotColumns)) {
            $this->pivotGenerated = false;
            $this->pivotData = [];

            return;
        }

        Log::info('Generating pivot table', [
            'rows' => $this->pivotRows,
            'columns' => $this->pivotColumns,
            'values' => $this->pivotValues,
        ]);

        // Get work orders data based on current filters
        $workOrdersData = $this->getFilteredWorkOrdersForPivot();

        if (empty($workOrdersData)) {
            $this->pivotGenerated = false;
            $this->pivotData = [];

            return;
        }

        $this->pivotData = $this->buildPivotTable($workOrdersData);
        $this->pivotGenerated = true;
    }

    private function getFilteredWorkOrdersForPivot()
    {
        // Use the same query logic as loadWorkOrders but get all data (no pagination)
        $query = WorkOrder::query()
            ->with(['machine', 'operator.user', 'bom.purchaseOrder.partNumber'])
            ->where('factory_id', Auth::user()->factory_id);

        // Apply existing filters
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

        // Apply pivot filters if active
        if ($this->hasActiveFilters()) {
            $query = $this->applyPivotFilters($query);
        }

        $workOrders = $query->get();

        Log::info('Retrieved work orders for pivot', ['count' => $workOrders->count()]);
        if ($workOrders->count() > 0) {
            Log::info('First work order raw data', [
                'status' => $workOrders->first()->status,
                'machine_name' => $workOrders->first()->machine?->name,
                'operator_name' => $workOrders->first()->operator?->user?->getFilamentName(),
            ]);
        }

        // Map to array format like in loadWorkOrders
        $mappedData = $workOrders->map(function ($wo) {
            $partNumber = 'N/A';
            if ($wo->bom && $wo->bom->purchaseOrder && $wo->bom->purchaseOrder->partNumber) {
                $partNumber = $wo->bom->purchaseOrder->partNumber->partnumber.
                    '_'.($wo->bom->purchaseOrder->partNumber->revision ?? '');
            }

            $machineName = 'N/A';
            if ($wo->machine && $wo->machine->factory_id == $wo->factory_id) {
                $machineName = $wo->machine->name;
            }

            $operatorName = 'N/A';
            if ($wo->operator && $wo->operator->factory_id == $wo->factory_id && $wo->operator->user) {
                $operatorName = $wo->operator->user->getFilamentName();
            }

            $mappedRow = [
                'wo_number' => $wo->unique_id ?? ('WO-'.$wo->id),
                'machine' => $machineName,
                'operator' => $operatorName,
                'status' => $wo->status, // Make sure this is correctly mapped
                'start_time' => $wo->start_time ? $wo->start_time->format('Y-m-d') : null,
                'part_number' => $partNumber,
                'ok_qty' => $wo->ok_qtys ?? 0,
                'ko_qty' => $wo->scrapped_qtys ?? 0,
                'qty' => $wo->qty ?? 0,
            ];

            // Log the first mapped row for debugging
            static $logged = false;
            if (! $logged) {
                Log::info('First mapped work order for pivot', $mappedRow);
                $logged = true;
            }

            return $mappedRow;
        })->toArray();

        Log::info('Mapped work orders for pivot', ['count' => count($mappedData)]);

        return $mappedData;
    }

    private function buildPivotTable($data)
    {
        $pivot = [];
        $totals = [];

        foreach ($data as $row) {
            // Build row key
            $rowKey = $this->buildKey($row, $this->pivotRows);

            // Build column key
            $columnKey = $this->buildKey($row, $this->pivotColumns);

            // Initialize if not exists
            if (! isset($pivot[$rowKey])) {
                $pivot[$rowKey] = [];
                $pivot[$rowKey]['_row_data'] = $this->extractKeyData($row, $this->pivotRows);
            }

            if (! isset($pivot[$rowKey][$columnKey])) {
                $pivot[$rowKey][$columnKey] = [];
                $pivot[$rowKey][$columnKey]['_column_data'] = $this->extractKeyData($row, $this->pivotColumns);
            }

            // Aggregate values
            foreach ($this->pivotValues as $valueField) {
                $value = $row[$valueField] ?? 0;

                if (! isset($pivot[$rowKey][$columnKey][$valueField])) {
                    $pivot[$rowKey][$columnKey][$valueField] = 0;
                }

                if (is_numeric($value)) {
                    $pivot[$rowKey][$columnKey][$valueField] += $value;
                } else {
                    $pivot[$rowKey][$columnKey][$valueField] = $value; // For non-numeric values, just keep the latest
                }

                // Track totals
                if (! isset($totals[$valueField])) {
                    $totals[$valueField] = 0;
                }
                if (is_numeric($value)) {
                    $totals[$valueField] += $value;
                }
            }
        }

        return [
            'data' => $pivot,
            'totals' => $totals,
            'columns' => $this->getUniqueColumnKeys($data),
            'rows' => array_keys($pivot),
        ];
    }

    private function buildKey($row, $fields)
    {
        if (empty($fields)) {
            return 'Total';
        }

        $parts = [];
        foreach ($fields as $field) {
            $value = $row[$field] ?? 'N/A';
            $parts[] = $value;

            // Log field access for debugging
            Log::info('Building key for field', [
                'field' => $field,
                'value' => $value,
                'available_keys' => array_keys($row),
            ]);
        }

        $key = implode(' | ', $parts);
        Log::info('Built key', ['fields' => $fields, 'key' => $key]);

        return $key;
    }

    private function extractKeyData($row, $fields)
    {
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $row[$field] ?? 'N/A';
        }

        return $data;
    }

    private function getUniqueColumnKeys($data)
    {
        $columns = [];
        foreach ($data as $row) {
            $columnKey = $this->buildKey($row, $this->pivotColumns);
            if (! in_array($columnKey, $columns)) {
                $columns[] = $columnKey;
            }
        }

        return $columns;
    }

    public function clearPivotTable()
    {
        $this->pivotRows = [];
        $this->pivotColumns = [];
        $this->pivotValues = [];
        $this->pivotData = [];
        $this->pivotGenerated = false;
    }

    public function isFilterValueSelected(string $filterType, string $value): bool
    {
        return in_array($value, $this->selectedFilterValues[$filterType] ?? []);
    }

    private function applyPivotFilters($query)
    {
        // Apply WorkOrderNo filter
        if (! empty($this->selectedFilterValues['workOrderNo'])) {
            $query->where(function ($q) {
                foreach ($this->selectedFilterValues['workOrderNo'] as $woNumber) {
                    $q->orWhere('unique_id', $woNumber)
                        ->orWhere('id', str_replace('WO-', '', $woNumber));
                }
            });
        }

        // Apply Machine filter
        if (! empty($this->selectedFilterValues['machine'])) {
            $machineIds = Machine::whereIn('name', $this->selectedFilterValues['machine'])
                ->where('factory_id', Auth::user()->factory_id)
                ->pluck('id');
            if ($machineIds->isNotEmpty()) {
                $query->whereIn('machine_id', $machineIds);
            }
        }

        // Apply Operator filter
        if (! empty($this->selectedFilterValues['operator'])) {
            $operatorIds = Operator::whereHas('user', function ($q) {
                $q->where(function ($query) {
                    foreach ($this->selectedFilterValues['operator'] as $operatorName) {
                        $query->orWhere('first_name', 'LIKE', '%'.$operatorName.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$operatorName.'%')
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$operatorName.'%']);
                    }
                });
            })->where('factory_id', Auth::user()->factory_id)->pluck('id');

            if ($operatorIds->isNotEmpty()) {
                $query->whereIn('operator_id', $operatorIds);
            }
        }

        // Apply Status filter
        if (! empty($this->selectedFilterValues['status'])) {
            $query->whereIn('status', $this->selectedFilterValues['status']);
        }

        // Apply StartTime filter
        if (! empty($this->selectedFilterValues['startTime'])) {
            $query->where(function ($q) {
                foreach ($this->selectedFilterValues['startTime'] as $date) {
                    $q->orWhereDate('start_time', $date);
                }
            });
        }

        return $query;
    }
}
