<div class="max-w-7xl mx-auto p-6">
    <!-- Interactive Pivot Table Builder -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-white">Interactive Pivot Table</h2>
                <p class="text-gray-400 mt-1">Drag and drop fields to create custom views of your work order data</p>
            </div>
        </div>

        <!-- Available Fields (Top Section) -->
        <div class="mb-6">
            <h3 class="text-lg font-medium text-white mb-4">Available Fields</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($availableFields as $key => $label)
                    <div 
                        draggable="true" 
                        ondragstart="dragStart(event, '{{ $key }}', '{{ $label }}')"
                        class="bg-blue-600 hover:bg-blue-700 px-3 py-2 rounded-md text-white text-sm cursor-move transition-colors duration-200 flex items-center gap-2"
                    >
                        <span>{{ $label }}</span>
                        <svg class="w-4 h-4 text-blue-200" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M7 2a1 1 0 011 1v4h4V3a1 1 0 112 0v4h1a1 1 0 110 2h-1v4h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1H8v1a1 1 0 11-2 0v-1H5a1 1 0 110-2h1V9H5a1 1 0 010-2h1V3a1 1 0 011-1z"/>
                        </svg>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Drop Zones (Horizontal Layout) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Rows Drop Zone -->
            <div class="space-y-2">
                <h3 class="text-lg font-medium text-blue-700 dark:text-white flex items-center">
                    <span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                    Rows
                </h3>
                <div 
                    ondrop="drop(event, 'rows')" 
                    ondragover="allowDrop(event)"
                    ondragenter="dragEnter(event)"
                    ondragleave="dragLeave(event)"
                    class="min-h-[120px] border-2 border-dashed border-blue-600 rounded-lg p-4 bg-blue-600/10 hover:bg-blue-600/20 transition-colors duration-200"
                    id="rows-zone"
                >
                    <p class="text-blue-400 text-sm text-center mb-3">Drag fields here</p>
                    <div class="space-y-2" id="rows-content">
                        @foreach(($rowFields ?? []) as $field)
                            <div class="bg-blue-600 px-3 py-2 rounded text-white text-sm flex items-center justify-between">
                                <span>{{ $field['label'] }}</span>
                                <button 
                                    wire:click="removeField('rows', '{{ $field['key'] }}')"
                                    class="text-blue-200 hover:text-white ml-2"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Columns Drop Zone -->
            <div class="space-y-2">
                <h3 class="text-lg font-medium text-green-700 dark:text-white flex items-center">
                    <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                    Columns
                </h3>
                <div 
                    ondrop="drop(event, 'columns')" 
                    ondragover="allowDrop(event)"
                    ondragenter="dragEnter(event)"
                    ondragleave="dragLeave(event)"
                    class="min-h-[120px] border-2 border-dashed border-green-600 rounded-lg p-4 bg-green-600/10 hover:bg-green-600/20 transition-colors duration-200"
                    id="columns-zone"
                >
                    <p class="text-green-400 text-sm text-center mb-3">Drag fields here</p>
                    <div class="space-y-2" id="columns-content">
                        @foreach(($columnFields ?? []) as $field)
                            <div class="bg-green-100 dark:bg-green-600 px-3 py-2 rounded text-green-700 dark:text-white text-sm flex items-center justify-between">
                                <span>{{ $field['label'] }}</span>
                                <button 
                                    wire:click="removeField('columns', '{{ $field['key'] }}')"
                                    class="text-green-500 dark:text-green-200 hover:text-green-700 dark:hover:text-white ml-2"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Values Drop Zone -->
            <div class="space-y-2">
                <h3 class="text-lg font-medium text-orange-700 dark:text-white flex items-center">
                    <span class="w-3 h-3 bg-orange-500 rounded-full mr-2"></span>
                    <span class="text-orange-700 dark:text-orange-400">Values</span>
                </h3>
                <div 
                    ondrop="drop(event, 'values')" 
                    ondragover="allowDrop(event)"
                    ondragenter="dragEnter(event)"
                    ondragleave="dragLeave(event)"
                    class="min-h-[120px] border-2 border-dashed border-orange-600 rounded-lg p-4 bg-orange-600/10 hover:bg-orange-600/20 transition-colors duration-200"
                    id="values-zone"
                >
                    <p class="text-orange-700 dark:text-orange-400 text-sm text-center mb-3">Drag fields here</p>
                    <div class="space-y-2" id="values-content">
                        @foreach(($valueFields ?? []) as $field)
                            <div class="bg-orange-100 dark:bg-orange-600 px-3 py-2 rounded text-orange-700 dark:text-white text-sm flex items-center justify-between">
                                <span>{{ $field['label'] }}</span>
                                <button 
                                    wire:click="removeField('values', '{{ $field['key'] }}')"
                                    class="text-orange-500 dark:text-orange-200 hover:text-orange-700 dark:hover:text-white ml-2"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3">
            <button wire:click="generatePivotTable" 
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium transition-colors">
                Generate Pivot Table
            </button>
            <button wire:click="clearAll" 
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 dark:bg-gray-500 dark:hover:bg-gray-600 dark:text-white rounded-md text-sm font-medium transition-colors border border-gray-300 dark:border-gray-600">
                Clear All
            </button>
            <button wire:click="exportToExcel" 
                    class="px-4 py-2 bg-green-100 hover:bg-green-200 text-green-700 dark:bg-green-600 dark:hover:bg-green-700 dark:text-white rounded-md text-sm font-medium transition-colors border border-green-300 dark:border-green-600">
                Export to Excel
            </button>
        </div>
    </div>

    <!-- Pivot Table Results -->
    @if(!empty($pivotData) && !empty($pivotData['headers']))
        <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-semibold text-white">Pivot Table Results</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-700">
                        <tr>
                            @foreach($pivotData['headers'] as $header)
                                    <th class="px-6 py-3 text-left text-xs font-medium 
                                        @if(strtolower($header) === 'work order no' || strtolower($header) === 'wo number') text-blue-700 dark:text-blue-300 
                                        @else text-gray-700 dark:text-gray-300 @endif uppercase tracking-wider">
                                        {{ $header }}
                                    </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($pivotData['rows'] as $row)
                            <tr class="hover:bg-blue-50 dark:hover:bg-gray-700">
                                @foreach($row as $index => $cell)
                                    @if($index === 0)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-700 dark:text-blue-300">
                                            {{ $cell }}
                                        </td>
                                    @else
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            {{ $cell }}
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(empty($pivotData['rows']))
                <div class="text-center py-8">
                    <p class="text-gray-400">No data available for the selected configuration.</p>
                </div>
            @endif
        </div>
    @elseif((count($rowFields ?? []) > 0 || count($columnFields ?? []) > 0) && count($valueFields ?? []) > 0)
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-400">Generating pivot table...</p>
            </div>
        </div>
    @else
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
            <div class="text-center py-8">
                <svg class="w-16 h-16 text-gray-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0V5a2 2 0 012-2h2a2 2 0 002-2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <h3 class="text-lg font-semibold text-white mb-2">Create Your Pivot Table</h3>
                <p class="text-gray-400 mb-4">
                    Drag fields from Available Fields into Rows, Columns, and Values sections to get started.
                </p>
                <p class="text-sm text-gray-500">
                    Tip: Start by dragging "Work Order No" to Rows and "Qty" to Values for a simple summary.
                </p>
            </div>
        </div>
    @endif
</div>
