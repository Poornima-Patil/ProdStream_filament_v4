@php
    use Illuminate\Support\Carbon;

    // Use selectedDate if provided, otherwise use current date for week calculation
    $centerDate = isset($selectedDate) ? Carbon::parse($selectedDate) : now();
    
    // For weekly view, show the week containing the selected date
    $startDate = $centerDate->copy()->startOfWeek();
    $endDate = $centerDate->copy()->endOfWeek();

    // Generate days for the week
    $days = [];
    $current = $startDate->copy();
    while ($current <= $endDate) {
        $days[] = $current->copy();
        $current->addDay();
    }
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
@endphp

<div x-data="{ expanded: false }" class="w-full">
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Header with navigation and expand/collapse like Advanced Gantt Chart -->
        <div class="flex justify-between items-center p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-4">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Work Order Gantt Chart</h2>
                <button @click="expanded = !expanded" 
                        class="flex items-center space-x-1 px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded text-gray-700 dark:text-gray-300 transition-colors">
                    <span x-show="!expanded">📊 Expand</span>
                    <span x-show="expanded">📉 Collapse</span>
                </button>
            </div>
            <div class="flex space-x-2">
                <button wire:click="previousWeek" class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded text-gray-700 dark:text-gray-300 transition-colors">Previous</button>
                <button wire:click="today" class="px-3 py-1 text-sm bg-blue-100 hover:bg-blue-200 dark:bg-blue-800 dark:hover:bg-blue-700 rounded text-blue-700 dark:text-blue-300 transition-colors">Today</button>
                <button wire:click="nextWeek" class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded text-gray-700 dark:text-gray-300 transition-colors">Next</button>
            </div>
        </div>
        
        <!-- Current week display -->
        <div class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <span>Week {{ $startDate->format('W') }}: {{ $startDate->format('M j') }} - {{ $endDate->format('M j, Y') }}</span>
                <span class="text-xs">{{ count($workOrders) }} Work Orders</span>
            </div>
        </div>

        <!-- Gantt Chart Container with full width -->
        <div class="overflow-x-auto" :class="{ 'max-h-96': !expanded, 'max-h-none': expanded }">
            <div class="min-w-full" style="min-width: 1400px;">
                @if(count($workOrders) == 0)
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <div class="text-lg mb-2">📋</div>
                        <div>No work orders found for the current view period.</div>
                        <div class="text-sm mt-1">Try adjusting the date range or filters.</div>
                    </div>
                @else
                    <!-- Calendar Header with full width -->
                    <div class="grid grid-cols-8 bg-blue-50 dark:bg-blue-900 border-b-2 border-blue-200 dark:border-blue-800 sticky top-0 z-10">
                        <div class="p-4 text-center font-semibold text-sm text-gray-800 dark:text-gray-200 bg-blue-100 dark:bg-blue-800 border-r border-blue-200 dark:border-blue-700" style="min-width: 100px;">
                            Week
                        </div>
                        @foreach($dayNames as $index => $dayName)
                            @php $day = $days[$index] ?? null; @endphp
                            <div class="p-4 text-center font-semibold text-sm text-gray-800 dark:text-gray-200 border-r border-blue-200 dark:border-blue-700 last:border-r-0" style="min-width: 180px;">
                                <div>{{ $dayName }}</div>
                                @if($day)
                                    <div class="text-xs font-normal mt-1 {{ $day->isToday() ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $day->format('M j') }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <!-- Calendar Body -->
                    @php
                        // Group work orders by day
                        $weekWorkOrders = [];
                        foreach($days as $dayIndex => $day) {
                            $weekWorkOrders[$dayIndex] = [];
                            foreach($workOrders as $wo) {
                                $startDate = Carbon::parse($wo['start_date']);
                                $endDate = Carbon::parse($wo['end_date']);
                                
                                // Check if work order spans this day
                                if ($startDate <= $day && $endDate >= $day) {
                                    $weekWorkOrders[$dayIndex][] = $wo;
                                }
                            }
                        }
                        
                        // Calculate minimum height based on max work orders in any day
                        $maxWorkOrdersInDay = max(array_map('count', $weekWorkOrders + [0]));
                        $minHeight = max(200, ($maxWorkOrdersInDay * 60) + 100); // More space per work order
                    @endphp
                    
                    <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700" style="min-height: {{ $minHeight }}px;">
                        <!-- Week Number -->
                        <div class="bg-blue-100 dark:bg-blue-800 border-r border-gray-200 dark:border-gray-700 flex items-center justify-center font-semibold text-lg text-gray-800 dark:text-gray-200" style="min-width: 100px;">
                            {{ $days[0]->format('W') }}
                        </div>
                        
                        <!-- Days -->
                        @foreach($days as $dayIndex => $day)
                            @php
                                $dayWorkOrders = $weekWorkOrders[$dayIndex] ?? [];
                                $isToday = $day->isToday();
                            @endphp
                            
                            <div class="border-r border-gray-200 dark:border-gray-700 last:border-r-0 p-3 relative {{ $isToday ? 'bg-blue-50 dark:bg-blue-950' : 'bg-white dark:bg-gray-900' }}" 
                                 style="min-width: 180px; min-height: {{ $minHeight }}px;">
                                
                                <!-- Day Number -->
                                <div class="text-center mb-3 pb-2 border-b border-gray-100 dark:border-gray-700">
                                    <div class="text-lg font-bold {{ $isToday ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $day->format('j') }}
                                    </div>
                                </div>
                                
                                <!-- Work Orders for this day -->
                                <div class="space-y-2">
                                    @php $processedWorkOrders = []; @endphp
                                    
                                    @foreach($dayWorkOrders as $wo)
                                        @if(!in_array($wo['id'], $processedWorkOrders))
                                            @php
                                                $processedWorkOrders[] = $wo['id'];
                                                
                                                // Calculate completion percentage
                                                $totalQty = $wo['qty'] ?? 0;
                                                $okQtys = $wo['ok_qtys'] ?? 0;
                                                $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                                
                                                // Create URL to work order
                                                $woUrl = url('admin/' . (auth()->user()?->factory_id ?? 1) . '/work-orders/' . $wo['id']);
                                                
                                                // Check if we have actual dates (work order has been started)
                                                $hasActualDates = !empty($wo['actual_start_date']) || !empty($wo['actual_end_date']);
                                                
                                                // Show planned bar if work order has planned dates for this day
                                                $showPlanned = $wo['start_date'] <= $day->format('Y-m-d') && $wo['end_date'] >= $day->format('Y-m-d');
                                                
                                                // Show actual bar if work order has actual dates for this day
                                                $showActual = $hasActualDates && 
                                                             (($wo['actual_start_date'] && $wo['actual_start_date'] <= $day->format('Y-m-d')) && 
                                                              ($wo['actual_end_date'] && $wo['actual_end_date'] >= $day->format('Y-m-d')));
                                            @endphp
                                            
                                            <div class="work-order-container mb-3">
                                                {{-- Planned Bar (Blue) --}}
                                                @if($showPlanned)
                                                    <a href="{{ $woUrl }}" 
                                                       target="_blank"
                                                       class="block w-full h-8 bg-blue-500 hover:bg-blue-600 rounded-md px-3 py-1 text-white text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md mb-1"
                                                       title="Planned: {{ $wo['unique_id'] }} | Status: {{ $wo['status'] }} | Machine: {{ $wo['machine_name'] }} | Operator: {{ $wo['operator_name'] }} | Qty: {{ $wo['qty'] }}">
                                                        <div class="flex items-center justify-between h-full">
                                                            <span class="truncate text-sm font-semibold">{{ $wo['unique_id'] }}</span>
                                                            <span class="text-xs opacity-75">📋</span>
                                                        </div>
                                                    </a>
                                                @endif
                                                
                                                {{-- Actual Bar (Green) --}}
                                                @if($showActual)
                                                    <a href="{{ $woUrl }}" 
                                                       target="_blank"
                                                       class="block w-full h-8 bg-green-500 hover:bg-green-600 rounded-md px-3 py-1 text-white text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md"
                                                       title="Actual: {{ $wo['unique_id'] }} | Completion: {{ $percent }}% | OK Qty: {{ $okQtys }}/{{ $totalQty }} | Status: {{ $wo['status'] }}">
                                                        <div class="flex items-center justify-between h-full">
                                                            <span class="truncate text-sm font-semibold">{{ $percent > 0 ? $percent . '%' : $wo['unique_id'] }}</span>
                                                            <span class="text-xs opacity-75">✅</span>
                                                        </div>
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                    
                                    <!-- Show count if many work orders -->
                                    @if(count($dayWorkOrders) > 6)
                                        <div class="text-center text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700">
                                            +{{ count(array_unique(array_column($dayWorkOrders, 'id'))) }} work orders
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <!-- Legend with improved styling -->
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex flex-wrap gap-6 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-blue-500 rounded"></div>
                                <span class="text-gray-600 dark:text-gray-400">Planned (Work Order ID)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-green-500 rounded"></div>
                                <span class="text-gray-600 dark:text-gray-400">Actual (Completion %)</span>
                            </div>
                            <div class="ml-auto text-xs text-gray-500 dark:text-gray-400">
                                Click bars to view work order details
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
