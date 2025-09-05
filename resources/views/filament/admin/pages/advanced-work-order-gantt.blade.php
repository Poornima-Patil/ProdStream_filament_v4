@php
    use Illuminate\Support\Facades\Auth;
@endphp
<x-filament::page>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col">
        <div class="flex-1 flex flex-col items-center justify-start py-8">
            <div class="w-full max-w-7xl bg-white dark:bg-gray-800 rounded-xl shadow-xl px-4 md:px-10 py-8">
                {{-- Main Heading --}}
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                    <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight mb-4 md:mb-0">
                        Advanced Work Order Gantt Chart
                    </h1>
                </div>
                <div class="flex flex-wrap items-center gap-4 mb-8">
                    <select id="timeRangeSelector" class="w-32 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 text-base focus:ring focus:ring-blue-200 dark:bg-gray-800 dark:text-gray-100">
                        <option value="week" {{ $timeRange === 'week' ? 'selected' : '' }}>Week</option>
                        <option value="day" {{ $timeRange === 'day' ? 'selected' : '' }}>Day</option>
                        <option value="month" {{ $timeRange === 'month' ? 'selected' : '' }}>Month</option>
                    </select>
                    <input 
                        type="{{ $timeRange === 'month' ? 'month' : ($timeRange === 'day' ? 'date' : 'week') }}" 
                        id="datePicker" 
                        class="w-40 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2 text-base focus:ring focus:ring-blue-200 dark:bg-gray-800 dark:text-gray-100" 
                        value="{{ $timeRange === 'week' ? \Carbon\Carbon::parse($selectedDate)->format('Y-\WW') : $selectedDate }}" />

                    {{-- Color Legend Tab --}}
                    @php
                        $statusColors = config('work_order_status');
                        $legend = [
                            'Assigned' => $statusColors['assigned'],
                            'Start' => $statusColors['start'],
                            'Hold' => $statusColors['hold'],
                            'Completed' => $statusColors['completed'],
                            'Closed' => $statusColors['closed'],
                        ];
                    @endphp
                    <div class="flex items-center gap-4 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded px-3 py-1 ml-2">
                        @foreach($legend as $status => $color)
                            <div class="flex items-center gap-2">
                                <span class="inline-block w-4 h-4 rounded" style="background: {{ $color }}"></span>
                                <span class="text-xs text-gray-700 dark:text-gray-200">{{ $status }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Time Range & Selected Date --}}
                <div class="mb-8 flex flex-col md:flex-row md:items-center md:space-x-6">
                    <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-800 rounded-lg px-6 py-3 mb-3 md:mb-0 flex-1">
                        <span class="block text-lg font-semibold text-blue-700 dark:text-blue-200">Time Range</span>
                        <span class="block text-xl font-bold text-blue-900 dark:text-blue-100">{{ ucfirst($timeRange) }}</span>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-800 rounded-lg px-6 py-3 flex-1">
                        <span class="block text-lg font-semibold text-green-700 dark:text-green-200">Selected Date</span>
                        <span class="block text-xl font-bold text-green-900 dark:text-green-100">{{ $selectedDate }}</span>
                    </div>
                </div>

                {{-- Calendar View (Month/Week) --}}
                @if($timeRange === 'month' || $timeRange === 'week')
                @php
                    $carbonSelected = \Carbon\Carbon::parse($selectedDate);

                    if ($timeRange === 'month') {
                        $firstDay = $carbonSelected->copy()->startOfMonth()->startOfWeek();
                        $lastDay = $carbonSelected->copy()->endOfMonth()->endOfWeek();
                    } else {
                        $firstDay = $carbonSelected->copy()->startOfWeek();
                        $lastDay = $carbonSelected->copy()->endOfWeek();
                    }

                    $days = [];
                    $current = $firstDay->copy();
                    while ($current <= $lastDay) {
                        $days[] = $current->copy();
                        $current->addDay();
                    }
                    $weeks = array_chunk($days, 7);
                    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                @endphp

                <div class="overflow-x-auto">
                    <div class="w-full bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-4 min-w-[420px]">
                        <div class="mb-4">
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Work Order Calendar View</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-300">Outlook-like {{ ucfirst($timeRange) }} view</p>
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
                                        $plannedStart = \Carbon\Carbon::parse($wo->start_time)->startOfDay();
                                        $plannedEnd = \Carbon\Carbon::parse($wo->end_time)->endOfDay();
                                        $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                        $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                        $actualStart = $actualStartLog ? \Carbon\Carbon::parse($actualStartLog->changed_at)->startOfDay() : null;
                                        $actualEnd = $actualEndLog ? \Carbon\Carbon::parse($actualEndLog->changed_at)->endOfDay() : null;

                                        if ($plannedStart <= $day && $plannedEnd >= $day) {
                                            $woStackOrder[$wo->id] = $woIndex++;
                                            $cellBars[] = [
                                                'type' => 'planned',
                                                'wo' => $wo,
                                                'stackIdx' => $woStackOrder[$wo->id],
                                            ];
                                        }
                                        if ($actualStart && $actualEnd && $actualStart <= $day && $actualEnd >= $day) {
                                            $woStackOrder[$wo->id] = $woIndex++;
                                            $cellBars[] = [
                                                'type' => 'actual',
                                                'wo' => $wo,
                                                'stackIdx' => $woStackOrder[$wo->id],
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
                                                    
                                                    // Calculate percentage for actual bars
                                                    $totalQty = $wo->qty ?? 0;
                                                    $okQtys = $wo->ok_qtys ?? 0;
                                                    $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                                    
                                                    // Add missing variables for calendar view
                                                    $factoryId = Auth::user()?->factory_id ?? 3;
                                                    $barHeight = 20; // Standard height for calendar view bars
                                                    $barWidthPercentage = 100; // Full width for calendar cells
                                                    $extraWidth = 0; // No extra width needed for calendar view
                                                    
                                                    if($bar['type'] === 'planned') {
                                                        // Planned bars always remain blue
                                                        $displayText = $wo->unique_id;
                                                    } else {
                                                        // Actual bars use status-based colors with progress
                                                        $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                                        $currentStatus = $actualEndLog ? strtolower($actualEndLog->status) : strtolower($wo->status);
                                                        
                                                        // Get status color for progress bar
                                                        $statusColor = match($currentStatus) {
                                                            'assigned' => '#6b7280',  // gray-500
                                                            'start' => '#eab308',     // yellow-500
                                                            'hold' => '#ef4444',      // red-500
                                                            'completed' => '#22c55e', // green-500
                                                            'closed' => '#a855f7',    // purple-500
                                                            default => '#eab308'      // yellow-500
                                                        };
                                                        
                                                        // For "Start" status with no progress, show minimum 20% fill to make it visible
                                                        $displayPercent = $percent;
                                                        if ($currentStatus === 'start' && $percent === 0) {
                                                            $displayPercent = 20; // Show 20% fill for visibility
                                                        }
                                                    }
                                                @endphp
            
                                                @if($bar['type'] === 'planned')
                                                    {{-- Planned bar (solid blue) --}}
                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                        class="absolute left-1 right-1 h-5 rounded flex items-center shadow hover:opacity-80 transition group bg-blue-500 dark:bg-blue-700"
                                                        style="top: {{ $barTop }}px; z-index: 10; text-decoration: none; {{ $isHidden ? 'display:none;' : '' }}"
                                                        data-bar="{{ $cellId }}_bar_{{ $barIdx }}"
                                                        title="Planned: {{ $wo->unique_id }}">
                                                        <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: 20px;">
                                                            {{ $displayText }}
                                                        </span>
                                                    </a>
                                                @else
                                                    {{-- Actual bar (progress bar style) --}}
                                                    @php
                                                        // Get the current status for coloring
                                                        $currentStatus = strtolower($wo->status);
                                                        
                                                        // Check if there's a more recent status from logs
                                                        $latestLog = $wo->workOrderLogs->sortByDesc('changed_at')->first();
                                                        if ($latestLog) {
                                                            $currentStatus = strtolower($latestLog->status);
                                                        }
                                                        
                                                        // Get status color for progress bar
                                                        $statusColor = match($currentStatus) {
                                                            'assigned' => '#6b7280',  // gray-500
                                                            'start' => '#eab308',     // yellow-500
                                                            'hold' => '#ef4444',      // red-500
                                                            'completed' => '#22c55e', // green-500
                                                            'closed' => '#a855f7',    // purple-500
                                                            default => '#eab308'      // yellow-500
                                                        };
                                                        
                                                        // For "Start" status with no progress, show minimum 20% fill to make it visible
                                                        $displayPercent = $percent;
                                                        if ($currentStatus === 'start' && $percent === 0) {
                                                            $displayPercent = 20; // Show 20% fill for visibility
                                                        }
                                                    @endphp
    
                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                        class="absolute left-1 right-1 h-5 rounded flex items-center shadow hover:opacity-80 transition group bg-gray-200 dark:bg-gray-700 overflow-hidden"
                                                        style="top: {{ $barTop }}px; z-index: 10; text-decoration: none; {{ $isHidden ? 'display:none;' : '' }}"
                                                        data-bar="{{ $cellId }}_bar_{{ $barIdx }}"
                                                        title="Actual: {{ $wo->unique_id }} ({{ ucfirst($currentStatus) }}) - {{ $percent > 0 ? $percent . '%' : 'Started' }} Complete">
                                                        
                                                        {{-- Progress fill with status color --}}
                                                        <div class="absolute top-0 left-0 h-full transition-all duration-300"
                                                             style="width: {{ $displayPercent }}%; background-color: {{ $statusColor }}; z-index: 1;"></div>
                                                        
                                                        {{-- Text overlay --}}
                                                        <span class="relative text-[10px] font-semibold px-2 truncate w-full z-10 mix-blend-difference text-white" 
                                                              style="line-height: 20px;">
                                                            {{ $currentStatus === 'start' && $percent === 0 ? 'STARTED' : $displayText }}
                                                        </span>
                                                    </a>
                                                @endif
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

                {{-- Day View: 2-Hour Interval Table Only --}}
                @if($timeRange === 'day')
                @php
                    $carbonSelected = \Carbon\Carbon::parse($selectedDate);
                    // Create 2-hour time slots: 00:00-02:00, 02:00-04:00, etc.
                    $timeSlots = [];
                    for ($hour = 0; $hour < 24; $hour += 2) {
                        $timeSlots[] = [
                            'start' => $hour,
                            'end' => min($hour + 2, 24),
                            'label' => str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00-' . str_pad(min($hour + 2, 24), 2, '0', STR_PAD_LEFT) . ':00'
                        ];
                    }
                    $selectedDay = $carbonSelected->copy()->startOfDay();
                    $selectedDayEnd = $carbonSelected->copy()->endOfDay();
                    
                    // First try the normal filtering
                    $filteredWOs = $workOrders->filter(function($wo) use ($selectedDay, $selectedDayEnd) {
                        $plannedStart = \Carbon\Carbon::parse($wo->start_time);
                        $plannedEnd = \Carbon\Carbon::parse($wo->end_time);
                        return $plannedStart <= $selectedDayEnd && $plannedEnd >= $selectedDay;
                    });
                    
                    // If no work orders found, let's try a broader search or show recent ones for debugging
                    if ($filteredWOs->isEmpty() && $workOrders->isNotEmpty()) {
                        // Show the first few work orders regardless of date for debugging
                        $filteredWOs = $workOrders->take(3);
                    }
                @endphp

                <div class="overflow-x-auto mt-8">
                    <div class="w-full bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-4">
                        <div class="mb-4">
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                                Work Order Day View (2-Hour Intervals)
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-300">Outlook-style day view with 2-hour time slots</p>
                        </div>
                        <div class="border rounded-lg overflow-hidden border-gray-200 dark:border-gray-700">
                            <table class="w-full table-fixed border-collapse">
                                <thead>
                                    <tr>
                                        <th class="w-40 text-xs text-gray-500 dark:text-gray-300 bg-blue-50 dark:bg-blue-900 border border-gray-200 dark:border-gray-700 p-2">Date</th>
                                        @foreach($timeSlots as $slot)
                                            <th class="text-xs text-gray-700 dark:text-gray-200 bg-yellow-50 dark:bg-yellow-900 border border-gray-200 dark:border-gray-700 p-2 text-center">
                                                {{ $slot['label'] }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $barHeight = 16;
                                        $barGap = 4;
                                        $cellPadding = 24;
                                        $expandCollapseButtonHeight = 32;
                                        $maxVisibleBars = 3;
                                        
                                        // Process all work orders to create spanning bars
                                        $plannedBars = [];
                                        $actualBars = [];
                                        $allBars = [];
                                        $barCounter = 0;
                                        
                                        foreach($filteredWOs as $wo) {
                                            $plannedStart = \Carbon\Carbon::parse($wo->start_time);
                                            $plannedEnd = \Carbon\Carbon::parse($wo->end_time);
                                            
                                            // Get actual start and end from logs
                                            $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                            $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                            $actualStart = $actualStartLog ? \Carbon\Carbon::parse($actualStartLog->changed_at) : null;
                                            $actualEnd = $actualEndLog ? \Carbon\Carbon::parse($actualEndLog->changed_at) : null;
                                            
                                            // Convert to 2-hour slot indices
                                            $plannedStartSlot = ($plannedStart && $plannedStart->isSameDay($selectedDay)) ? floor($plannedStart->hour / 2) : null;
                                            $plannedEndSlot = ($plannedEnd && $plannedEnd->isSameDay($selectedDay)) ? floor($plannedEnd->hour / 2) : null;
                                            $actualStartSlot = ($actualStart && $actualStart->isSameDay($selectedDay)) ? floor($actualStart->hour / 2) : null;
                                            $actualEndSlot = ($actualEnd && $actualEnd->isSameDay($selectedDay)) ? floor($actualEnd->hour / 2) : null;
                                            
                                            // Fix: Handle work orders that span across days
                                            // If work order starts before selected day but ends on selected day
                                            if($plannedStart < $selectedDay && $plannedEnd >= $selectedDay) {
                                                $plannedStartSlot = 0; // Start from first slot of the day
                                                $plannedEndSlot = $plannedEnd->isSameDay($selectedDay) ? floor($plannedEnd->hour / 2) : 11; // End slot or last slot
                                            }
                                            // If work order starts on selected day but ends after selected day  
                                            if($plannedStart->isSameDay($selectedDay) && $plannedEnd > $selectedDayEnd) {
                                                $plannedStartSlot = floor($plannedStart->hour / 2);
                                                $plannedEndSlot = 11; // Last slot of the day
                                            }
                                            
                                            // Same logic for actual times
                                            if($actualStart && $actualEnd) {
                                                if($actualStart < $selectedDay && $actualEnd >= $selectedDay) {
                                                    $actualStartSlot = 0;
                                                    $actualEndSlot = $actualEnd->isSameDay($selectedDay) ? floor($actualEnd->hour / 2) : 11;
                                                }
                                                if($actualStart->isSameDay($selectedDay) && $actualEnd > $selectedDayEnd) {
                                                    $actualStartSlot = floor($actualStart->hour / 2);
                                                    $actualEndSlot = 11;
                                                }
                                            }
                                            
                                            // Add planned work order as a spanning bar
                                            if($plannedStartSlot !== null && $plannedEndSlot !== null) {
                                                $spanSlots = max(1, $plannedEndSlot - $plannedStartSlot + 1);
                                                $allBars[] = [
                                                    'type' => 'planned',
                                                    'wo' => $wo,
                                                    'startSlot' => $plannedStartSlot,
                                                    'endSlot' => $plannedEndSlot,
                                                    'spanSlots' => $spanSlots,
                                                    'stackIdx' => $barCounter,
                                                    'id' => 'planned_' . $wo->id,
                                                ];
                                                $barCounter++;
                                            }
                                            
                                            // Add actual work order as a spanning bar
                                            if($actualStartSlot !== null && $actualEndSlot !== null) {
                                                $spanSlots = max(1, $actualEndSlot - $actualStartSlot + 1);
                                                $allBars[] = [
                                                    'type' => 'actual',
                                                    'wo' => $wo,
                                                    'startSlot' => $actualStartSlot,
                                                    'endSlot' => $actualEndSlot,
                                                    'spanSlots' => $spanSlots,
                                                    'stackIdx' => $barCounter,
                                                    'id' => 'actual_' . $wo->id,
                                                ];
                                                $barCounter++;
                                            }
                                        }
                                        
                                        $maxBarsInAnySlot = count($allBars);
                                        $rowCollapsedHeight = $cellPadding + (min($maxVisibleBars, $maxBarsInAnySlot) * ($barHeight + $barGap));
                                        if($maxBarsInAnySlot > $maxVisibleBars) {
                                            $rowCollapsedHeight += $expandCollapseButtonHeight;
                                        }
                                        $rowExpandedHeight = $cellPadding + ($maxBarsInAnySlot * ($barHeight + $barGap)) + $expandCollapseButtonHeight;
                                    @endphp
                                    
                                    {{-- Single row containing all work orders --}}
                                    <tr>
                                        <td class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-2 align-top text-gray-900 dark:text-gray-100">
                                            {{ $carbonSelected->format('M d, Y') }}
                                        </td>
                                        @foreach($timeSlots as $slotIndex => $slot)
                                            @php
                                                // Find bars that should be displayed in this slot
                                                $cellBars = [];
                                                foreach($allBars as $bar) {
                                                    // Only add bars that start in this slot (to avoid duplication)
                                                    if($bar['startSlot'] === $slotIndex) {
                                                        $cellBars[] = $bar;
                                                    }
                                                }
                                                
                                                $visibleBars = array_slice($cellBars, 0, $maxVisibleBars);
                                                $hiddenBars = array_slice($cellBars, $maxVisibleBars);
                                                $stackCount = count($cellBars);
                                                $collapsedHeight = $cellPadding + (count($visibleBars) * ($barHeight + $barGap));
                                                if(count($hiddenBars) > 0) {
                                                    $collapsedHeight += $expandCollapseButtonHeight;
                                                }
                                                $expandedHeight = max(
                                                    $cellPadding + $stackCount * ($barHeight + $barGap) + $expandCollapseButtonHeight,
                                                    60
                                                );
                                                $cellId = 'cell_' . $selectedDay->format('Ymd') . '_' . $slotIndex;
                                            @endphp
                                            <td class="relative border border-gray-200 dark:border-gray-700 align-top p-0 overflow-visible"
                                                style="height: {{ $collapsedHeight }}px; position: relative;"
                                                id="{{ $cellId }}_td"
                                            >
                                                <div id="{{ $cellId }}" class="relative h-full w-full" style="min-height: 60px; position: relative;">
                                                   @foreach($visibleBars as $barIndex => $bar)
    @php
        $wo = $bar['wo'];
        $stackIdx = $barIndex;
        $factoryId = Auth::user()?->factory_id ?? 'default-factory';
        $barTop = 8 + $stackIdx * ($barHeight + $barGap);
        $spanSlots = $bar['spanSlots'];
        $totalQty = $wo->qty ?? 0;
        $okQtys = $wo->ok_qtys ?? 0;
        $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
        
        if($bar['type'] === 'planned') {
            $displayText = $wo->unique_id;
        } else {
            $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
            $currentStatus = $actualEndLog ? strtolower($actualEndLog->status) : strtolower($wo->status);
            
            $statusColor = match($currentStatus) {
                'assigned' => '#6b7280',
                'start' => '#eab308',
                'hold' => '#ef4444',
                'completed' => '#22c55e',
                'closed' => '#a855f7',
                default => '#eab308'
            };
            
            $displayText = $percent > 0 ? $percent . '%' : $wo->unique_id;
            $displayPercent = $percent;
        }
        
        $barWidthPercentage = $spanSlots * 100;
        $extraWidth = ($spanSlots - 1) * 2;
    @endphp
    
    @if($bar['type'] === 'planned')
        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
           class="absolute bg-blue-500 dark:bg-blue-600 hover:bg-blue-600 dark:hover:bg-blue-700 rounded flex items-center shadow transition group"
           style="top: {{ $barTop }}px; 
                  left: 4px; 
                  height: {{ $barHeight }}px; 
                  width: calc({{ $barWidthPercentage }}% + {{ $extraWidth }}px - 8px); 
                  min-width: 50px; 
                  z-index: {{ 100 + $barIndex }}; 
                  text-decoration: none;
                  position: absolute;"
           title="Planned: {{ $wo->unique_id }}">
            <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                {{ $displayText }}
            </span>
        </a>
    @else
        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
           class="absolute bg-gray-200 dark:bg-gray-700 hover:opacity-80 rounded flex items-center shadow transition group overflow-hidden"
           style="top: {{ $barTop }}px; 
                  left: 4px; 
                  height: {{ $barHeight }}px; 
                  width: calc({{ $barWidthPercentage }}% + {{ $extraWidth }}px - 8px); 
                  min-width: 50px; 
                  z-index: {{ 100 + $barIndex }}; 
                  text-decoration: none;
                  position: absolute;"
           title="Actual: {{ $wo->unique_id }} ({{ ucfirst($currentStatus) }}) - {{ $percent > 0 ? $percent . '%' : 'Started' }} Complete">
            
            {{-- Progress fill with status color --}}
            <div class="absolute top-0 left-0 h-full transition-all duration-300"
                 style="width: {{ $displayPercent }}%; background-color: {{ $statusColor }}; z-index: 1;"></div>
            
            <span class="relative text-[10px] font-semibold px-2 truncate w-full z-10 mix-blend-difference text-white" 
                  style="line-height: {{ $barHeight }}px;">
                {{ $currentStatus === 'start' && $percent === 0 ? 'STARTED' : $displayText }}
            </span>
        </a>
    @endif
@endforeach

                                                    @if(count($hiddenBars) > 0)
                                                        <button 
                                                            onclick="
                                                                document.getElementById('{{ $cellId }}_more').style.display='block';
                                                                this.style.display='none';
                                                                document.getElementById('{{ $cellId }}_td').style.height = '{{ $expandedHeight }}px';
                                                            "
                                                            class="absolute right-2 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 text-gray-900 dark:text-gray-100"
                                                            style="border:1px solid #ccc;">
                                                            <svg class="w-3 h-3 mr-1 text-gray-900 dark:text-gray-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                            </svg>
                                                            +{{ count($hiddenBars) }} more
                                                        </button>
                                                        <div id="{{ $cellId }}_more" class="absolute inset-0" style="display:none;">
@foreach($hiddenBars as $barIndex => $bar)
    @php
        $wo = $bar['wo'];
        $realIndex = $maxVisibleBars + $barIndex;
        $stackIdx = $realIndex;
        $factoryId = Auth::user()?->factory_id ?? 'default-factory';
        $barTop = 8 + $stackIdx * ($barHeight + $barGap);
        $spanSlots = $bar['spanSlots'];
        $totalQty = $wo->qty ?? 0;
        $okQtys = $wo->ok_qtys ?? 0;
        $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
        
        if($bar['type'] === 'planned') {
            $displayText = $wo->unique_id;
        } else {
            $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
            $currentStatus = $actualEndLog ? strtolower($actualEndLog->status) : strtolower($wo->status);
            
            $statusColor = match($currentStatus) {
                'assigned' => '#6b7280',
                'start' => '#eab308',
                'hold' => '#ef4444',
                'completed' => '#22c55e',
                'closed' => '#a855f7',
                default => '#eab308'
            };
            
            $displayText = $percent > 0 ?   $percent . '%' : $wo->unique_id;
            $displayPercent = $percent;
        }
        
        $barWidthPercentage = $spanSlots * 100;
        $extraWidth = ($spanSlots - 1) * 2;
    @endphp
    
    @if($bar['type'] === 'planned')
        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
           class="absolute bg-blue-500 dark:bg-blue-600 hover:bg-blue-600 dark:hover:bg-blue-700 rounded flex items-center shadow transition group"
           style="top: {{ $barTop }}px; 
                  left: 4px; 
                  height: {{ $barHeight }}px; 
                  width: calc({{ $barWidthPercentage }}% + {{ $extraWidth }}px - 8px); 
                  min-width: 50px; 
                  z-index: {{ 100 + $realIndex }}; 
                  text-decoration: none;
                  position: absolute;"
           title="Planned: {{ $wo->unique_id }}">
            <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                {{ $displayText }}
            </span>
        </a>
    @else
        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
           class="absolute bg-gray-200 dark:bg-gray-700 hover:opacity-80 rounded flex items-center shadow transition group overflow-hidden"
           style="top: {{ $barTop }}px; 
                  left: 4px; 
                  height: {{ $barHeight }}px; 
                  width: calc({{ $barWidthPercentage }}% + {{ $extraWidth }}px - 8px); 
                  min-width: 50px; 
                  z-index: {{ 100 + $realIndex }}; 
                  text-decoration: none;
                  position: absolute;"
           title="Actual: {{ $wo->unique_id }} ({{ ucfirst($currentStatus) }}) - {{ $percent > 0 ? $percent . '%' : 'Started' }} Complete">
            
            {{-- Progress fill with status color --}}
            <div class="absolute top-0 left-0 h-full transition-all duration-300"
                 style="width: {{ $displayPercent }}%; background-color: {{ $statusColor }}; z-index: 1;"></div>
            
            <span class="relative text-[10px] font-semibold px-2 truncate w-full z-10 mix-blend-difference text-white" 
                  style="line-height: {{ $barHeight }}px;">
                {{ $currentStatus === 'start' && $percent === 0 ? 'STARTED' : $displayText }}
            </span>
        </a>
    @endif
@endforeach
                                                            <button
                                                                onclick="
                                                                    document.getElementById('{{ $cellId }}_more').style.display='none';
                                                                    document.querySelector('#{{ $cellId }} > button').style.display='block';
                                                                    document.getElementById('{{ $cellId }}_td').style.height = '{{ $collapsedHeight }}px';
                                                                "
                                                                class="absolute right-2 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 text-gray-900 dark:text-gray-100"
                                                                style="border:1px solid #ccc;">
                                                                <svg class="w-3 h-3 mr-1 text-gray-900 dark:text-gray-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                                                </svg>
                                                                Collapse
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Back Button --}}

            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const timeRangeSelector = document.getElementById('timeRangeSelector');
            const datePicker = document.getElementById('datePicker');
            const urlParams = new URLSearchParams(window.location.search);
            const persistedTimeRange = urlParams.get('timeRange');
            const persistedSelectedDate = urlParams.get('selectedDate');
            if (persistedTimeRange) {
                timeRangeSelector.value = persistedTimeRange;
            }
            if (persistedSelectedDate) {
                if (timeRangeSelector.value === 'week') {
                    const date = new Date(persistedSelectedDate);
                    const year = date.getFullYear();
                    const week = Math.ceil(((date - new Date(year, 0, 1)) / 86400000 + 1) / 7);
                    datePicker.value = `${year}-W${week.toString().padStart(2, '0')}`;
                } else {
                    datePicker.value = persistedSelectedDate;
                }
            }
            timeRangeSelector.addEventListener('change', function () {
                const selectedValue = this.value;
                if (selectedValue === 'week') {
                    datePicker.type = 'week';
                    datePicker.value = '';
                } else if (selectedValue === 'day') {
                    datePicker.type = 'date';
                    datePicker.value = '';
                } else if (selectedValue === 'month') {
                    datePicker.type = 'month';
                    datePicker.value = '';
                }
            });
            datePicker.addEventListener('change', function () {
                const timeRange = timeRangeSelector.value;
                const selectedDate = this.value;
                window.location.href = `?timeRange=${timeRange}&selectedDate=${selectedDate}`;
            });
        });
    </script>
</x-filament::page>
