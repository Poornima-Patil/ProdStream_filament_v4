<?php

namespace App\Livewire;

use App\Models\WorkOrder;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AutoPivotTableBuilder extends Component
{
    protected $layout = 'components.layouts.app';

    // Pivot configuration
    public $selectedRows = [];

    public $selectedColumns = [];

    public $selectedValues = [];

    public $aggregationFunction = 'sum';

    public $dateRange = 'last_month';

    public $startDate;

    public $endDate;

    // Data
    public $workOrderData = [];

    public $availableFields = [];

    public $pivotResult = [];

    // Filter values - exactly like CSV version
    public $activeFilters = [];

    // UI state
    public $showPivotTable = false;

    public $autoDownloadCsv = true;

    protected $rules = [
        'selectedRows' => 'array',
        'selectedColumns' => 'array',
        'selectedValues' => 'array|min:1',
        'aggregationFunction' => 'required|in:sum,count,avg,min,max',
        'dateRange' => 'required|in:today,this_week,last_week,this_month,last_month,this_year,custom',
    ];

    protected $messages = [
        'selectedValues.min' => 'Please select at least one field to aggregate.',
    ];

    public function mount()
    {
        $this->initializeDates();
        $this->loadWorkOrderData();
        $this->setupAvailableFields();
    }

    private function initializeDates()
    {
        switch ($this->dateRange) {
            case 'today':
                $this->startDate = now()->startOfDay()->toDateString();
                $this->endDate = now()->endOfDay()->toDateString();
                break;
            case 'this_week':
                $this->startDate = now()->startOfWeek()->toDateString();
                $this->endDate = now()->endOfWeek()->toDateString();
                break;
            case 'last_week':
                $this->startDate = now()->subWeek()->startOfWeek()->toDateString();
                $this->endDate = now()->subWeek()->endOfWeek()->toDateString();
                break;
            case 'this_month':
                $this->startDate = now()->startOfMonth()->toDateString();
                $this->endDate = now()->endOfMonth()->toDateString();
                break;
            case 'last_month':
            default:
                $this->startDate = now()->subMonth()->startOfMonth()->toDateString();
                $this->endDate = now()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'this_year':
                $this->startDate = now()->startOfYear()->toDateString();
                $this->endDate = now()->endOfYear()->toDateString();
                break;
        }
    }

    public function updatedDateRange()
    {
        $this->initializeDates();
        $this->loadWorkOrderData();
    }

    public function updatedStartDate()
    {
        $this->dateRange = 'custom';
        $this->loadWorkOrderData();
    }

    public function updatedEndDate()
    {
        $this->dateRange = 'custom';
        $this->loadWorkOrderData();
    }

    // Auto-update pivot when filters change
    public function updatedActiveFilters()
    {
        if ($this->showPivotTable && ! empty($this->selectedValues)) {
            $this->autoGeneratePivot();
        }
    }

    // Auto-update pivot when configuration changes
    public function updatedSelectedRows()
    {
        if ($this->showPivotTable && ! empty($this->selectedValues)) {
            $this->autoGeneratePivot();
        }
    }

    public function updatedSelectedColumns()
    {
        if ($this->showPivotTable && ! empty($this->selectedValues)) {
            $this->autoGeneratePivot();
        }
    }

    public function updatedSelectedValues()
    {
        if ($this->showPivotTable && ! empty($this->selectedValues)) {
            $this->autoGeneratePivot();
        }
    }

    public function updatedAggregationFunction()
    {
        if ($this->showPivotTable && ! empty($this->selectedValues)) {
            $this->autoGeneratePivot();
        }
    }

    private function autoGeneratePivot()
    {
        try {
            if (empty($this->workOrderData) || empty($this->selectedValues)) {
                return;
            }

            $filteredData = $this->applyFilters($this->workOrderData);
            if (! empty($filteredData)) {
                $this->pivotResult = $this->createPivotTable($filteredData);
            }
        } catch (Exception $e) {
            // Silently fail auto-updates, user can manually regenerate
        }
    }

    private function loadWorkOrderData()
    {
        if (! Auth::user() || ! Auth::user()->factory) {
            $this->workOrderData = [];

            return;
        }

        $query = WorkOrder::with([
            'bom.purchaseOrder.partNumber',
            'machine',
            'operator.user',
            'okQuantities',
            'scrappedQuantities',
        ])
            ->where('factory_id', Auth::user()->factory->id);

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
        }

        $workOrders = $query->get();

        $this->workOrderData = $workOrders->map(function ($wo) {
            return [
                'work_order_no' => $wo->unique_id,
                'bom' => optional($wo->bom)->unique_id,
                'part_number' => optional($wo->bom?->purchaseOrder?->partNumber)->partnumber,
                'revision' => optional($wo->bom?->purchaseOrder?->partNumber)->revision,
                'machine' => optional($wo->machine)->name,
                'operator' => optional($wo->operator?->user)->first_name,
                'qty' => $wo->qty,
                'status' => ucfirst($wo->status),
                'start_time' => $wo->start_time?->format('Y-m-d H:i'),
                'end_time' => $wo->end_time?->format('Y-m-d H:i'),
                'ok_qty' => $wo->ok_qtys,
                'ko_qty' => $wo->scrapped_qtys,
                'created_date' => $wo->created_at->format('Y-m-d'),
                'created_month' => $wo->created_at->format('Y-m'),
                'created_year' => $wo->created_at->format('Y'),
                'day_of_week' => $wo->created_at->format('l'),
            ];
        })->toArray();
    }

    private function setupAvailableFields()
    {
        $this->availableFields = [
            // Categorical fields for grouping
            'grouping' => [
                'work_order_no' => 'Work Order No',
                'bom' => 'BOM',
                'part_number' => 'Part Number',
                'revision' => 'Revision',
                'machine' => 'Machine',
                'operator' => 'Operator',
                'status' => 'Status',
                'created_date' => 'Date',
                'created_month' => 'Month',
                'created_year' => 'Year',
                'day_of_week' => 'Day of Week',
            ],
            // Numeric fields for aggregation
            'numeric' => [
                'qty' => 'Quantity',
                'ok_qty' => 'OK Quantity',
                'ko_qty' => 'KO Quantity',
            ],
        ];
    }

    public function generatePivot()
    {
        try {
            $this->validate();

            if (empty($this->workOrderData)) {
                session()->flash('error', 'No work order data found for the selected date range.');

                return;
            }

            // Apply filters before creating pivot table
            $filteredData = $this->applyFilters($this->workOrderData);

            if (empty($filteredData)) {
                session()->flash('error', 'No data found after applying filters.');

                return;
            }

            $this->pivotResult = $this->createPivotTable($filteredData);
            $this->showPivotTable = true;

            // Auto-download CSV if enabled
            if ($this->autoDownloadCsv) {
                $this->downloadCsv();
            }

            session()->flash('success', 'Pivot table generated successfully!');
        } catch (Exception $e) {
            session()->flash('error', 'Error generating pivot table: '.$e->getMessage());
        }
    }

    private function applyFilters($data)
    {
        if (empty($this->activeFilters)) {
            return $data;
        }

        return collect($data)->filter(function ($row) {
            foreach ($this->activeFilters as $field => $values) {
                if (! empty($values) && ! in_array($row[$field], $values)) {
                    return false;
                }
            }

            return true;
        })->toArray();
    }

    private function createPivotTable($data = null)
    {
        $dataToProcess = $data ?? $this->workOrderData;
        $grouped = [];

        foreach ($dataToProcess as $row) {
            $rowKey = $this->createGroupKey($row, $this->selectedRows);
            $colKey = $this->createGroupKey($row, $this->selectedColumns);

            if (! isset($grouped[$rowKey])) {
                $grouped[$rowKey] = [];
            }

            if (! isset($grouped[$rowKey][$colKey])) {
                $grouped[$rowKey][$colKey] = [];
            }

            foreach ($this->selectedValues as $valueField) {
                if (! isset($grouped[$rowKey][$colKey][$valueField])) {
                    $grouped[$rowKey][$colKey][$valueField] = [];
                }

                if (isset($row[$valueField]) && $row[$valueField] !== null) {
                    $grouped[$rowKey][$colKey][$valueField][] = $row[$valueField];
                }
            }
        }

        // Calculate aggregations
        $result = [];
        foreach ($grouped as $rowKey => $rowData) {
            $result[$rowKey] = [];
            foreach ($rowData as $colKey => $colData) {
                $result[$rowKey][$colKey] = [];
                foreach ($colData as $valueKey => $valueArray) {
                    $result[$rowKey][$colKey][$valueKey] = $this->calculateAggregation($valueArray);
                }
            }
        }

        return [
            'data' => $result,
            'summary' => $this->calculateSummary($result),
            'metadata' => [
                'rows' => $this->selectedRows,
                'columns' => $this->selectedColumns,
                'values' => $this->selectedValues,
                'aggregation' => $this->aggregationFunction,
                'total_records' => count($dataToProcess),
                'date_range' => $this->dateRange,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
            ],
        ];
    }

    private function createGroupKey($row, $fields)
    {
        if (empty($fields)) {
            return 'Total';
        }

        $keyParts = [];
        foreach ($fields as $field) {
            $keyParts[] = $row[$field] ?? 'N/A';
        }

        return implode(' | ', $keyParts);
    }

    private function calculateAggregation($values)
    {
        if (empty($values)) {
            return 0;
        }

        $numericValues = array_filter(array_map(function ($val) {
            return is_numeric($val) ? floatval($val) : null;
        }, $values));

        if (empty($numericValues) && $this->aggregationFunction !== 'count') {
            return 0;
        }

        switch ($this->aggregationFunction) {
            case 'sum':
                return array_sum($numericValues);
            case 'avg':
                return empty($numericValues) ? 0 : round(array_sum($numericValues) / count($numericValues), 2);
            case 'min':
                return empty($numericValues) ? 0 : min($numericValues);
            case 'max':
                return empty($numericValues) ? 0 : max($numericValues);
            case 'count':
                return count($values);
            default:
                return array_sum($numericValues);
        }
    }

    private function calculateSummary($result)
    {
        $totalRows = count($result);
        $totalColumns = 0;
        $grandTotal = 0;

        foreach ($result as $rowData) {
            $totalColumns = max($totalColumns, count($rowData));
            foreach ($rowData as $colData) {
                foreach ($colData as $value) {
                    if (is_numeric($value)) {
                        $grandTotal += $value;
                    }
                }
            }
        }

        return [
            'total_rows' => $totalRows,
            'total_columns' => $totalColumns,
            'grand_total' => $grandTotal,
        ];
    }

    public function downloadCsv()
    {
        if (empty($this->pivotResult)) {
            session()->flash('error', 'No pivot data to export');

            return;
        }

        $filename = 'work_order_pivot_'.now()->format('Y-m-d_H-i-s').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // Write metadata
            fputcsv($file, ['Work Order Pivot Table Report']);
            fputcsv($file, ['Generated:', now()->format('Y-m-d H:i:s')]);
            fputcsv($file, ['Date Range:', $this->startDate.' to '.$this->endDate]);
            fputcsv($file, ['Total Records:', $this->pivotResult['metadata']['total_records']]);
            fputcsv($file, ['Aggregation:', ucfirst($this->aggregationFunction)]);
            fputcsv($file, []); // Empty row

            // Write headers
            $headerRow = ['Rows'];
            $allColumns = [];
            foreach ($this->pivotResult['data'] as $rowData) {
                $allColumns = array_merge($allColumns, array_keys($rowData));
            }
            $allColumns = array_unique($allColumns);
            $headerRow = array_merge($headerRow, $allColumns);
            fputcsv($file, $headerRow);

            // Write data
            foreach ($this->pivotResult['data'] as $rowKey => $rowData) {
                $row = [$rowKey];
                foreach ($allColumns as $colKey) {
                    $cellValue = '';
                    if (isset($rowData[$colKey])) {
                        $values = [];
                        foreach ($rowData[$colKey] as $valueKey => $value) {
                            $values[] = $this->availableFields['numeric'][$valueKey].': '.number_format($value, 2);
                        }
                        $cellValue = implode(', ', $values);
                    }
                    $row[] = $cellValue;
                }
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function resetConfiguration()
    {
        $this->reset([
            'selectedRows',
            'selectedColumns',
            'selectedValues',
            'activeFilters',
            'pivotResult',
        ]);
        $this->showPivotTable = false;
        session()->flash('success', 'Configuration reset successfully');
    }

    public function render()
    {
        return view('livewire.auto-pivot-table-builder');
    }
}
