<div>
    <div class="max-w-7xl mx-auto p-6 space-y-6">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg p-6">
            <h1 class="text-2xl font-bold mb-2">Auto Pivot Table Builder</h1>
            <p class="text-blue-100">Generate pivot tables from your work order data automatically. No CSV upload required!</p>
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

        <!-- Step 1: Date Range Selection -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">1. Select Data Range</h2>
                <p class="text-sm text-gray-600 mt-1">Choose the date range for your work order data</p>
            </div>
            
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quick Select</label>
                        <select wire:model.live="dateRange" class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm">
                            <option value="today">Today</option>
                            <option value="this_week">This Week</option>
                            <option value="last_week">Last Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_year">This Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" wire:model.live="startDate" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <input type="date" wire:model.live="endDate" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
                
                <div class="text-sm text-gray-600 bg-blue-50 p-3 rounded-md">
                    <strong>Data Range:</strong> {{ $startDate }} to {{ $endDate }} 
                    <span class="text-blue-600 font-medium">({{ count($workOrderData) }} records loaded automatically)</span>
                </div>
            </div>
        </div>

        <!-- Step 2: Configure Pivot Table -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">2. Configure Pivot Table</h2>
                <p class="text-sm text-gray-600 mt-1">Drag and drop fields or select from dropdowns to configure your pivot table</p>
            </div>
            
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Rows -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Rows</label>
                        <select wire:model.live="selectedRows" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm h-32 overflow-y-auto">
                            @foreach ($availableFields['grouping'] as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500">Fields to group by as rows</p>
                    </div>

                    <!-- Columns -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Columns</label>
                        <select wire:model.live="selectedColumns" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm h-32 overflow-y-auto">
                            @foreach ($availableFields['grouping'] as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500">Fields to group by as columns</p>
                    </div>

                    <!-- Values -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Values</label>
                        <select wire:model.live="selectedValues" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm h-32 overflow-y-auto">
                            @foreach ($availableFields['numeric'] as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500">Numeric fields to aggregate</p>
                        @error('selectedValues') 
                            <p class="text-xs text-red-600">{{ $message }}</p> 
                        @enderror
                    </div>

                    <!-- Aggregation -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Aggregation</label>
                        <select wire:model.live="aggregationFunction" class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm">
                            <option value="sum">Sum</option>
                            <option value="count">Count</option>
                            <option value="avg">Average</option>
                            <option value="min">Minimum</option>
                            <option value="max">Maximum</option>
                        </select>
                        <p class="text-xs text-gray-500">How to aggregate values</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3 bg-blue-50 p-3 rounded-md">
                    <input type="checkbox" wire:model="autoDownloadCsv" id="autoDownload" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label for="autoDownload" class="text-sm font-medium text-gray-700">
                        Auto-download CSV after generating pivot table
                    </label>
                </div>
            </div>
        </div>

        <!-- Filters Section (exactly like CSV version) -->
        @if(!empty($workOrderData))
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Filters</h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Work Order No Filter -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Work Order No</label>
                            <select wire:model.live="activeFilters.work_order_no" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm h-24 overflow-y-auto">
                                @foreach(collect($workOrderData)->pluck('work_order_no')->unique()->sort()->values() as $value)
                                    @if($value)
                                        <option value="{{ $value }}">{{ $value }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <!-- BOM Filter -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">BOM</label>
                            <select wire:model.live="activeFilters.bom" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm h-24 overflow-y-auto">
                                @foreach(collect($workOrderData)->pluck('bom')->unique()->sort()->values() as $value)
                                    @if($value)
                                        <option value="{{ $value }}">{{ $value }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <!-- Part Number Filter -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Part Number</label>
                            <select wire:model.live="activeFilters.part_number" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm h-24 overflow-y-auto">
                                @foreach(collect($workOrderData)->pluck('part_number')->unique()->sort()->values() as $value)
                                    @if($value)
                                        <option value="{{ $value }}">{{ $value }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <!-- Machine Filter -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Machine</label>
                            <select wire:model.live="activeFilters.machine" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm h-24 overflow-y-auto">
                                @foreach(collect($workOrderData)->pluck('machine')->unique()->sort()->values() as $value)
                                    @if($value)
                                        <option value="{{ $value }}">{{ $value }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <!-- Operator Filter -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Operator</label>
                            <select wire:model.live="activeFilters.operator" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm h-24 overflow-y-auto">
                                @foreach(collect($workOrderData)->pluck('operator')->unique()->sort()->values() as $value)
                                    @if($value)
                                        <option value="{{ $value }}">{{ $value }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select wire:model.live="activeFilters.status" multiple class="w-full p-2 border border-gray-300 rounded-md bg-white text-sm h-24 overflow-y-auto">
                                @foreach(collect($workOrderData)->pluck('status')->unique()->sort()->values() as $value)
                                    @if($value)
                                        <option value="{{ $value }}">{{ $value }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Generate Pivot Button -->
                <div class="p-6 border-t border-gray-200 bg-gray-50">
                    <div class="text-center">
                        <button wire:click="generatePivot" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg text-sm font-medium transition-colors disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="generatePivot">Generate Pivot Table</span>
                            <span wire:loading wire:target="generatePivot" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Generating...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Pivot Table Results -->
        @if ($showPivotTable && !empty($pivotResult))
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">3. Pivot Table Results</h2>
                            <div class="mt-2 text-sm text-gray-600">
                                <p>{{ $pivotResult['metadata']['total_records'] }} records processed | {{ $pivotResult['summary']['total_rows'] }} rows | {{ $pivotResult['summary']['total_columns'] }} columns</p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button wire:click="downloadCsv" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                                Download CSV
                            </button>
                            <button wire:click="resetConfiguration" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="p-6 border-b border-gray-200 bg-gray-50">
                    <div class="grid grid-cols-3 gap-4 text-center">
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

                <!-- Pivot Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 sticky left-0 bg-gray-50">
                                    {{ implode(' / ', array_map(fn($key) => $availableFields['grouping'][$key] ?? $key, $pivotResult['metadata']['rows'])) ?: 'Total' }}
                                </th>
                                @php
                                    $allColumns = [];
                                    foreach ($pivotResult['data'] as $rowData) {
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
                            @foreach($pivotResult['data'] as $rowKey => $rowData)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-r border-gray-200 bg-gray-50 sticky left-0">
                                        {{ $rowKey }}
                                    </td>
                                    @foreach($allColumns as $columnKey)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-r border-gray-200">
                                            @if(isset($rowData[$columnKey]))
                                                @foreach($rowData[$columnKey] as $valueKey => $value)
                                                    <div class="mb-1 last:mb-0">
                                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                                                            {{ $availableFields['numeric'][$valueKey] ?? $valueKey }}: 
                                                            <span class="font-medium">{{ number_format($value, 2) }}</span>
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
            </div>
        @endif

        <!-- Data Preview Section (exactly like CSV version) -->
        @if(!empty($workOrderData))
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Data Preview</h2>
                    <p class="text-sm text-gray-600 mt-1">Showing first 5 rows of {{ count($workOrderData) }} total records</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Work Order No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BOM</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Part Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revision</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Machine</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operator</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OK Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">KO Qty</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach(array_slice($workOrderData, 0, 5) as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['work_order_no'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['bom'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['part_number'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['revision'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['machine'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['operator'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['status'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['qty'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['ok_qty'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row['ko_qty'] ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
