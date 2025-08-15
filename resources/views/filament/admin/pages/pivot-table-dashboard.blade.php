<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}
        
        @if($this->showPivotTable && !empty($this->pivotResult))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">Pivot Table Results</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Rows: {{ implode(', ', $this->pivotResult['rows']) }} | 
                        Columns: {{ implode(', ', $this->pivotResult['columns']) }} | 
                        Values: {{ implode(', ', $this->pivotResult['values']) }} | 
                        Aggregation: {{ ucfirst($this->pivotResult['aggregation']) }}
                    </p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                    {{ implode(' / ', $this->pivotResult['rows']) ?: 'Rows' }}
                                </th>
                                @php
                                    $allColumns = [];
                                    foreach ($this->pivotResult['data'] as $rowData) {
                                        $allColumns = array_merge($allColumns, array_keys($rowData));
                                    }
                                    $allColumns = array_unique($allColumns);
                                @endphp
                                @foreach($allColumns as $column)
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                        {{ $column }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($this->pivotResult['data'] as $rowKey => $rowData)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-r border-gray-200 bg-gray-50">
                                        {{ $rowKey }}
                                    </td>
                                    @foreach($allColumns as $columnKey)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-r border-gray-200">
                                            @if(isset($rowData[$columnKey]))
                                                @foreach($rowData[$columnKey] as $valueKey => $value)
                                                    <div class="mb-1">
                                                        <span class="text-xs text-gray-400">{{ $valueKey }}:</span>
                                                        <span class="font-medium">{{ $value }}</span>
                                                    </div>
                                                @endforeach
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(!empty($this->csvData) && $this->showPivotConfig)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">CSV Data Preview</h3>
                    <p class="text-sm text-gray-600 mt-1">Showing first 10 rows of {{ count($this->csvData) }} total rows</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                @foreach($this->csvHeaders as $header)
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ $header }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach(array_slice($this->csvData, 0, 10) as $row)
                                <tr class="hover:bg-gray-50">
                                    @foreach($this->csvHeaders as $header)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $row[$header] ?? '' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <style>
        .fi-fo-file-upload .fi-fo-file-upload-file {
            border-radius: 0.5rem;
            border: 2px dashed #d1d5db;
            padding: 2rem;
            transition: all 0.2s;
        }
        
        .fi-fo-file-upload .fi-fo-file-upload-file:hover {
            border-color: #3b82f6;
            background-color: #f8fafc;
        }
        
        .pivot-table-cell {
            min-width: 120px;
        }
        
        .pivot-table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .pivot-row-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
    </style>
</x-filament-panels::page>
