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
                        value="{{ $timeRange === 'week' ? \Carbon\Carbon::parse($selectedDate)->setTimezone(config('app.timezone'))->format('Y-\WW') : $selectedDate }}" />

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
                    $carbonSelected = \Carbon\Carbon::parse($selectedDate)->setTimezone(config('app.timezone'));

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
                                
                                // Create spanning bars for this week
                                $weekStart = $week[0]->copy()->startOfDay();
                                $weekEnd = $week[6]->copy()->endOfDay();
                                
                                $weekSpanningBars = [];
                                $barStackIndex = 0;
                                
                                // Process each work order to create spanning bars
                                foreach ($workOrders as $wo) {
                                    $plannedStart = \Carbon\Carbon::parse($wo->start_time)->setTimezone(config('app.timezone'));
                                    $plannedEnd = \Carbon\Carbon::parse($wo->end_time)->setTimezone(config('app.timezone'));
                                    $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                    $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                    $actualStart = $actualStartLog ? \Carbon\Carbon::parse($actualStartLog->changed_at)->setTimezone(config('app.timezone')) : null;
                                    $actualEnd = $actualEndLog ? \Carbon\Carbon::parse($actualEndLog->changed_at)->setTimezone(config('app.timezone')) : null;

                                    // Check if planned work order overlaps with this week
                                    if ($plannedStart <= $weekEnd && $plannedEnd >= $weekStart) {
                                        // Calculate which days this work order spans
                                        $startDayIndex = null;
                                        $endDayIndex = null;
                                        
                                        for ($i = 0; $i < 7; $i++) {
                                            $dayStart = $week[$i]->copy()->startOfDay();
                                            $dayEnd = $week[$i]->copy()->endOfDay();
                                            
                                            if ($plannedStart <= $dayEnd && $plannedEnd >= $dayStart) {
                                                if ($startDayIndex === null) $startDayIndex = $i;
                                                $endDayIndex = $i;
                                            }
                                        }
                                        
                                        if ($startDayIndex !== null && $endDayIndex !== null) {
                                            $weekSpanningBars[] = [
                                                'type' => 'planned',
                                                'wo' => $wo,
                                                'startDayIndex' => $startDayIndex,
                                                'endDayIndex' => $endDayIndex,
                                                'spanDays' => $endDayIndex - $startDayIndex + 1,
                                                'stackIdx' => $barStackIndex++,
                                            ];
                                        }
                                    }
                                    
                                    // Check if actual work order overlaps with this week
                                    if ($actualStart && $actualEnd && $actualStart <= $weekEnd && $actualEnd >= $weekStart) {
                                        // Calculate which days this work order spans
                                        $startDayIndex = null;
                                        $endDayIndex = null;
                                        
                                        for ($i = 0; $i < 7; $i++) {
                                            $dayStart = $week[$i]->copy()->startOfDay();
                                            $dayEnd = $week[$i]->copy()->endOfDay();
                                            
                                            if ($actualStart <= $dayEnd && $actualEnd >= $dayStart) {
                                                if ($startDayIndex === null) $startDayIndex = $i;
                                                $endDayIndex = $i;
                                            }
                                        }
                                        
                                        if ($startDayIndex !== null && $endDayIndex !== null) {
                                            $weekSpanningBars[] = [
                                                'type' => 'actual',
                                                'wo' => $wo,
                                                'startDayIndex' => $startDayIndex,
                                                'endDayIndex' => $endDayIndex,
                                                'spanDays' => $endDayIndex - $startDayIndex + 1,
                                                'stackIdx' => $barStackIndex++,
                                            ];
                                        }
                                    }
                                }
                                
                                $maxBarsInRow = count($weekSpanningBars);
                                $maxVisibleBars = 2;
                                $collapsedHeight = 20 + ($maxVisibleBars * 24) + ($maxBarsInRow > $maxVisibleBars ? 36 : 0);
                                $expandedHeight = 20 + ($maxBarsInRow * 24) + 36;
                            @endphp
                            <div class="grid grid-cols-8 min-h-[80px] border-b border-gray-200 dark:border-gray-700 relative" 
                                 id="{{ $rowId }}" 
                                 style="height: {{ $collapsedHeight }}px;"
                                 data-expanded="false">
                                {{-- Week number cell --}}
                                <div class="flex items-center justify-center bg-indigo-200 dark:bg-indigo-800 text-indigo-900 dark:text-white text-xs font-bold border-r border-gray-200 dark:border-gray-700">
                                    {{ $weekNumber }}
                                </div>
                                
                                {{-- Day cells with day numbers --}}
                                @foreach($week as $dayIdx => $day)
                                    <div class="relative border-r border-gray-200 dark:border-gray-700 last:border-r-0 bg-white dark:bg-gray-900">
                                        <div class="absolute top-1 left-1 text-xs font-semibold {{ $day->isToday() ? 'text-blue-600 dark:text-blue-300' : 'text-gray-700 dark:text-gray-200' }}">
                                            {{ $day->format('j') }}
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Spanning bars positioned absolutely over the entire row --}}
                                <div class="absolute inset-0 pointer-events-none" style="left: calc(100% / 8); right: 0;">
                                    @php $visibleBars = array_slice($weekSpanningBars, 0, $maxVisibleBars); @endphp
                                    @foreach($weekSpanningBars as $barIdx => $bar)
                                        @php
                                            $wo = $bar['wo'];
                                            $stackIdx = $bar['stackIdx'];
                                            $barTop = 20 + $stackIdx * 24;
                                            $isHidden = $barIdx >= $maxVisibleBars;
                                            
                                            // Calculate bar positioning and width
                                            $startPercent = ($bar['startDayIndex'] / 7) * 100;
                                            $widthPercent = ($bar['spanDays'] / 7) * 100;
                                            
                                            // Calculate percentage for actual bars
                                            $totalQty = $wo->qty ?? 0;
                                            $okQtys = $wo->ok_qtys ?? 0;
                                            $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                            
                                            $factoryId = Auth::user()?->factory_id ?? 'default-factory';

                                            if($bar['type'] === 'planned') {
                                                $displayText = $wo->unique_id;
                                            } else {
                                                // Get status from logs for actual bars
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
                                                if ($currentStatus === 'start' && $percent === 0) {
                                                    $displayPercent = 20; // Show 20% fill for visibility
                                                }
                                            }
                                        @endphp

                                        @if($bar['type'] === 'planned')
                                            {{-- Planned spanning bar --}}
                                            <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                               class="absolute bg-blue-500 dark:bg-blue-600 hover:bg-blue-600 dark:hover:bg-blue-700 rounded flex items-center shadow transition group pointer-events-auto {{ $isHidden ? 'hidden' : '' }}"
                                               style="top: {{ $barTop }}px;
                                                      left: {{ $startPercent }}%;
                                                      width: {{ $widthPercent }}%;
                                                      height: 20px;
                                                      z-index: {{ 100 + $barIdx }};
                                                      text-decoration: none;"
                                               data-bar="week_bar_{{ $barIdx }}"
                                               title="Planned: {{ $wo->unique_id }} ({{ $bar['spanDays'] }} day{{ $bar['spanDays'] > 1 ? 's' : '' }})">
                                                <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: 20px;">
                                                    {{ $displayText }}
                                                </span>
                                            </a>
                                        @else
                                            {{-- Actual spanning bar --}}
                                            <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                               class="absolute bg-gray-200 dark:bg-gray-700 hover:opacity-80 rounded flex items-center shadow transition group overflow-hidden pointer-events-auto {{ $isHidden ? 'hidden' : '' }}"
                                               style="top: {{ $barTop }}px;
                                                      left: {{ $startPercent }}%;
                                                      width: {{ $widthPercent }}%;
                                                      height: 20px;
                                                      z-index: {{ 100 + $barIdx }};
                                                      text-decoration: none;"
                                               data-bar="week_bar_{{ $barIdx }}"
                                               title="Actual: {{ $wo->unique_id }} ({{ ucfirst($currentStatus) }}) - {{ $percent > 0 ? $percent . '%' : 'Started' }} Complete ({{ $bar['spanDays'] }} day{{ $bar['spanDays'] > 1 ? 's' : '' }})">

                                                {{-- Progress fill with status color --}}
                                                <div class="absolute top-0 left-0 h-full transition-all duration-300"
                                                     style="width: {{ $displayPercent }}%; background-color: {{ $statusColor }}; z-index: 1;"></div>

                                                <span class="relative text-[10px] font-semibold px-2 truncate w-full z-10 mix-blend-difference text-white"
                                                      style="line-height: 20px;">
                                                    {{ $currentStatus === 'start' && $percent === 0 ? 'STARTED' : $displayText }}
                                                </span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>

                                {{-- Expand/collapse controls --}}
                                @if(count($weekSpanningBars) > $maxVisibleBars)
                                    <button
                                        onclick="
                                            let row = document.getElementById('{{ $rowId }}');
                                            row.style.height = '{{ $expandedHeight }}px';
                                            row.setAttribute('data-expanded', 'true');
                                            row.querySelectorAll('[data-bar]').forEach(bar => bar.classList.remove('hidden'));
                                            this.style.display='none';
                                            document.getElementById('{{ $rowId }}_collapse').style.display='block';
                                        "
                                        class="absolute right-2 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 text-gray-900 dark:text-gray-100 pointer-events-auto"
                                        style="border:1px solid #ccc;"
                                        id="{{ $rowId }}_expand"
                                    >
                                        <svg class="w-3 h-3 mr-1 text-gray-900 dark:text-gray-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                        +{{ count($weekSpanningBars) - $maxVisibleBars }} more
                                    </button>

                                    <button
                                        onclick="
                                            let row = document.getElementById('{{ $rowId }}');
                                            row.style.height = '{{ $collapsedHeight }}px';
                                            row.setAttribute('data-expanded', 'false');
                                            row.querySelectorAll('[data-bar]').forEach((bar, index) => {
                                                if (index >= {{ $maxVisibleBars }}) {
                                                    bar.classList.add('hidden');
                                                }
                                            });
                                            this.style.display='none';
                                            document.getElementById('{{ $rowId }}_expand').style.display='block';
                                        "
                                        class="absolute right-2 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 text-gray-900 dark:text-gray-100 pointer-events-auto"
                                        style="display:none; border:1px solid #ccc;"
                                        id="{{ $rowId }}_collapse"
                                    >
                                        <svg class="w-3 h-3 mr-1 text-gray-900 dark:text-gray-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                        </svg>
                                        Collapse
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Day View: 2-Hour Interval Table Only --}}
                @if($timeRange === 'day')
                @php
                    $carbonSelected = \Carbon\Carbon::parse($selectedDate)->setTimezone(config('app.timezone'));
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

                    // Use the work orders that are already filtered by the backend
                    // The backend filtering is working correctly, so don't re-filter here
                    $filteredWOs = $workOrders;

                    // Debug our specific work order
                    $specificWO = $filteredWOs->where('unique_id', 'W0001_090325_O0039_082025_2040_8466936861_B')->first();
                    if ($specificWO) {
                        \Log::info('Daily View WO Found', [
                            'wo_id' => $specificWO->unique_id,
                            'selected_day' => $selectedDay->toDateString(),
                            'planned_start' => $specificWO->start_time,
                            'planned_end' => $specificWO->end_time,
                            'found_in_filtered' => true,
                        ]);
                    } else {
                        \Log::info('Daily View WO NOT Found', [
                            'selected_day' => $selectedDay->toDateString(),
                            'total_work_orders' => $workOrders->count(),
                            'work_order_ids' => $workOrders->pluck('unique_id')->toArray(),
                        ]);
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
                                            $plannedStart = \Carbon\Carbon::parse($wo->start_time)->setTimezone(config('app.timezone'));
                                            $plannedEnd = \Carbon\Carbon::parse($wo->end_time)->setTimezone(config('app.timezone'));

                                            // Get actual start and end from logs
                                            $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                            $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                            $actualStart = $actualStartLog ? \Carbon\Carbon::parse($actualStartLog->changed_at)->setTimezone(config('app.timezone')) : null;
                                            $actualEnd = $actualEndLog ? \Carbon\Carbon::parse($actualEndLog->changed_at)->setTimezone(config('app.timezone')) : null;

                                            // Calculate planned bar times for the selected day
                                            $plannedDisplayStart = null;
                                            $plannedDisplayEnd = null;

                                            // Check if planned work order overlaps with selected day
                                            if ($plannedStart <= $selectedDayEnd && $plannedEnd >= $selectedDay) {
                                                // Clamp to selected day boundaries
                                                $plannedDisplayStart = $plannedStart->lt($selectedDay) ? $selectedDay->copy() : $plannedStart->copy();
                                                $plannedDisplayEnd = $plannedEnd->gt($selectedDayEnd) ? $selectedDayEnd->copy() : $plannedEnd->copy();
                                            }

                                            // Calculate actual bar times for the selected day
                                            $actualDisplayStart = null;
                                            $actualDisplayEnd = null;

                                            if ($actualStart && $actualEnd) {
                                                // Check if actual work order overlaps with selected day
                                                if ($actualStart <= $selectedDayEnd && $actualEnd >= $selectedDay) {
                                                    // Clamp to selected day boundaries
                                                    $actualDisplayStart = $actualStart->lt($selectedDay) ? $selectedDay->copy() : $actualStart->copy();
                                                    $actualDisplayEnd = $actualEnd->gt($selectedDayEnd) ? $selectedDayEnd->copy() : $actualEnd->copy();
                                                }
                                            }

                                            // Add planned bar if it overlaps with the selected day
                                            if ($plannedDisplayStart && $plannedDisplayEnd) {
                                                $allBars[] = [
                                                    'type' => 'planned',
                                                    'wo' => $wo,
                                                    'displayStart' => $plannedDisplayStart,
                                                    'displayEnd' => $plannedDisplayEnd,
                                                    'stackIdx' => $barCounter,
                                                    'id' => 'planned_' . $wo->id,
                                                ];
                                                $barCounter++;
                                            }

                                            // Add actual bar if it overlaps with the selected day
                                            if ($actualDisplayStart && $actualDisplayEnd) {
                                                $allBars[] = [
                                                    'type' => 'actual',
                                                    'wo' => $wo,
                                                    'displayStart' => $actualDisplayStart,
                                                    'displayEnd' => $actualDisplayEnd,
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
                                        {{-- Create a single cell spanning all time slots for the day view --}}
                                        <td colspan="{{ count($timeSlots) }}" class="relative border border-gray-200 dark:border-gray-700 align-top p-0 overflow-visible"
                                            style="height: {{ $rowCollapsedHeight }}px; position: relative;"
                                            id="day_cell_{{ $selectedDay->format('Ymd') }}"
                                        >
                                            <div class="relative h-full w-full" style="min-height: 60px; position: relative;">
                                                {{-- Time scale background --}}
                                                <div class="absolute inset-0 flex">
                                                    @foreach($timeSlots as $slotIndex => $slot)
                                                        <div class="flex-1 border-r border-gray-100 dark:border-gray-600 {{ $slotIndex === count($timeSlots) - 1 ? 'border-r-0' : '' }}">
                                                        </div>
                                                    @endforeach
                                                </div>

                                                {{-- Render all bars with precise time-based positioning --}}
                                                @foreach($allBars as $barIndex => $bar)
                                                    @php
                                                        $wo = $bar['wo'];
                                                        $stackIdx = $bar['stackIdx'];
                                                        $factoryId = Auth::user()?->factory_id ?? 'default-factory';
                                                        $barTop = 8 + $stackIdx * ($barHeight + $barGap);
                                                        $isVisible = $barIndex < $maxVisibleBars;

                                                        // Use the display times we calculated in the bar creation
                                                        $displayStart = $bar['displayStart'];
                                                        $displayEnd = $bar['displayEnd'];

                                                        // Calculate exact positioning within the day
                                                        $minutesFromDayStart = $selectedDay->diffInMinutes($displayStart, false); // false = don't use absolute value
                                                        $leftPercent = ($minutesFromDayStart / (24 * 60)) * 100;

                                                        $durationMinutes = $displayStart->diffInMinutes($displayEnd, false); // false = don't use absolute value  
                                                        $widthPercent = ($durationMinutes / (24 * 60)) * 100;

                                                        // Ensure minimum width for visibility and positive value
                                                        if ($widthPercent < 2) {
                                                            $widthPercent = 2;
                                                        }

                                                        $totalQty = $wo->qty ?? 0;
                                                        $okQtys = $wo->ok_qtys ?? 0;
                                                        $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;

                                                        if($bar['type'] === 'planned') {
                                                            $displayText = $wo->unique_id;
                                                        } else {
                                                            // For actual bars, get the status from logs
                                                            $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
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
                                                            $displayPercent = $percent; // For progress bar width
                                                        }
                                                    @endphp

                                                    @if($bar['type'] === 'planned')
                                                        {{-- Planned bar --}}
                                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                           class="absolute bg-blue-500 dark:bg-blue-600 hover:bg-blue-600 dark:hover:bg-blue-700 rounded flex items-center shadow transition group {{ !$isVisible ? 'hidden' : '' }}"
                                                           style="top: {{ $barTop }}px;
                                                                  left: {{ $leftPercent }}%;
                                                                  height: {{ $barHeight }}px;
                                                                  width: {{ $widthPercent }}%;
                                                                  min-width: 30px;
                                                                  z-index: {{ 100 + $barIndex }};
                                                                  text-decoration: none;"
                                                           data-bar="day_bar_{{ $barIndex }}"
                                                           title="Planned: {{ $wo->unique_id }} ({{ $displayStart->format('H:i') }} - {{ $displayEnd->format('H:i') }})">
                                                            <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                {{ $displayText }}
                                                            </span>
                                                        </a>
                                                    @else
                                                        {{-- Actual bar --}}
                                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                           class="absolute bg-gray-200 dark:bg-gray-700 hover:opacity-80 rounded flex items-center shadow transition group overflow-hidden {{ !$isVisible ? 'hidden' : '' }}"
                                                           style="top: {{ $barTop }}px;
                                                                  left: {{ $leftPercent }}%;
                                                                  height: {{ $barHeight }}px;
                                                                  width: {{ $widthPercent }}%;
                                                                  min-width: 30px;
                                                                  z-index: {{ 100 + $barIndex }};
                                                                  text-decoration: none;"
                                                           data-bar="day_bar_{{ $barIndex }}"
                                                           title="Actual: {{ $wo->unique_id }} ({{ ucfirst($currentStatus) }}) - {{ $percent > 0 ? $percent . '%' : 'Started' }} Complete ({{ $displayStart->format('H:i') }} - {{ $displayEnd->format('H:i') }})">

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

                                                {{-- Expand/collapse controls --}}
                                                @if(count($allBars) > $maxVisibleBars)
                                                    <button
                                                        onclick="
                                                            let cell = document.getElementById('day_cell_{{ $selectedDay->format('Ymd') }}');
                                                            cell.style.height = '{{ $rowExpandedHeight }}px';
                                                            cell.querySelectorAll('[data-bar]').forEach(bar => bar.classList.remove('hidden'));
                                                            this.style.display='none';
                                                            document.getElementById('day_collapse_{{ $selectedDay->format('Ymd') }}').style.display='block';
                                                        "
                                                        class="absolute right-2 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 text-gray-900 dark:text-gray-100"
                                                        style="border:1px solid #ccc;"
                                                        id="day_expand_{{ $selectedDay->format('Ymd') }}"
                                                    >
                                                        <svg class="w-3 h-3 mr-1 text-gray-900 dark:text-gray-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                        +{{ count($allBars) - $maxVisibleBars }} more
                                                    </button>

                                                    <button
                                                        onclick="
                                                            let cell = document.getElementById('day_cell_{{ $selectedDay->format('Ymd') }}');
                                                            cell.style.height = '{{ $rowCollapsedHeight }}px';
                                                            cell.querySelectorAll('[data-bar]').forEach((bar, index) => {
                                                                if (index >= {{ $maxVisibleBars }}) {
                                                                    bar.classList.add('hidden');
                                                                }
                                                            });
                                                            this.style.display='none';
                                                            document.getElementById('day_expand_{{ $selectedDay->format('Ymd') }}').style.display='block';
                                                        "
                                                        class="absolute right-2 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 text-gray-900 dark:text-gray-100"
                                                        style="display:none; border:1px solid #ccc;"
                                                        id="day_collapse_{{ $selectedDay->format('Ymd') }}"
                                                    >
                                                        <svg class="w-3 h-3 mr-1 text-gray-900 dark:text-gray-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                                        </svg>
                                                        Collapse
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
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
