<?php

namespace App\Filament\Admin\Pages;

use App\Exports\BomExport;
use App\Exports\SalesOrderExport;
use App\Exports\WorkOrderExport;
use App\Models\CustomerInformation;
use App\Models\Machine;
use App\Models\MachineGroup;
use App\Models\Operator;
use App\Models\OperatorProficiency;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class DownloadExcelReports extends Page
{
    protected string $view = 'filament.admin.pages.download-excel-reports';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Download Excel Reports';

    protected static ?string $title = 'Download Excel Reports';

    // Work Order filters
    public $woDateFrom = '';

    public $woDateTo = '';

    public $woStatus = '';

    public $woMachine = '';

    public $woOperator = '';

    // BOM filters
    public $bomDateFrom = '';

    public $bomDateTo = '';

    public $bomMachineGroup = '';

    public $bomOperatorProficiency = '';

    public $bomStatus = '';

    public $bomTargetCompletionDate = '';

    // Sales Order filters
    public $soDateFrom = '';

    public $soDateTo = '';

    public $soCustomer = '';

    public $activeTab = 'work-orders';

    public function mount()
    {
        // Set default date range to last month
        $this->woDateFrom = now()->subMonth()->format('Y-m-d');
        $this->woDateTo = now()->format('Y-m-d');

        $this->bomDateFrom = now()->subMonth()->format('Y-m-d');
        $this->bomDateTo = now()->format('Y-m-d');

        $this->soDateFrom = now()->subMonth()->format('Y-m-d');
        $this->soDateTo = now()->format('Y-m-d');
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function downloadWorkOrderReport()
    {
        $filters = [
            'date_from' => $this->woDateFrom,
            'date_to' => $this->woDateTo,
            'status' => $this->woStatus,
            'machine' => $this->woMachine,
            'operator' => $this->woOperator,
        ];

        $filename = 'work_orders_'.now()->format('Y_m_d_H_i_s').'.xlsx';

        return Excel::download(new WorkOrderExport($filters), $filename);
    }

    public function downloadWorkOrderReportCsv()
    {
        $filters = [
            'date_from' => $this->woDateFrom,
            'date_to' => $this->woDateTo,
            'status' => $this->woStatus,
            'machine' => $this->woMachine,
            'operator' => $this->woOperator,
        ];

        $filename = 'work_orders_'.now()->format('Y_m_d_H_i_s').'.csv';

        return Excel::download(new WorkOrderExport($filters), $filename);
    }

    public function downloadBomReport()
    {
        $filters = [
            'date_from' => $this->bomDateFrom,
            'date_to' => $this->bomDateTo,
            'machine_group' => $this->bomMachineGroup,
            'operator_proficiency' => $this->bomOperatorProficiency,
            'status' => $this->bomStatus,
            'target_completion_date' => $this->bomTargetCompletionDate,
        ];

        $filename = 'bom_report_'.now()->format('Y_m_d_H_i_s').'.xlsx';

        return Excel::download(new BomExport($filters), $filename);
    }

    public function downloadBomReportCsv()
    {
        $filters = [
            'date_from' => $this->bomDateFrom,
            'date_to' => $this->bomDateTo,
            'machine_group' => $this->bomMachineGroup,
            'operator_proficiency' => $this->bomOperatorProficiency,
            'status' => $this->bomStatus,
            'target_completion_date' => $this->bomTargetCompletionDate,
        ];

        $filename = 'bom_report_'.now()->format('Y_m_d_H_i_s').'.csv';

        return Excel::download(new BomExport($filters), $filename);
    }

    public function downloadSalesOrderReport()
    {
        $filters = [
            'date_from' => $this->soDateFrom,
            'date_to' => $this->soDateTo,
            'customer' => $this->soCustomer,
        ];

        $filename = 'sales_order_report_'.now()->format('Y_m_d_H_i_s').'.xlsx';

        return Excel::download(new SalesOrderExport($filters), $filename);
    }

    public function downloadSalesOrderReportCsv()
    {
        $filters = [
            'date_from' => $this->soDateFrom,
            'date_to' => $this->soDateTo,
            'customer' => $this->soCustomer,
        ];

        $filename = 'sales_order_report_'.now()->format('Y_m_d_H_i_s').'.csv';

        return Excel::download(new SalesOrderExport($filters), $filename);
    }

    public function clearWorkOrderFilters()
    {
        $this->woDateFrom = now()->subMonth()->format('Y-m-d');
        $this->woDateTo = now()->format('Y-m-d');
        $this->woStatus = '';
        $this->woMachine = '';
        $this->woOperator = '';
    }

    public function clearBomFilters()
    {
        $this->bomDateFrom = now()->subMonth()->format('Y-m-d');
        $this->bomDateTo = now()->format('Y-m-d');
        $this->bomMachineGroup = '';
        $this->bomOperatorProficiency = '';
        $this->bomStatus = '';
        $this->bomTargetCompletionDate = '';
    }

    public function clearSalesOrderFilters()
    {
        $this->soDateFrom = now()->subMonth()->format('Y-m-d');
        $this->soDateTo = now()->format('Y-m-d');
        $this->soCustomer = '';
    }

    public function getMachinesProperty()
    {
        return Machine::where('factory_id', Auth::user()->factory_id)
            ->pluck('name', 'name')
            ->toArray();
    }

    public function getOperatorsProperty()
    {
        return Operator::with('user')
            ->where('factory_id', Auth::user()->factory_id)
            ->get()
            ->mapWithKeys(function ($operator) {
                return [$operator->id => $operator->user ? $operator->user->getFilamentName() : 'Unknown'];
            })
            ->toArray();
    }

    public function getMachineGroupsProperty()
    {
        return MachineGroup::where('factory_id', Auth::user()->factory_id)
            ->pluck('group_name', 'id')
            ->toArray();
    }

    public function getOperatorProficienciesProperty()
    {
        return OperatorProficiency::where('factory_id', Auth::user()->factory_id)
            ->pluck('proficiency', 'id')
            ->toArray();
    }

    public function getWorkOrderStatusesProperty()
    {
        return [
            'Assigned' => 'Assigned',
            'Start' => 'Started',
            'In Progress' => 'In Progress',
            'Hold' => 'On Hold',
            'Completed' => 'Completed',
        ];
    }

    public function getBomStatusesProperty()
    {
        return [
            '1' => 'Active',
            '0' => 'Inactive',
        ];
    }

    public function getCustomersProperty()
    {
        return CustomerInformation::where('factory_id', Auth::user()->factory_id)
            ->pluck('name', 'id')
            ->toArray();
    }
}
