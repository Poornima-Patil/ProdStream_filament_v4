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
    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

<div x-data="{ expanded: false }" class="filament-widget">
    <div class="fi-wi-stats-overview-card relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <!-- Header with navigation and expand/collapse -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <h3 class="fi-wi-stats-overview-card-label text-sm font-medium text-gray-500 dark:text-gray-400">
                    Work Order Gantt Chart
                </h3>
                <button 
                    @click="expanded = !expanded"
                    class="fi-btn fi-btn-size-sm fi-color-gray fi-btn-color-gray inline-flex items-center gap-1.5 justify-center font-semibold outline-none transition duration-75 focus:ring-2 rounded-lg bg-white text-gray-950 hover:bg-gray-50 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 dark:focus:ring-primary-500 px-2.5 py-1.5 text-xs ring-1 ring-gray-950/10 dark:ring-white/20"
                    type="button">
                    <span x-show="!expanded" class="text-xs">📊 Expand</span>
                    <span x-show="expanded" class="text-xs">📉 Collapse</span>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="previousWeek" class="fi-btn fi-btn-size-sm fi-color-gray inline-flex items-center justify-center font-semibold outline-none transition duration-75 focus:ring-2 rounded-lg bg-white text-gray-950 hover:bg-gray-50 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 px-2.5 py-1.5 text-xs ring-1 ring-gray-950/10 dark:ring-white/20">
                    Previous
                </button>
                <button wire:click="today" class="fi-btn fi-btn-size-sm fi-color-primary inline-flex items-center justify-center font-semibold outline-none transition duration-75 focus:ring-2 rounded-lg bg-primary-600 text-white hover:bg-primary-500 focus:ring-primary-600 px-2.5 py-1.5 text-xs">
                    Today
                </button>
                <button wire:click="nextWeek" class="fi-btn fi-btn-size-sm fi-color-gray inline-flex items-center justify-center font-semibold outline-none transition duration-75 focus:ring-2 rounded-lg bg-white text-gray-950 hover:bg-gray-50 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 px-2.5 py-1.5 text-xs ring-1 ring-gray-950/10 dark:ring-white/20">
                    Next
                </button>
            </div>
        </div>
        
        <!-- Week info -->
        <div class="flex justify-between items-center mb-4 text-sm text-gray-600 dark:text-gray-400">
            <span>Week {{ $startDate->format('W') }}: {{ $startDate->format('M j') }} - {{ $endDate->format('M j, Y') }}</span>
            <span class="text-xs">{{ count($workOrders) }} Work Orders</span>
        </div>

        <!-- Gantt Chart Container -->
        <div class="relative border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="overflow-auto" :class="{ 'max-h-80': !expanded, 'max-h-none': expanded }">
                @if(count($workOrders) == 0)
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <div class="text-sm mb-2">📋 No work orders found</div>
                        <div class="text-xs">Try adjusting the date range or filters.</div>
                    </div>
                @else
                    <div class="min-w-max">
                        <!-- Header -->
                        <div class="grid grid-cols-8 bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
                            <div class="px-3 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 border-r border-gray-300 dark:border-gray-600" style="min-width: 70px;">
                                Week
                            </div>
                            @foreach($dayNames as $index => $dayName)
                                @php $day = $days[$index] ?? null; @endphp
                                <div class="px-2 py-2 text-center text-xs font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-600 last:border-r-0" style="min-width: 110px;">
                                    <div>{{ $dayName }}</div>
                                    @if($day)
                                        <div class="text-xs font-normal mt-0.5 {{ $day->isToday() ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $day->format('M j') }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <!-- Content -->
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
                            
                            // Calculate height
                            $maxWorkOrdersInDay = max(array_map('count', $weekWorkOrders + [0]));
                            $minHeight = max(100, ($maxWorkOrdersInDay * 30) + 40);
                        @endphp
                        
                        <div class="grid grid-cols-8" style="min-height: {{ $minHeight }}px;">
                            <!-- Week Number -->
                            <div class="bg-gray-200 dark:bg-gray-700 border-r border-gray-300 dark:border-gray-600 flex items-center justify-center font-medium text-sm text-gray-800 dark:text-gray-200" style="min-width: 70px;">
                                {{ $days[0]->format('W') }}
                            </div>
                            
                            <!-- Days -->
                            @foreach($days as $dayIndex => $day)
                                @php
                                    $dayWorkOrders = $weekWorkOrders[$dayIndex] ?? [];
                                    $isToday = $day->isToday();
                                @endphp
                                
                                <div class="border-r border-gray-200 dark:border-gray-600 last:border-r-0 p-2 relative {{ $isToday ? 'bg-blue-50 dark:bg-blue-950/50' : 'bg-white dark:bg-gray-900' }}" 
                                     style="min-width: 110px;">
                                    
                                    <!-- Day Number -->
                                    <div class="text-center mb-2">
                                        <div class="text-sm font-semibold {{ $isToday ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300' }}">
                                            {{ $day->format('j') }}
                                        </div>
                                    </div>
                                    
                                    <!-- Work Orders -->
                                    <div class="space-y-1">
                                        @php $processedWorkOrders = []; @endphp
                                        
                                        @foreach($dayWorkOrders as $wo)
                                            @if(!in_array($wo['id'], $processedWorkOrders))
                                                @php
                                                    $processedWorkOrders[] = $wo['id'];
                                                    
                                                    // Calculate completion percentage
                                                    $totalQty = $wo['qty'] ?? 0;
                                                    $okQtys = $wo['ok_qtys'] ?? 0;
                                                    $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                                    
                                                    // Create URL
                                                    $woUrl = url('admin/' . (auth()->user()?->factory_id ?? 1) . '/work-orders/' . $wo['id']);
                                                    
                                                    // Check dates
                                                    $hasActualDates = !empty($wo['actual_start_date']) || !empty($wo['actual_end_date']);
                                                    $showPlanned = $wo['start_date'] <= $day->format('Y-m-d') && $wo['end_date'] >= $day->format('Y-m-d');
                                                    $showActual = $hasActualDates && 
                                                                 (($wo['actual_start_date'] && $wo['actual_start_date'] <= $day->format('Y-m-d')) && 
                                                                  ($wo['actual_end_date'] && $wo['actual_end_date'] >= $day->format('Y-m-d')));
                                                @endphp
                                                
                                                <div class="work-order-block">
                                                    {{-- Planned Bar (Blue) --}}
                                                    @if($showPlanned)
                                                        <a href="{{ $woUrl }}" 
                                                           target="_blank"
                                                           class="block w-full h-4 bg-blue-500 hover:bg-blue-600 rounded text-white text-xs text-center leading-4 mb-0.5 transition-colors"
                                                           title="Planned: {{ $wo['unique_id'] }} | Status: {{ $wo['status'] }} | Machine: {{ $wo['machine_name'] ?? 'N/A' }} | Operator: {{ $wo['operator_name'] ?? 'Unassigned' }} | Qty: {{ $wo['qty'] }}">
                                                            <span class="truncate px-1">{{ $wo['unique_id'] }}</span>
                                                        </a>
                                                    @endif
                                                    
                                                    {{-- Actual Bar (Green) --}}
                                                    @if($showActual)
                                                        <a href="{{ $woUrl }}" 
                                                           target="_blank"
                                                           class="block w-full h-4 bg-green-500 hover:bg-green-600 rounded text-white text-xs text-center leading-4 transition-colors"
                                                           title="Actual: {{ $wo['unique_id'] }} | Completion: {{ $percent }}% | OK Qty: {{ $okQtys }}/{{ $totalQty }} | Status: {{ $wo['status'] }}">
                                                            <span class="truncate px-1">{{ $percent > 0 ? $percent . '%' : $wo['unique_id'] }}</span>
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                        
                                        @if(count($dayWorkOrders) > 8)
                                            <div class="text-center text-xs text-gray-500 dark:text-gray-400 pt-1">
                                                +{{ count(array_unique(array_column($dayWorkOrders, 'id'))) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Legend -->
        <div class="mt-4 flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 bg-blue-500 rounded"></div>
                    <span>Planned (Work Order ID)</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 bg-green-500 rounded"></div>
                    <span>Actual (Completion %)</span>
                </div>
            </div>
            <div class="text-xs">
                Click bars to view details
            </div>
        </div>
    </div>
</div>
