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
                                                    $barColor = $bar['type'] === 'planned' ? '#3b82f6' : '#10B981';
                                                    $barBgClass = $bar['type'] === 'planned' ? 'bg-blue-500 dark:bg-blue-700' : 'bg-green-500 dark:bg-green-700';
                                                @endphp
                                                <a href="{{ url('admin/' . (auth()->user()?->factory_id ?? 3) . '/work-orders/' . $wo->id) }}"
                                                    class="absolute left-1 right-1 h-5 rounded flex items-center shadow hover:bg-blue-700 dark:hover:bg-blue-800 transition group {{ $barBgClass }}"
                                                    style="top: {{ $barTop }}px; z-index: 10; text-decoration: none; {{ $isHidden ? 'display:none;' : '' }}"
                                                    data-bar="{{ $cellId }}_bar_{{ $barIdx }}"
                                                    title="{{ $wo->unique_id }}">
                                                    <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: 20px;">
                                                        {{ $wo->unique_id }}
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

                {{-- Day View: Hourly Table Only --}}
                @if($timeRange === 'day')
                @php
                    $carbonSelected = \Carbon\Carbon::parse($selectedDate);
                    $hours = range(0, 23);
                    $selectedDay = $carbonSelected->copy()->startOfDay();
                    $selectedDayEnd = $carbonSelected->copy()->endOfDay();
                    $filteredWOs = $workOrders->filter(function($wo) use ($selectedDay, $selectedDayEnd) {
                        $plannedStart = \Carbon\Carbon::parse($wo->start_time);
                        $plannedEnd = \Carbon\Carbon::parse($wo->end_time);
                        return $plannedStart->startOfDay() <= $selectedDayEnd && $plannedEnd->endOfDay() >= $selectedDay;
                    });
                @endphp

                <div class="overflow-x-auto mt-8">
                    <div class="w-full bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-4">
                        <div class="mb-4">
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                                Work Order Day View
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-300">Outlook-style day view</p>
                        </div>
                        <div class="border rounded-lg overflow-hidden border-gray-200 dark:border-gray-700">
                            <table class="w-full table-fixed border-collapse">
                                <thead>
                                    <tr>
                                        <th class="w-40 text-xs text-gray-500 dark:text-gray-300 bg-blue-50 dark:bg-blue-900 border border-gray-200 dark:border-gray-700 p-2">Date</th>
                                        @foreach($hours as $hour)
                                            <th class="text-xs text-gray-700 dark:text-gray-200 bg-yellow-50 dark:bg-yellow-900 border border-gray-200 dark:border-gray-700 p-2 text-center">
                                                {{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00
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
                                        $maxVisibleBars = 2;
                                    @endphp
                                    @foreach($filteredWOs as $wo)
                                        @php
                                            $plannedStart = \Carbon\Carbon::parse($wo->start_time);
                                            $plannedEnd = \Carbon\Carbon::parse($wo->end_time);
                                            $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                            $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                            $actualStart = $actualStartLog ? \Carbon\Carbon::parse($actualStartLog->changed_at) : null;
                                            $actualEnd = $actualEndLog ? \Carbon\Carbon::parse($actualEndLog->changed_at) : null;
                                            $actualStartHour = ($actualStart && $actualStart->isSameDay($selectedDay)) ? $actualStart->hour : null;
                                            $actualEndHour = ($actualEnd && $actualEnd->isSameDay($selectedDay)) ? $actualEnd->hour : null;
                                        @endphp
                                        <tr>
                                            <td class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-2 align-top text-gray-900 dark:text-gray-100">
                                                {{ $carbonSelected->format('M d, Y') }}
                                            </td>
                                            @for($h = 0; $h < 24; $h++)
                                                @php
                                                    $cellBars = [];
                                                    $startHour = ($plannedStart->isSameDay($selectedDay)) ? $plannedStart->hour : 0;
                                                    $endHour = ($plannedEnd->isSameDay($selectedDay)) ? $plannedEnd->hour : 23;
                                                    $spanHours = max(1, $endHour - $startHour + 1);
                                                    if($h === $startHour) {
                                                        $cellBars[] = [
                                                            'type' => 'planned',
                                                            'wo' => $wo,
                                                            'spanHours' => $spanHours,
                                                            'stackIdx' => 0,
                                                        ];
                                                    }
                                                    if($actualStartHour !== null && $actualEndHour !== null && $h === $actualStartHour) {
                                                        $actualSpanHours = max(1, $actualEndHour - $actualStartHour + 1);
                                                        $cellBars[] = [
                                                            'type' => 'actual',
                                                            'wo' => $wo,
                                                            'spanHours' => $actualSpanHours,
                                                            'stackIdx' => 1,
                                                        ];
                                                    }
                                                    $visibleBars = array_slice($cellBars, 0, $maxVisibleBars);
                                                    $hiddenBars = array_slice($cellBars, $maxVisibleBars);
                                                    $stackCount = count($cellBars);
                                                    $collapsedHeight = $cellPadding + (count($visibleBars) * $barHeight) + ((count($visibleBars) > 0 ? (count($visibleBars) - 1) : 0) * $barGap);
                                                    if(count($hiddenBars) > 0) {
                                                        $collapsedHeight += $expandCollapseButtonHeight;
                                                    }
                                                    $expandedHeight = max(
                                                        $cellPadding + $stackCount * 2 * ($barHeight + $barGap) + $expandCollapseButtonHeight,
                                                        36 + $expandCollapseButtonHeight
                                                    );
                                                    $cellId = 'cell_' . $wo->id . '_' . $selectedDay->format('Ymd') . '_' . $h;
                                                @endphp
                                                <td class="relative border border-gray-200 dark:border-gray-700 align-top p-0"
                                                    style="height: {{ $collapsedHeight }}px;"
                                                    id="{{ $cellId }}_td"
                                                >
                                                    <div id="{{ $cellId }}" class="relative h-full" style="min-height: 36px;">
                                                        @foreach($visibleBars as $bar)
                                                            @php
                                                                $wo = $bar['wo'];
                                                                $stackIdx = $bar['stackIdx'];
                                                                $factoryId = auth()->user()?->factory_id ?? 'default-factory';
                                                                $statusColors = config('work_order_status');
                                                                $barTop = 8 + $stackIdx * 2 * ($barHeight + $barGap);
                                                                $spanHours = isset($bar['spanHours']) ? max(1, $bar['spanHours']) : 1;
                                                                $totalQty = $wo->qty ?? 0;
                                                                $okQtys = $wo->ok_qtys ?? 0;
                                                                $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                                                $logs = $wo->workOrderLogs ?? [];
                                                                $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                                                $actualStatusKey = $actualEndLog ? strtolower($actualEndLog->status) : strtolower($wo->status);
                                                                $actualColor = $statusColors[$actualStatusKey] ?? '#10B981';
                                                                $barBgClass = $bar['type'] === 'planned' ? 'bg-blue-500 dark:bg-blue-700' : 'bg-green-500 dark:bg-green-700';
                                                            @endphp
                                                            @if($bar['type'] === 'planned')
<a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
   class="absolute {{ $barBgClass }} rounded flex items-center shadow hover:bg-blue-700 dark:hover:bg-blue-800 transition group"
   style="top: {{ $barTop }}px; left: 0; height: {{ $barHeight }}px; width: 100%; min-width: 8px; z-index: 10; text-decoration: none;"
   title="{{ $wo->unique_id }}">
                                                                <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                    {{ $wo->unique_id }}
                                                                </span>
                                                            </a>
                                                            @elseif($bar['type'] === 'actual')
<a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
   class="absolute {{ $barBgClass }} rounded flex items-center shadow hover:bg-blue-700 dark:hover:bg-blue-800 transition group"
   style="top: {{ $barTop }}px; left: 0; height: {{ $barHeight }}px; width: 100%; min-width: 8px; z-index: 10; text-decoration: none;"
   title="{{ $wo->unique_id }}">
                                                                <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                    {{ $percent }}%
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
                                                                class="absolute right-1 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 text-gray-900 dark:text-gray-100"
                                                                style="border:1px solid #ccc;">
                                                                <svg class="w-3 h-3 mr-1 text-gray-900 dark:text-gray-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                                </svg>
                                                                +{{ count($hiddenBars) }} more
                                                            </button>
                                                            <div id="{{ $cellId }}_more" class="absolute inset-0" style="display:none;">
                                                                @foreach($hiddenBars as $bar)
                                                                    @php
                                                                        $wo = $bar['wo'];
                                                                        $stackIdx = $bar['stackIdx'];
                                                                        $factoryId = auth()->user()?->factory_id ?? 'default-factory';
                                                                        $statusColors = config('work_order_status');
                                                                        $barTop = 8 + $stackIdx * 2 * ($barHeight + $barGap);
                                                                        $spanHours = isset($bar['spanHours']) ? max(1, $bar['spanHours']) : 1;
                                                                        $totalQty = $wo->qty ?? 0;
                                                                        $okQtys = $wo->ok_qtys ?? 0;
                                                                        $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                                                        $logs = $wo->workOrderLogs ?? [];
                                                                        $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                                                        $actualStatusKey = $actualEndLog ? strtolower($actualEndLog->status) : strtolower($wo->status);
                                                                        $actualColor = $statusColors[$actualStatusKey] ?? '#10B981';
                                                                        $isPlanned = $bar['type'] === 'planned';
                                                                        $isActual = $bar['type'] === 'actual';
                                                                        $barBgClass = $isPlanned ? 'bg-blue-500 dark:bg-blue-700' : 'bg-green-500 dark:bg-green-700';
                                                                    @endphp
                                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                                       class="absolute {{ $barBgClass }} rounded flex items-center shadow hover:bg-blue-700 dark:hover:bg-blue-800 transition group"
                                                                       style="top: {{ $barTop }}px; left: 0; height: {{ $barHeight }}px; width: 100%; min-width: 8px; z-index: 10; text-decoration: none;"
                                                                       title="{{ $wo->unique_id }}">
                                                                        <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                            @if($isPlanned)
                                                                                {{ $wo->unique_id }}
                                                                            @elseif($isActual && $percent > 0)
                                                                                {{ $percent }}%
                                                                            @endif
                                                                        </span>
                                                                    </a>
                                                                @endforeach
                                                                <button
                                                                    onclick="
                                                                        document.getElementById('{{ $cellId }}_more').style.display='none';
                                                                        document.querySelector('#{{ $cellId }} > button').style.display='block';
                                                                        document.getElementById('{{ $cellId }}_td').style.height = '{{ $collapsedHeight }}px';
                                                                    "
                                                                    class="absolute right-1 bottom-2 bg-gray-200 dark:bg-gray-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 text-gray-900 dark:text-gray-100"
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
                                            @endfor
                                        </tr>
                                    @endforeach
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
