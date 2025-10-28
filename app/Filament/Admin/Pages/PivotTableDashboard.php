<?php

namespace App\Filament\Admin\Pages;

use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PivotTableDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.pivot-table-dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Work Order Reports';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Pivot Table';

    public array $data = [];

    public array $csvHeaders = [];

    public array $csvData = [];

    public array $pivotResult = [];

    public bool $showPivotConfig = false;

    public bool $showPivotTable = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Upload CSV File')
                    ->description('Upload a CSV file to create a pivot table')
                    ->schema([
                        FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', '.csv'])
                            ->directory('uploads/csv')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                if ($state) {
                                    $this->processCsvFile($state);
                                }
                            }),
                    ])
                    ->collapsed(false),

                Section::make('Pivot Table Configuration')
                    ->description('Configure your pivot table settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('rows')
                                    ->label('Rows')
                                    ->options(fn () => empty($this->csvHeaders) ? [] : array_combine($this->csvHeaders, $this->csvHeaders))
                                    ->multiple()
                                    ->searchable()
                                    ->placeholder('Select fields for rows')
                                    ->helperText('Fields to group by as rows'),

                                Select::make('columns')
                                    ->label('Columns')
                                    ->options(fn () => empty($this->csvHeaders) ? [] : array_combine($this->csvHeaders, $this->csvHeaders))
                                    ->multiple()
                                    ->searchable()
                                    ->placeholder('Select fields for columns')
                                    ->helperText('Fields to group by as columns'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('values')
                                    ->label('Values')
                                    ->options(fn () => empty($this->csvHeaders) ? [] : array_combine($this->csvHeaders, $this->csvHeaders))
                                    ->multiple()
                                    ->searchable()
                                    ->placeholder('Select fields to aggregate')
                                    ->helperText('Numeric fields to calculate'),

                                Select::make('aggregation')
                                    ->label('Aggregation Function')
                                    ->options([
                                        'sum' => 'Sum',
                                        'count' => 'Count',
                                        'avg' => 'Average',
                                        'min' => 'Minimum',
                                        'max' => 'Maximum',
                                    ])
                                    ->default('sum')
                                    ->required()
                                    ->helperText('How to aggregate the values'),
                            ]),

                        CheckboxList::make('filters')
                            ->label('Available Filters')
                            ->options(fn () => empty($this->csvHeaders) ? [] : array_combine($this->csvHeaders, $this->csvHeaders))
                            ->columns(3)
                            ->helperText('Select fields to enable as filters'),

                        Actions::make([
                            Action::make('generate_pivot')
                                ->label('Generate Pivot Table')
                                ->icon('heroicon-o-table-cells')
                                ->color('primary')
                                ->action('generatePivotTable')
                                ->disabled(fn () => empty($this->csvData)),

                            Action::make('reset')
                                ->label('Reset')
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->action('resetForm'),
                        ]),
                    ])
                    ->visible($this->showPivotConfig)
                    ->collapsed(false),

                Section::make('Export Options')
                    ->schema([
                        Radio::make('export_format')
                            ->label('Export Format')
                            ->options([
                                'csv' => 'CSV',
                                'excel' => 'Excel',
                                'pdf' => 'PDF',
                            ])
                            ->default('csv')
                            ->inline(),

                        Actions::make([
                            Action::make('export_pivot')
                                ->label('Export Pivot Table')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('success')
                                ->action('exportPivotTable')
                                ->disabled(fn () => empty($this->pivotResult)),
                        ]),
                    ])
                    ->visible($this->showPivotTable)
                    ->collapsed(false),
            ])
            ->statePath('data');
    }

    public function processCsvFile($filePath): void
    {
        try {
            $fullPath = Storage::path($filePath);

            if (! file_exists($fullPath)) {
                throw new Exception('File not found');
            }

            $csvData = [];
            $headers = [];

            if (($handle = fopen($fullPath, 'r')) !== false) {
                // Read headers
                $headers = fgetcsv($handle);
                if (! $headers) {
                    throw new Exception('Invalid CSV format');
                }

                // Read data rows
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) === count($headers)) {
                        $csvData[] = array_combine($headers, $row);
                    }
                }
                fclose($handle);
            }

            $this->csvHeaders = $headers;
            $this->csvData = $csvData;
            $this->showPivotConfig = true;

            Notification::make()
                ->title('CSV file processed successfully')
                ->success()
                ->body(count($csvData).' rows loaded with '.count($headers).' columns')
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Error processing CSV file')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    public function generatePivotTable(): void
    {
        $formState = $this->form->getState();

        try {
            $rows = $formState['rows'] ?? [];
            $columns = $formState['columns'] ?? [];
            $values = $formState['values'] ?? [];
            $aggregation = $formState['aggregation'] ?? 'sum';

            if (empty($rows) && empty($columns)) {
                throw new Exception('Please select at least one field for rows or columns');
            }

            $pivotData = $this->createPivotTable($rows, $columns, $values, $aggregation);
            $this->pivotResult = $pivotData;
            $this->showPivotTable = true;

            Notification::make()
                ->title('Pivot table generated successfully')
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Error generating pivot table')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    private function createPivotTable(array $rows, array $columns, array $values, string $aggregation): array
    {
        $groupedData = [];

        foreach ($this->csvData as $row) {
            $rowKey = $this->createGroupKey($row, $rows);
            $colKey = $this->createGroupKey($row, $columns);

            if (! isset($groupedData[$rowKey])) {
                $groupedData[$rowKey] = [];
            }

            if (! isset($groupedData[$rowKey][$colKey])) {
                $groupedData[$rowKey][$colKey] = [];
            }

            foreach ($values as $value) {
                if (! isset($groupedData[$rowKey][$colKey][$value])) {
                    $groupedData[$rowKey][$colKey][$value] = [];
                }

                if (isset($row[$value])) {
                    $groupedData[$rowKey][$colKey][$value][] = $row[$value];
                }
            }
        }

        // Calculate aggregations
        $result = [];
        foreach ($groupedData as $rowKey => $rowData) {
            $result[$rowKey] = [];
            foreach ($rowData as $colKey => $colData) {
                $result[$rowKey][$colKey] = [];
                foreach ($colData as $valueKey => $valueArray) {
                    $result[$rowKey][$colKey][$valueKey] = $this->calculateAggregation($valueArray, $aggregation);
                }
            }
        }

        return [
            'data' => $result,
            'rows' => $rows,
            'columns' => $columns,
            'values' => $values,
            'aggregation' => $aggregation,
        ];
    }

    private function createGroupKey(array $row, array $fields): string
    {
        if (empty($fields)) {
            return 'Total';
        }

        $keyParts = [];
        foreach ($fields as $field) {
            $keyParts[] = $row[$field] ?? '';
        }

        return implode(' | ', $keyParts);
    }

    private function calculateAggregation(array $values, string $aggregation)
    {
        if (empty($values)) {
            return 0;
        }

        $numericValues = array_filter(array_map('floatval', $values), function ($val) {
            return is_numeric($val);
        });

        if (empty($numericValues)) {
            return $aggregation === 'count' ? count($values) : 0;
        }

        switch ($aggregation) {
            case 'sum':
                return array_sum($numericValues);
            case 'avg':
                return round(array_sum($numericValues) / count($numericValues), 2);
            case 'min':
                return min($numericValues);
            case 'max':
                return max($numericValues);
            case 'count':
                return count($values);
            default:
                return array_sum($numericValues);
        }
    }

    public function exportPivotTable()
    {
        $formState = $this->form->getState();
        $format = $formState['export_format'] ?? 'csv';

        try {
            $filename = 'pivot_table_'.now()->format('Y-m-d_H-i-s');

            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($filename);
                case 'excel':
                    return $this->exportToExcel($filename);
                case 'pdf':
                    return $this->exportToPdf($filename);
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Export failed')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    private function exportToCsv(string $filename): BinaryFileResponse
    {
        $csvPath = storage_path("app/temp/{$filename}.csv");

        if (! file_exists(dirname($csvPath))) {
            mkdir(dirname($csvPath), 0755, true);
        }

        $handle = fopen($csvPath, 'w');

        // Create headers
        $headers = ['Rows'];
        $allColumns = [];
        foreach ($this->pivotResult['data'] as $rowData) {
            $allColumns = array_merge($allColumns, array_keys($rowData));
        }
        $allColumns = array_unique($allColumns);
        $headers = array_merge($headers, $allColumns);

        fputcsv($handle, $headers);

        // Write data
        foreach ($this->pivotResult['data'] as $rowKey => $rowData) {
            $row = [$rowKey];
            foreach ($allColumns as $colKey) {
                $value = '';
                if (isset($rowData[$colKey])) {
                    $values = [];
                    foreach ($rowData[$colKey] as $valueKey => $valueData) {
                        $values[] = "{$valueKey}: {$valueData}";
                    }
                    $value = implode(', ', $values);
                }
                $row[] = $value;
            }
            fputcsv($handle, $row);
        }

        fclose($handle);

        return response()->download($csvPath)->deleteFileAfterSend();
    }

    private function exportToExcel(string $filename): BinaryFileResponse
    {
        // Implementation for Excel export using PhpSpreadsheet
        // Similar to CSV but using PhpSpreadsheet library
        throw new Exception('Excel export not implemented yet');
    }

    private function exportToPdf(string $filename): BinaryFileResponse
    {
        // Implementation for PDF export
        throw new Exception('PDF export not implemented yet');
    }

    public function resetForm(): void
    {
        $this->form->fill();
        $this->csvHeaders = [];
        $this->csvData = [];
        $this->pivotResult = [];
        $this->showPivotConfig = false;
        $this->showPivotTable = false;

        Notification::make()
            ->title('Form reset successfully')
            ->success()
            ->send();
    }
}
