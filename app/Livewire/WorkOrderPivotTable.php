<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\WorkOrder;
use App\Models\Machine;
use App\Models\Operator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderPivotTable extends Component
{
    // Available fields for pivot table
    public $availableFields = [
        'work_order_no' => 'Work Order No',
        'status' => 'Status',
        'machine' => 'Machine',
        'operator' => 'Operator',
        'bom' => 'BOM',
        'part_number' => 'Part Number',
        'revision' => 'Revision',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'qty' => 'Qty',
        'ok_qty' => 'OK Qty',
        'ko_qty' => 'KO Qty',
    ];

    // Field collections for drag and drop
    public $rowFields = [];
    public $columnFields = [];
    public $valueFields = [];

    // Pivot table data
    public $pivotData = [];
    public $pivotHeaders = [];
    public $pivotRows = [];

    // Filters
    public $filterDateFrom = '';
    public $filterDateTo = '';
    public $filterStatus = '';
    public $filterMachine = '';
    public $filterOperator = '';



    public function mount($filterDateFrom = null, $filterDateTo = null, $filterStatus = null, $filterMachine = null, $filterOperator = null)
    {
        $this->filterDateFrom = $filterDateFrom;
        $this->filterDateTo = $filterDateTo;
        $this->filterStatus = $filterStatus;
        $this->filterMachine = $filterMachine;
        $this->filterOperator = $filterOperator;
    }

    public function render()
    {
        return view('livewire.work-order-pivot-table');
    }

    public function addField($zone, $fieldKey, $fieldLabel)
    {
        $field = ['key' => $fieldKey, 'label' => $fieldLabel];

        // Remove from other zones if it exists
        $this->removeFieldFromAllZones($fieldKey);

        switch ($zone) {
            case 'rows':
                if (!$this->fieldExistsInArray($this->rowFields, $fieldKey)) {
                    $this->rowFields[] = $field;
                }
                break;
            case 'columns':
                if (!$this->fieldExistsInArray($this->columnFields, $fieldKey)) {
                    $this->columnFields[] = $field;
                }
                break;
            case 'values':
                if (!$this->fieldExistsInArray($this->valueFields, $fieldKey)) {
                    $this->valueFields[] = $field;
                }
                break;
        }

        $this->generatePivotTable();
    }

    public function removeField($zone, $fieldKey)
    {
        switch ($zone) {
            case 'rows':
                $this->rowFields = array_filter($this->rowFields, fn($field) => $field['key'] !== $fieldKey);
                break;
            case 'columns':
                $this->columnFields = array_filter($this->columnFields, fn($field) => $field['key'] !== $fieldKey);
                break;
            case 'values':
                $this->valueFields = array_filter($this->valueFields, fn($field) => $field['key'] !== $fieldKey);
                break;
        }

        $this->generatePivotTable();
    }

    private function removeFieldFromAllZones($fieldKey)
    {
        $this->rowFields = array_filter($this->rowFields, fn($field) => $field['key'] !== $fieldKey);
        $this->columnFields = array_filter($this->columnFields, fn($field) => $field['key'] !== $fieldKey);
        $this->valueFields = array_filter($this->valueFields, fn($field) => $field['key'] !== $fieldKey);
    }

    private function fieldExistsInArray($array, $fieldKey)
    {
        foreach ($array as $field) {
            if ($field['key'] === $fieldKey) {
                return true;
            }
        }
        return false;
    }

    public function clearAll()
    {
        $this->rowFields = [];
        $this->columnFields = [];
        $this->valueFields = [];
        $this->pivotData = [];
        $this->pivotHeaders = [];
        $this->pivotRows = [];
    }

    public function generatePivotTable()
    {
        if (empty($this->rowFields) && empty($this->columnFields)) {
            $this->pivotData = [];
            $this->pivotHeaders = [];
            $this->pivotRows = [];
            return;
        }

        // Enforce both date filters must be set
        if (empty($this->filterDateFrom) || empty($this->filterDateTo)) {
            $this->pivotData = [
                'headers' => ['Warning'],
                'rows' => [['Please select both start and end dates to generate the pivot table.']]
            ];
            $this->pivotHeaders = ['Warning'];
            $this->pivotRows = [['Please select both start and end dates to generate the pivot table.']];
            return;
        }

        // Build base query with factory_id and date range
        $query = WorkOrder::query()
            ->where('factory_id', Auth::user()->factory_id)
            ->with(['machine', 'operator', 'bom.purchaseOrder.partNumber'])
            ->whereDate('created_at', '>=', $this->filterDateFrom)
            ->whereDate('created_at', '<=', $this->filterDateTo);

        // Apply other filters
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

        $workOrders = $query->get();

        // Debug: Check what data we're getting
        Log::info('Pivot Table - Work Orders Count: ' . $workOrders->count());
        if ($workOrders->count() > 0) {
            $firstWo = $workOrders->first();
            Log::info('Pivot Table - Sample Work Order:', [
                'unique_id' => $firstWo->unique_id,
                'wo_number' => $firstWo->wo_number,
                'status' => $firstWo->status,
                'qty' => $firstWo->qty,
                'machine_id' => $firstWo->machine_id,
                'machine_name' => $firstWo->machine?->name,
                'operator_id' => $firstWo->operator_id,
                'operator_name' => $firstWo->operator?->name,
                'bom_id' => $firstWo->bom_id,
                'bom_number' => $firstWo->bom?->bom_number,
            ]);
        }

        // Transform data for pivot
        $transformedData = $workOrders->map(function ($wo) {
            return [
                'work_order_no' => $wo->wo_number ?? $wo->unique_id ?? 'WO-' . $wo->id,
                'status' => $wo->status ?? 'Unknown',
                'machine' => $wo->machine?->name ?? ($wo->machine_id ? 'Machine-' . $wo->machine_id : 'Unassigned'),
                'operator' => $wo->operator?->user?->getFilamentName() ?? ($wo->operator_id ? 'Operator-' . $wo->operator_id : 'Unassigned'),
                'bom' => $wo->bom?->bom_number ?? ($wo->bom_id ? 'BOM-' . $wo->bom_id : 'No BOM'),
                'part_number' => $wo->bom?->purchaseOrder?->partNumber?->partnumber ?? 'No Part Number',
                'revision' => $wo->bom?->purchaseOrder?->partNumber?->revision ?? 'No Revision',
                'start_time' => $wo->start_time ? $wo->start_time->format('Y-m-d H:i') : 'Not Started',
                'end_time' => $wo->end_time ? $wo->end_time->format('Y-m-d H:i') : 'Not Finished',
                'qty' => (int)($wo->qty ?? 0),
                'ok_qty' => (int)($wo->ok_qtys ?? 0),
                'ko_qty' => (int)($wo->scrapped_qtys ?? 0),
            ];
        })->toArray();

        // Generate pivot table
        $this->createPivotTable($transformedData);
    }

    private function createPivotTable($data)
    {
        if (empty($data)) {
            $this->pivotData = [];
            $this->pivotHeaders = [];
            $this->pivotRows = [];
            return;
        }

        // If no columns selected, create a simple table
        if (empty($this->columnFields)) {
            $this->createSimpleTable($data);
            return;
        }

        // Create full pivot table with rows and columns
        $this->createFullPivotTable($data);
    }

    private function createSimpleTable($data)
    {
        $this->pivotHeaders = [];
        $this->pivotRows = [];

        // Add row headers
        foreach ($this->rowFields as $rowField) {
            $this->pivotHeaders[] = $rowField['label'];
        }

        // Add value headers
        foreach ($this->valueFields as $valueField) {
            $this->pivotHeaders[] = $valueField['label'];
        }

        // Group data by selected row fields
        $rowKeys = array_column($this->rowFields, 'key');
        $groupedData = $this->groupDataBy($data, $rowKeys);

        foreach ($groupedData as $groupKey => $items) {
            $row = [];

            // Add row values
            $keyParts = explode('|', $groupKey);
            foreach ($keyParts as $keyPart) {
                $row[] = $keyPart;
            }

            // Add aggregated values
            foreach ($this->valueFields as $valueField) {
                $sum = array_sum(array_column($items, $valueField['key']));
                $row[] = $sum;
            }

            $this->pivotRows[] = $row;
        }

        $this->pivotData = [
            'headers' => $this->pivotHeaders,
            'rows' => $this->pivotRows
        ];
    }

    private function createFullPivotTable($data)
    {
        // Create full pivot table with rows and columns
        $rowKeys = array_column($this->rowFields, 'key');
        $columnKeys = array_column($this->columnFields, 'key');

        $rowGroups = $this->groupDataBy($data, $rowKeys);
        $columnGroups = $this->groupDataBy($data, $columnKeys);

        // Create headers: row fields + column combinations
        $this->pivotHeaders = [];
        foreach ($this->rowFields as $rowField) {
            $this->pivotHeaders[] = $rowField['label'];
        }

        foreach ($columnGroups as $colKey => $colItems) {
            foreach ($this->valueFields as $valueField) {
                $colLabel = $colKey . ' (' . $valueField['label'] . ')';
                $this->pivotHeaders[] = $colLabel;
            }
        }

        // Create rows
        $this->pivotRows = [];
        foreach ($rowGroups as $rowKey => $rowItems) {
            $row = [];

            // Add row identifiers
            $keyParts = explode('|', $rowKey);
            foreach ($keyParts as $keyPart) {
                $row[] = $keyPart;
            }

            foreach ($columnGroups as $colKey => $colItems) {
                // Find intersection of row and column data
                $intersection = array_filter($data, function ($item) use ($rowKey, $colKey, $rowKeys, $columnKeys) {
                    $rowMatch = $this->matchesGroup($item, $rowKeys, $rowKey);
                    $colMatch = $this->matchesGroup($item, $columnKeys, $colKey);
                    return $rowMatch && $colMatch;
                });

                foreach ($this->valueFields as $valueField) {
                    $sum = array_sum(array_column($intersection, $valueField['key']));
                    $row[] = $sum;
                }
            }

            $this->pivotRows[] = $row;
        }

        $this->pivotData = [
            'headers' => $this->pivotHeaders,
            'rows' => $this->pivotRows
        ];
    }

    private function groupDataBy($data, $fields)
    {
        $grouped = [];

        foreach ($data as $item) {
            $key = implode('|', array_map(fn($field) => $item[$field] ?? 'N/A', $fields));
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $item;
        }

        return $grouped;
    }

    private function matchesGroup($item, $fields, $groupKey)
    {
        $itemKey = implode('|', array_map(fn($field) => $item[$field] ?? 'N/A', $fields));
        return $itemKey === $groupKey;
    }

    public function exportToExcel()
    {
        // TODO: Implement Excel export functionality
        $this->dispatch('show-notification', 'Excel export functionality will be implemented soon');
    }
}
