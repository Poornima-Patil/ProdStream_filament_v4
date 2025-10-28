<?php

namespace App\Livewire;

use Exception;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class PivotTableBuilder extends Component
{
    use WithFileUploads;

    protected $layout = 'components.layouts.app';

    public $csvFile;

    public $csvData = [];

    public $csvHeaders = [];

    // Pivot configuration
    public $selectedRows = [];

    public $selectedColumns = [];

    public $selectedValues = [];

    public $selectedFilters = [];

    public $aggregationFunction = 'sum';

    // Filter values
    public $filterValues = [];

    public $activeFilters = [];

    // UI state
    public $showConfiguration = false;

    public $showResults = false;

    public $pivotResult = [];

    protected $rules = [
        'csvFile' => 'required|file|mimetypes:text/csv,text/plain|max:10240',
        'selectedRows' => 'array',
        'selectedColumns' => 'array',
        'selectedValues' => 'array',
        'aggregationFunction' => 'required|in:sum,count,avg,min,max',
    ];

    public function updatedCsvFile()
    {
        $this->validate(['csvFile' => 'required|file|mimetypes:text/csv,text/plain|max:10240']);

        try {
            $this->processCsvFile();
            $this->showConfiguration = true;
            session()->flash('success', 'CSV file uploaded successfully!');
        } catch (Exception $e) {
            session()->flash('error', 'Error processing CSV: '.$e->getMessage());
        }
    }

    private function processCsvFile()
    {
        $path = $this->csvFile->getRealPath();
        $csvData = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle);
            if (! $headers) {
                throw new Exception('Invalid CSV format');
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($headers)) {
                    $csvData[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }

        $this->csvHeaders = $headers;
        $this->csvData = $csvData;

        // Initialize filter values
        $this->initializeFilterValues();
    }

    private function initializeFilterValues()
    {
        $this->filterValues = [];
        foreach ($this->csvHeaders as $header) {
            $uniqueValues = collect($this->csvData)
                ->pluck($header)
                ->unique()
                ->filter()
                ->sort()
                ->values()
                ->toArray();

            $this->filterValues[$header] = $uniqueValues;
        }
    }

    public function generatePivot()
    {
        try {
            $this->validate();

            $filteredData = $this->applyFilters($this->csvData);
            $this->pivotResult = $this->createPivotTable($filteredData);
            $this->showResults = true;

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

    private function createPivotTable($data)
    {
        $grouped = [];

        foreach ($data as $row) {
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

                if (isset($row[$valueField])) {
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
                'total_records' => count($data),
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
                    $grandTotal += $value;
                }
            }
        }

        return [
            'total_rows' => $totalRows,
            'total_columns' => $totalColumns,
            'grand_total' => $grandTotal,
        ];
    }

    public function exportToCsv()
    {
        if (empty($this->pivotResult)) {
            session()->flash('error', 'No pivot data to export');

            return;
        }

        $filename = 'pivot_export_'.now()->format('Y-m-d_H-i-s').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

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
                            $values[] = "{$valueKey}: {$value}";
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
            'selectedFilters',
            'activeFilters',
            'pivotResult',
        ]);
        $this->showConfiguration = false;
        $this->showResults = false;

        session()->flash('success', 'Configuration reset successfully');
    }

    #[Computed]
    public function availableNumericFields()
    {
        if (empty($this->csvData)) {
            return [];
        }

        $numericFields = [];
        foreach ($this->csvHeaders as $header) {
            $sampleValues = collect($this->csvData)
                ->take(10)
                ->pluck($header)
                ->filter(fn ($val) => is_numeric($val));

            if ($sampleValues->count() > 5) {
                $numericFields[] = $header;
            }
        }

        return $numericFields;
    }

    public function render()
    {
        return view('livewire.pivot-table-builder');
    }
}
