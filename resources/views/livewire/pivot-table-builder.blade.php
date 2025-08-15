<div>
    <div class="max-w-7xl mx-auto p-6 space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Pivot Table Builder</h1>
            <p class="text-gray-600">Upload a CSV file and create interactive pivot tables with custom filters and aggregations.</p>
        </div>

        <!-- Flash Messages -->
        @if (session()->has('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

    <!-- File Upload Section -->
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">1. Upload CSV File</h2>
            <p class="text-sm text-gray-600 mt-1">Select a CSV file to begin creating your pivot table</p>
        </div>
        <div class="p-6">
            <div class="flex items-center justify-center w-full">
                <label for="csvFile" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <svg class="w-8 h-8 mb-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p class="mb-2 text-sm text-gray-500">
                            <span class="font-semibold">Click to upload</span> or drag and drop
                        </p>
                        <p class="text-xs text-gray-500">CSV files only (MAX. 10MB)</p>
                    </div>
                    <input id="csvFile" type="file" wire:model="csvFile" class="hidden" accept=".csv,text/csv">
                </label>
            </div>
            @error('csvFile') 
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p> 
            @enderror

            @if ($csvFile)
                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-700">
                        <strong>File:</strong> {{ $csvFile->getClientOriginalName() }} 
                        ({{ round($csvFile->getSize() / 1024, 2) }} KB)
                    </p>
                </div>
            @endif
        </div>
    </div>

    <!-- Configuration Section -->
    @if ($showConfiguration)
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">2. Configure Pivot Table</h2>
                <p class="text-sm text-gray-600 mt-1">Drag and drop fields or select from dropdowns to configure your pivot table</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Available Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Rows -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Rows</label>
                        <select wire:model="selectedRows" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm max-h-32 overflow-y-auto">
                            @foreach ($csvHeaders as $header)
                                <option value="{{ $header }}">{{ $header }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500">Fields to group by as rows</p>
                    </div>

                    <!-- Columns -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Columns</label>
                        <select wire:model="selectedColumns" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm max-h-32 overflow-y-auto">
                            @foreach ($csvHeaders as $header)
                                <option value="{{ $header }}">{{ $header }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500">Fields to group by as columns</p>
                    </div>

                    <!-- Values -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Values</label>
                        <select wire:model="selectedValues" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm max-h-32 overflow-y-auto">
                            @foreach ($this->availableNumericFields as $field)
                                <option value="{{ $field }}">{{ $field }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500">Numeric fields to aggregate</p>
                    </div>

                    <!-- Aggregation -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Aggregation</label>
                        <select wire:model="aggregationFunction" class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm">
                            <option value="sum">Sum</option>
                            <option value="count">Count</option>
                            <option value="avg">Average</option>
                            <option value="min">Minimum</option>
                            <option value="max">Maximum</option>
                        </select>
                        <p class="text-xs text-gray-500">How to aggregate values</p>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="border-t pt-6">
                    <h3 class="text-md font-medium text-gray-900 mb-4">Filters</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($csvHeaders as $header)
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">{{ $header }}</label>
                                <select wire:model="activeFilters.{{ $header }}" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm max-h-24 overflow-y-auto">
                                    @foreach ($filterValues[$header] ?? [] as $value)
                                        <option value="{{ $value }}">{{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-4">
                    <button wire:click="generatePivot" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors">
                        Generate Pivot Table
                    </button>
                    <button wire:click="resetConfiguration" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors">
                        Reset Configuration
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Results Section -->
    @if ($showResults && !empty($pivotResult))
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">3. Pivot Table Results</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $pivotResult['metadata']['total_records'] }} records processed | 
                        {{ $pivotResult['summary']['total_rows'] }} rows | 
                        {{ $pivotResult['summary']['total_columns'] }} columns
                    </p>
                </div>
                <button wire:click="exportToCsv" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Export CSV
                </button>
            </div>

            <!-- Pivot Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider border-r border-blue-400">
                                {{ implode(' / ', $pivotResult['metadata']['rows']) ?: 'Rows' }}
                            </th>
                            @php
                                $allColumns = [];
                                foreach ($pivotResult['data'] as $rowData) {
                                    $allColumns = array_merge($allColumns, array_keys($rowData));
                                }
                                $allColumns = array_unique($allColumns);
                            @endphp
                            @foreach($allColumns as $column)
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider border-r border-blue-400">
                                    {{ $column }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($pivotResult['data'] as $rowKey => $rowData)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-r border-gray-200 bg-gradient-to-r from-pink-50 to-red-50">
                                    {{ $rowKey }}
                                </td>
                                @foreach($allColumns as $columnKey)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-r border-gray-200">
                                        @if(isset($rowData[$columnKey]))
                                            @foreach($rowData[$columnKey] as $valueKey => $value)
                                                <div class="mb-1 last:mb-0">
                                                    <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">
                                                        {{ $valueKey }}: 
                                                        <span class="font-medium text-gray-900">{{ number_format($value, 2) }}</span>
                                                    </span>
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

            <!-- Summary -->
            <div class="p-6 border-t border-gray-200 bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold text-blue-600">{{ $pivotResult['summary']['total_rows'] }}</div>
                        <div class="text-sm text-gray-600">Total Rows</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">{{ $pivotResult['summary']['total_columns'] }}</div>
                        <div class="text-sm text-gray-600">Total Columns</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-purple-600">{{ number_format($pivotResult['summary']['grand_total'], 2) }}</div>
                        <div class="text-sm text-gray-600">Grand Total</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Data Preview -->
    @if (!empty($csvData) && $showConfiguration)
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Data Preview</h2>
                <p class="text-sm text-gray-600 mt-1">Showing first 5 rows of {{ count($csvData) }} total records</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach($csvHeaders as $header)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ $header }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach(array_slice($csvData, 0, 5) as $row)
                            <tr class="hover:bg-gray-50">
                                @foreach($csvHeaders as $header)
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
        /* Custom scrollbar for select elements */
        select::-webkit-scrollbar {
            width: 6px;
        }
        
        select::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        select::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        select::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Loading animation */
        .loading {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }
    </style>
</div>
