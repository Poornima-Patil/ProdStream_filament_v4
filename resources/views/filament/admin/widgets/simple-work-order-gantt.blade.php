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
    $weeks = [array_slice($days, 0, 7)]; // Single week
    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

<div x-data="{ expanded: false }" class="filament-widget" style="width: 100%; min-width: 1200px;">
    <div class="fi-wi-stats-overview-card relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="width: 100%; min-width: 1180px;">
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

        <!-- Gantt Chart Container with increased minimum width -->
                <!-- Gantt Chart Container -->
        <div class="overflow-x-auto">
            @if(count($workOrders) == 0)
                <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <div class="text-sm mb-2">📋 No work orders found</div>
                    <div class="text-xs">Try adjusting the date range or filters.</div>
                </div>
            @else
                <div class="w-full bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-4 min-w-[420px]">
                    <div class="mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Work Order Calendar View</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-300">Outlook-like Week view</p>
                    </div>

                    {{-- Calendar Header --}}
                    <div class="grid grid-cols-8">
                        <div class="bg-indigo-600 dark:bg-indigo-900 text-white text-xs font-semibold py-2 px-2 text-center rounded-tl-lg border border-gray-200 dark:border-gray-700">
                            Week
                        </div>
                        @foreach($dayNames as $day)
                            <div class="bg-indigo-100 dark:bg-indigo-800 text-indigo-900 dark:text-white text-xs font-semibold py-2 px-2 border border-gray-200 dark:border-gray-700 text-center">
                                {{ $day }}
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
                        @foreach($weeks as $weekIdx => $week)
                            @php
                                $rowId = "row_{$weekIdx}";
                                $weekNumber = $week[0]->format('W');
                                $maxBarsInRow = 0;
                                $cellBarsArr = [];
                                foreach($week as $dayIdx => $day) {
                                    $cellBars = [];
                                    $woStackOrder = [];
                                    $woIndex = 0;
                                    foreach ($workOrders as $wo) {
                                        $plannedStart = \Carbon\Carbon::parse($wo['start_date'])->startOfDay();
                                        $plannedEnd = \Carbon\Carbon::parse($wo['end_date'])->endOfDay();
                                        
                                        // Get actual dates from logs if available
                                        $hasActualDates = !empty($wo['actual_start_date']) || !empty($wo['actual_end_date']);
                                        $actualStart = null;
                                        $actualEnd = null;
                                        
                                        if ($hasActualDates) {
                                            $actualStart = $wo['actual_start_date'] ? \Carbon\Carbon::parse($wo['actual_start_date'])->startOfDay() : null;
                                            $actualEnd = $wo['actual_end_date'] ? \Carbon\Carbon::parse($wo['actual_end_date'])->endOfDay() : null;
                                        }

                                        if ($plannedStart <= $day && $plannedEnd >= $day) {
                                            $woStackOrder[$wo['id']] = $woIndex++;
                                            $cellBars[] = [
                                                'type' => 'planned',
                                                'wo' => $wo,
                                                'stackIdx' => $woStackOrder[$wo['id']],
                                            ];
                                        }
                                        if ($actualStart && $actualEnd && $actualStart <= $day && $actualEnd >= $day) {
                                            $woStackOrder[$wo['id']] = $woIndex++;
                                            $cellBars[] = [
                                                'type' => 'actual',
                                                'wo' => $wo,
                                                'stackIdx' => $woStackOrder[$wo['id']],
                                            ];
                                        }
                                    }
                                    usort($cellBars, fn($a, $b) => $a['stackIdx'] <=> $b['stackIdx']);
                                    $cellBarsArr[$dayIdx] = $cellBars;
                                    $maxBarsInRow = max($maxBarsInRow, count($cellBars));
                                }
                                $maxVisibleBars = 2;
                                $collapsedHeight = 20 + ($maxVisibleBars * 24) + (count($cellBarsArr) > $maxVisibleBars ? 36 : 0);
                                $expandedHeight = 20 + ($maxBarsInRow * 24) + 36;
                            @endphp
                            <div class="grid grid-cols-8 min-h-[80px] border-b border-gray-200 dark:border-gray-700 relative" id="{{ $rowId }}">
                                {{-- Week number cell --}}
                                <div class="flex items-center justify-center bg-indigo-200 dark:bg-indigo-800 text-indigo-900 dark:text-white text-xs font-bold border-r border-gray-200 dark:border-gray-700">
                                    {{ $weekNumber }}
                                </div>
                                @foreach($week as $dayIdx => $day)
                                    @php
                                        $cellId = "cell_{$weekIdx}_{$dayIdx}";
                                        $cellBars = $cellBarsArr[$dayIdx];
                                        $visibleBars = array_slice($cellBars, 0, $maxVisibleBars);
                                        $hiddenBars = array_slice($cellBars, $maxVisibleBars);
                                        $cellTotalBars = count($cellBars);
                                    @endphp
                                    <div 
                                        class="relative min-h-[80px] border-r border-b border-gray-200 dark:border-gray-700 last:border-r-0 bg-white dark:bg-gray-900 group transition-all duration-200"
                                        id="{{ $cellId }}_container"
                                        data-row="{{ $rowId }}"
                                        data-expanded="false"
                                        style="height: {{ $collapsedHeight }}px;"
                                    >
                                        <div class="absolute top-1 left-1 text-xs font-semibold {{ $day->isToday() ? 'text-blue-600 dark:text-blue-300' : 'text-gray-700 dark:text-gray-200' }}">
                                            {{ $day->format('j') }}
                                        </div>
                                        {{-- Bars --}}
                                        <div id="{{ $cellId }}_bars" class="pb-8">
                                            @foreach($cellBars as $barIdx => $bar)
                                                @php
                                                    $wo = $bar['wo'];
                                                    $stackIdx = $bar['stackIdx'];
                                                    $barTop = 20 + $stackIdx * 24;
                                                    $isHidden = $barIdx >= $maxVisibleBars;
                                                    $barColor = $bar['type'] === 'planned' ? '#3b82f6' : '#10B981';
                                                    $barBgClass = $bar['type'] === 'planned' ? 'bg-blue-500 dark:bg-blue-700' : 'bg-green-500 dark:bg-green-700';
                                                    
                                                    // Calculate percentage for actual bars
                                                    $totalQty = $wo['qty'] ?? 0;
                                                    $okQtys = $wo['ok_qtys'] ?? 0;
                                                    $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                                    
                                                    // Determine display text
                                                    if($bar['type'] === 'planned') {
                                                        $displayText = $wo['unique_id'];
                                                    } else {
                                                        $displayText = $percent > 0 ? $percent . '%' : $wo['unique_id'];
                                                    }
                                                @endphp
                                                <a href="{{ url('admin/' . (auth()->user()?->factory_id ?? 1) . '/work-orders/' . $wo['id']) }}"
                                                    class="absolute left-1 right-1 h-5 rounded flex items-center shadow hover:bg-blue-700 dark:hover:bg-blue-800 transition group {{ $barBgClass }}"
                                                    style="top: {{ $barTop }}px; z-index: 10; text-decoration: none; {{ $isHidden ? 'display:none;' : '' }}"
                                                    data-bar="{{ $cellId }}_bar_{{ $barIdx }}"
                                                    title="{{ $bar['type'] === 'planned' ? 'Planned' : 'Actual' }}: {{ $wo['unique_id'] }}">
                                                    <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: 20px;">
                                                        {{ $displayText }}
                                                    </span>
                                                </a>
                                            @endforeach

                                            @if(count($hiddenBars) > 0)
                                                <button 
                                                    id="{{ $cellId }}_expand"
                                                    class="right-2 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 border border-gray-300 dark:border-gray-600 mb-2 text-gray-900 dark:text-gray-100"
                                                    style="position: absolute; left: 8px; right: 8px; bottom: 4px; margin-bottom: 0;"
                                                    onclick="
                                                        this.closest('[data-row]').setAttribute('data-expanded', 'true');
                                                        document.querySelectorAll('[data-row={{ $rowId }}]').forEach(function(cell){
                                                            cell.style.height = '{{ $expandedHeight }}px';
                                                        });
                                                        @for($i = $maxVisibleBars; $i < $cellTotalBars; $i++)
                                                            document.querySelector('[data-bar={{ $cellId }}_bar_{{ $i }}]').style.display = 'flex';
                                                        @endfor
                                                        this.style.display='none';
                                                        document.getElementById('{{ $cellId }}_collapse').style.display='flex';
                                                    "
                                                    type="button"
                                                >
                                                    <svg class="w-3 h-3 mr-1 text-gray-900 dark:text-gray-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                    +{{ count($hiddenBars) }} more
                                                </button>

                                                <button
                                                    id="{{ $cellId }}_collapse"
                                                    class="right-2 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 border border-gray-300 dark:border-gray-600 mb-2 text-gray-900 dark:text-gray-100"
                                                    style="display:none; position: absolute; left: 8px; right: 8px; bottom: 4px; margin-bottom: 0;"
                                                    onclick="
                                                        this.closest('[data-row]').setAttribute('data-expanded', 'false');
                                                        @for($i = $maxVisibleBars; $i < $cellTotalBars; $i++)
                                                            document.querySelector('[data-bar={{ $cellId }}_bar_{{ $i }}]').style.display = 'none';
                                                        @endfor
                                                        this.style.display='none';
                                                        document.getElementById('{{ $cellId }}_expand').style.display='flex';

                                                        // Only collapse row if ALL cells are collapsed
                                                        let allCollapsed = true;
                                                        document.querySelectorAll('[data-row={{ $rowId }}][data-expanded]').forEach(function(cell){
                                                            if(cell.getAttribute('data-expanded') === 'true') allCollapsed = false;
                                                        });
                                                        if(allCollapsed) {
                                                            document.querySelectorAll('[data-row={{ $rowId }}]').forEach(function(cell){
                                                                cell.style.height = '{{ $collapsedHeight }}px';
                                                            });
                                                        }
                                                    "
                                                    type="button"
                                                >
                                                    <svg class="w-3 h-3 mr-1 text-gray-900 dark:text-gray-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                                    </svg>
                                                    Collapse
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
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
