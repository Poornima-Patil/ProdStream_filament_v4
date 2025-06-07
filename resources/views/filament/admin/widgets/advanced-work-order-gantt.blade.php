@php use Illuminate\Support\Carbon; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advanced Work Order Gantt Chart</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.1/dist/tailwind.min.css" rel="stylesheet">
    @endif
</head>
<body>
<div class="min-h-screen bg-gray-100 flex flex-col">
    <div class="flex-1 flex flex-col items-center justify-start py-8">
        <div class="w-full max-w-7xl bg-white rounded-xl shadow-xl px-10 py-8">
            {{-- Main Heading --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight mb-4 md:mb-0">
                    Advanced Work Order Gantt Chart
                </h1>
            </div>
            <div class="flex flex-wrap items-center space-x-4 mb-8">
                <select id="timeRangeSelector" class="w-32 border border-gray-300 rounded-lg px-4 py-2 text-base focus:ring focus:ring-blue-200">
                    <option value="week" {{ $timeRange === 'week' ? 'selected' : '' }}>Week</option>
                    <option value="day" {{ $timeRange === 'day' ? 'selected' : '' }}>Day</option>
                    <option value="month" {{ $timeRange === 'month' ? 'selected' : '' }}>Month</option>
                </select>
                <input 
                    type="{{ $timeRange === 'month' ? 'month' : ($timeRange === 'day' ? 'date' : 'week') }}" 
                    id="datePicker" 
                    class="w-40 border border-gray-300 rounded-lg px-4 py-2 text-base focus:ring focus:ring-blue-200" 
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
                <div class="flex items-center space-x-4 bg-gray-100 border border-gray-300 rounded px-3 py-1 ml-2">
                    @foreach($legend as $status => $color)
                        <div class="flex items-center space-x-2">
                            <span class="inline-block w-4 h-4 rounded" style="background: {{ $color }}"></span>
                            <span class="text-xs text-gray-700">{{ $status }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Time Range & Selected Date --}}
            <div class="mb-8 flex flex-col md:flex-row md:items-center md:space-x-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg px-6 py-3 mb-3 md:mb-0 flex-1">
                    <span class="block text-lg font-semibold text-blue-700">Time Range</span>
                    <span class="block text-xl font-bold text-blue-900">{{ ucfirst($timeRange) }}</span>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg px-6 py-3 flex-1">
                    <span class="block text-lg font-semibold text-green-700">Selected Date</span>
                    <span class="block text-xl font-bold text-green-900">{{ $selectedDate }}</span>
                </div>
            </div>

            {{-- Calendar View (Month/Week) --}}
            @if($timeRange === 'month' || $timeRange === 'week')
            @php
                $carbonSelected = Carbon::parse($selectedDate);

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
                <div class="w-full bg-white rounded-lg shadow border p-4">
                    <div class="mb-4">
                        <h3 class="text-xl font-semibold text-gray-800">Work Order Calendar View</h3>
                        <p class="text-sm text-gray-500">Outlook-like {{ ucfirst($timeRange) }} view</p>
                    </div>
                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full table-fixed border-collapse">
                            <thead>
                                <tr>
                                    <th class="w-14 text-xs text-gray-500 bg-blue-50 border p-2">Week</th>
                                    @foreach($dayNames as $day)
                                        <th class="text-xs text-gray-700 bg-yellow-50 border p-2">{{ $day }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($weeks as $week)
                                <tr class="relative">
                                    <td class="text-xs text-gray-600 align-top border bg-blue-50 font-semibold">
                                        {{ $week[0]->format('W') }}
                                    </td>
                                    @foreach($week as $dayIndex => $day)
                                        @php
                                            $weekStart = $week[0];
                                            $weekEnd = end($week);
                                            $woStackOrder = [];
                                            $woIndex = 0;
                                            foreach ($workOrders as $wo) {
                                                $plannedStart = Carbon::parse($wo->start_time)->startOfDay();
                                                $plannedEnd = Carbon::parse($wo->end_time)->endOfDay();
                                                $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                                $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                                $actualStart = $actualStartLog ? Carbon::parse($actualStartLog->changed_at)->startOfDay() : null;
                                                $actualEnd = $actualEndLog ? Carbon::parse($actualEndLog->changed_at)->endOfDay() : null;

                                                if (
                                                    ($plannedEnd >= $weekStart && $plannedStart <= $weekEnd) ||
                                                    ($actualStart && $actualEnd && $actualEnd >= $weekStart && $actualStart <= $weekEnd) ||
                                                    ($actualStart && !$actualEnd && $actualStart >= $weekStart && $actualStart <= $weekEnd)
                                                ) {
                                                    $woStackOrder[$wo->id] = $woIndex++;
                                                }
                                            }

                                            $cellBars = [];
                                            foreach ($workOrders as $wo) {
                                                $stackIdx = $woStackOrder[$wo->id] ?? null;
                                                if ($stackIdx === null) continue;

                                                // --- PLANNED BAR ---
                                                $plannedStart = Carbon::parse($wo->start_time)->startOfDay();
                                                $plannedEnd = Carbon::parse($wo->end_time)->endOfDay();

                                                $barStart = $plannedStart->greaterThan($weekStart) ? $plannedStart : $weekStart;
                                                $barEnd = $plannedEnd->lessThan($weekEnd) ? $plannedEnd : $weekEnd;

                                                if ($barEnd >= $weekStart && $barStart <= $weekEnd) {
                                                    $plannedStartIdx = collect($week)->search(fn($d) => $d->equalTo($barStart));
                                                    if ($plannedStartIdx === false) $plannedStartIdx = 0;

                                                    $plannedEndIdx = collect($week)
                                                        ->filter(fn($d) => $d->lte($barEnd))
                                                        ->keys()
                                                        ->last();
                                                    if ($plannedEndIdx === null) $plannedEndIdx = $plannedStartIdx;

                                                    $plannedSpanDays = max(1, $plannedEndIdx - $plannedStartIdx + 1);

                                                    if ($dayIndex === $plannedStartIdx) {
                                                        $cellBars[] = [
                                                            'type' => 'planned',
                                                            'wo' => $wo,
                                                            'spanDays' => $plannedSpanDays,
                                                            'stackIdx' => $stackIdx,
                                                        ];
                                                    }
                                                }

                                                // --- ACTUAL BAR ---
                                                $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                                $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                                $actualStart = $actualStartLog ? Carbon::parse($actualStartLog->changed_at)->startOfDay() : null;
                                                $actualEnd = $actualEndLog ? Carbon::parse($actualEndLog->changed_at)->endOfDay() : null;

                                                if ($actualStart && $actualEnd) {
                                                    $actualBarStart = $actualStart->greaterThan($weekStart) ? $actualStart : $weekStart;
                                                    $actualBarEnd = $actualEnd->lessThan($weekEnd) ? $actualEnd : $weekEnd;

                                                    if ($actualBarEnd >= $weekStart && $actualBarStart <= $weekEnd) {
                                                        $actualStartIdx = collect($week)->search(fn($d) => $d->equalTo($actualBarStart));
                                                        if ($actualStartIdx === false) $actualStartIdx = 0;

                                                        $actualEndIdx = collect($week)
                                                            ->filter(fn($d) => $d->lte($actualBarEnd))
                                                            ->keys()
                                                            ->last();
                                                        if ($actualEndIdx === null) $actualEndIdx = $actualStartIdx;

                                                        $actualSpanDays = max(1, $actualEndIdx - $actualStartIdx + 1);

                                                        if ($dayIndex === $actualStartIdx) {
                                                            $cellBars[] = [
                                                                'type' => 'actual',
                                                                'wo' => $wo,
                                                                'spanDays' => $actualSpanDays,
                                                                'stackIdx' => $stackIdx,
                                                            ];
                                                        }
                                                    }
                                                }
                                                // --- FLAG (only if there is a start log and NO end log) ---
                                                if ($actualStart && !$actualEnd) {
                                                    $flagDayIdx = collect($week)->search(fn($d) => $d->toDateString() === $actualStart->toDateString());
                                                    if ($flagDayIdx !== false && $dayIndex === $flagDayIdx) {
                                                        $cellBars[] = [
                                                            'type' => 'flag',
                                                            'wo' => $wo,
                                                            'stackIdx' => $stackIdx,
                                                        ];
                                                    }
                                                }
                                            }

                                            usort($cellBars, function($a, $b) {
                                                if ($a['type'] === $b['type']) return 0;
                                                if ($a['type'] === 'flag') return 1;
                                                if ($b['type'] === 'flag') return -1;
                                                return 0;
                                            });

                                            $maxVisibleBars = 1;
                                            $visibleBars = array_slice($cellBars, 0, $maxVisibleBars);
                                            $hiddenBars = array_slice($cellBars, $maxVisibleBars);

                                            $barHeight = 20;
                                            $barGap = 4;
                                            $cellPadding = 24;
                                            $expandCollapseButtonHeight = 32;
                                            $stackCount = count($cellBars);

                                            $collapsedHeight = max(
                                                $cellPadding + $maxVisibleBars * 2 * ($barHeight + $barGap) + $expandCollapseButtonHeight,
                                                64 + $expandCollapseButtonHeight
                                            );
                                            $expandedHeight = max(
                                                $cellPadding + $stackCount * 2 * ($barHeight + $barGap) + $expandCollapseButtonHeight,
                                                64 + $expandCollapseButtonHeight
                                            );
                                            $cellId = 'cell_' . $weekStart->format('Ymd') . '_' . $day->format('Ymd') . '_' . $dayIndex;

                                            // For planned-actual attachment logic
                                            $allBars = array_merge($visibleBars, $hiddenBars);
                                        @endphp

                                        <td class="align-top border relative p-0"
                                            style="min-width: 160px; height: {{ $collapsedHeight }}px;"
                                            id="{{ $cellId }}_td"
                                        >
                                            <div class="text-xs font-semibold {{ $day->isToday() ? 'text-blue-600' : 'text-gray-700' }} px-1 pt-1">
                                                {{ $day->format('j') }}
                                            </div>
                                            <div id="{{ $cellId }}" class="relative h-full" style="min-height: 64px;">
                                                @foreach($visibleBars as $bar)
                                                    @php
                                                        $wo = $bar['wo'];
                                                        $stackIdx = $bar['stackIdx'];
                                                        $factoryId = auth()->user()?->factory_id ?? 'default-factory';
                                                        $statusColors = config('work_order_status');
                                                        $barTop = 18 + $stackIdx * 2 * ($barHeight + $barGap);
                                                        $spanDays = isset($bar['spanDays']) ? max(1, $bar['spanDays']) : 1;
                                                        $totalQty = $wo->qty ?? 0;
                                                        $okQtys = $wo->ok_qtys ?? 0;
                                                        $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                                        $logs = $wo->workOrderLogs ?? [];
                                                        $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                                        $actualStatusKey = $actualEndLog ? strtolower($actualEndLog->status) : strtolower($wo->status);
                                                        $actualColor = $statusColors[$actualStatusKey] ?? '#10B981';

                                                        $isPlanned = $bar['type'] === 'planned';
                                                        $isActual = $bar['type'] === 'actual';

                                                        // Find planned bar for this WO in this cell (from all bars)
                                                        $plannedBarForWO = collect($allBars)->first(fn($b) => $b['type'] === 'planned' && $b['wo']->id === $wo->id);
                                                        $plannedBarTop = $plannedBarForWO ? (18 + $plannedBarForWO['stackIdx'] * 2 * ($barHeight + $barGap)) : null;
                                                        $actualBarTop = ($isActual && $plannedBarForWO && $plannedBarForWO['stackIdx'] === $stackIdx)
        ? ($plannedBarTop + $barHeight) // Attach actual just below planned
        : ($barTop + $barHeight + $barGap);                                                    @endphp

                                                    @if($isPlanned)
                                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                           class="absolute bg-blue-500 rounded flex items-center shadow hover:bg-blue-700 transition group"
                                                           style="top: {{ $barTop }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 10; text-decoration: none;"
                                                           title="{{ $wo->unique_id }}">
                                                            <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                {{ $wo->unique_id }}
                                                            </span>
                                                        </a>
                                                    @elseif($isActual)
                                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                           class="absolute rounded flex items-center shadow hover:opacity-90 transition group"
                                                           style="background: {{ $actualColor }}; top: {{ $actualBarTop }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 20; text-decoration: none;"
                                                           title="{{ $wo->unique_id }}">
                                                            <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                @if($percent > 0)
                                                                    {{ $percent }}%
                                                                @endif
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
                                                        class="absolute right-1 bottom-5 bg-gray-200 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50"
                                                        style="border:1px solid #ccc;">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
                                                                $barTop = 18 + $stackIdx * 2 * ($barHeight + $barGap);
                                                                $spanDays = isset($bar['spanDays']) ? max(1, $bar['spanDays']) : 1;
                                                                $totalQty = $wo->qty ?? 0;
                                                                $okQtys = $wo->ok_qtys ?? 0;
                                                                $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                                                $logs = $wo->workOrderLogs ?? [];
                                                                $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                                                $actualStatusKey = $actualEndLog ? strtolower($actualEndLog->status) : strtolower($wo->status);
                                                                $actualColor = $statusColors[$actualStatusKey] ?? '#10B981';

                                                                $isPlanned = $bar['type'] === 'planned';
                                                                $isActual = $bar['type'] === 'actual';

                                                                // Find planned bar for this WO in this cell (from all bars)
                                                                $plannedBarForWO = collect($allBars)->first(fn($b) => $b['type'] === 'planned' && $b['wo']->id === $wo->id);
                                                                $plannedBarTop = $plannedBarForWO ? (18 + $plannedBarForWO['stackIdx'] * 2 * ($barHeight + $barGap)) : null;
                                                                $actualBarTop = ($isActual && $plannedBarForWO && $plannedBarForWO['stackIdx'] === $stackIdx)
        ? ($plannedBarTop + $barHeight) // Attach actual just below planned
        : ($barTop + $barHeight + $barGap);                                                            @endphp

                                                            @if($isPlanned)
                                                                <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                                   class="absolute bg-blue-500 rounded flex items-center shadow hover:bg-blue-700 transition group"
                                                                   style="top: {{ $barTop }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 10; text-decoration: none;"
                                                                   title="{{ $wo->unique_id }}">
                                                                    <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                        {{ $wo->unique_id }}
                                                                    </span>
                                                                </a>
                                                            @elseif($isActual)
                                                                <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                                   class="absolute rounded flex items-center shadow hover:opacity-90 transition group"
                                                                   style="background: {{ $actualColor }}; top: {{ $actualBarTop }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 20; text-decoration: none;"
                                                                   title="{{ $wo->unique_id }}">
                                                                    <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                        @if($percent > 0)
                                                                            {{ $percent }}%
                                                                        @endif
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
                                                            class="absolute right-1 bottom-5 bg-gray-200 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50"
                                                            style="border:1px solid #ccc;">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Day View: Hourly Table Only --}}
            @if($timeRange === 'day')
            @php
                $carbonSelected = Carbon::parse($selectedDate);
                $hours = range(0, 23);
                $selectedDay = $carbonSelected->copy()->startOfDay();
                $selectedDayEnd = $carbonSelected->copy()->endOfDay();
                $filteredWOs = $workOrders->filter(function($wo) use ($selectedDay, $selectedDayEnd) {
                    $plannedStart = Carbon::parse($wo->start_time);
                    $plannedEnd = Carbon::parse($wo->end_time);
                    return $plannedStart->startOfDay() <= $selectedDayEnd && $plannedEnd->endOfDay() >= $selectedDay;
                });
            @endphp

            <div class="overflow-x-auto mt-8">
                <div class="w-full bg-white rounded-lg shadow border p-4">
                    <div class="mb-4">
                        <h3 class="text-xl font-semibold text-gray-800">
                            Work Order Day View
                        </h3>
                        <p class="text-sm text-gray-500">Outlook-style day view</p>
                    </div>
                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full table-fixed border-collapse">
                            <thead>
                                <tr>
                                    <th class="w-40 text-xs text-gray-500 bg-blue-50 border p-2">Date</th>
                                    @foreach($hours as $hour)
                                        <th class="text-xs text-gray-700 bg-yellow-50 border p-2 text-center">
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
                                        $plannedStart = Carbon::parse($wo->start_time);
                                        $plannedEnd = Carbon::parse($wo->end_time);
                                        $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                        $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                        $actualStart = $actualStartLog ? Carbon::parse($actualStartLog->changed_at) : null;
                                        $actualEnd = $actualEndLog ? Carbon::parse($actualEndLog->changed_at) : null;
                                        $actualStartHour = ($actualStart && $actualStart->isSameDay($selectedDay)) ? $actualStart->hour : null;
                                        $actualEndHour = ($actualEnd && $actualEnd->isSameDay($selectedDay)) ? $actualEnd->hour : null;
                                    @endphp
                                    <tr>
                                        <td class="bg-white border p-2 align-top">
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
                                                $collapsedHeight = max(
                                                    $cellPadding + $maxVisibleBars * 2 * ($barHeight + $barGap) + $expandCollapseButtonHeight,
                                                    36 + $expandCollapseButtonHeight
                                                );
                                                $expandedHeight = max(
                                                    $cellPadding + $stackCount * 2 * ($barHeight + $barGap) + $expandCollapseButtonHeight,
                                                    36 + $expandCollapseButtonHeight
                                                );
                                                $cellId = 'cell_' . $wo->id . '_' . $selectedDay->format('Ymd') . '_' . $h;
                                            @endphp
                                            <td class="relative border align-top p-0"
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
                                                        @endphp
                                                        @if($bar['type'] === 'planned')
                                                            <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                               class="absolute left-0 bg-blue-500 rounded shadow hover:bg-blue-700 transition group flex items-center"
                                                               style="top: {{ $barTop }}px; height: {{ $barHeight }}px; width: calc({{ $spanHours }} * 100% - 2px); min-width: 32px; z-index: 10; text-decoration: none;"
                                                               title="{{ $wo->unique_id }}">
                                                                <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                    {{ $wo->unique_id }}
                                                                </span>
                                                            </a>
                                                        @elseif($bar['type'] === 'actual')
                                                            <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                               class="absolute left-0 bg-green-500 rounded shadow hover:bg-green-700 transition group flex items-center"
                                                               style="top: {{ $barTop + $barHeight + $barGap }}px; height: {{ $barHeight }}px; width: calc({{ $spanHours }} * 100% - 2px); min-width: 32px; z-index: 20; text-decoration: none;"
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
                                                            class="absolute right-1 bottom-2 bg-gray-200 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50"
                                                            style="border:1px solid #ccc;">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
                                                                @endphp
                                                                @if($bar['type'] === 'planned')
                                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                                       class="absolute left-0 bg-blue-500 rounded shadow hover:bg-blue-700 transition group flex items-center"
                                                                       style="top: {{ $barTop }}px; height: {{ $barHeight }}px; width: calc({{ $spanHours }} * 100% - 2px); min-width: 32px; z-index: 10; text-decoration: none;"
                                                                       title="{{ $wo->unique_id }}">
                                                                        <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                            {{ $wo->unique_id }}
                                                                        </span>
                                                                    </a>
                                                                @elseif($bar['type'] === 'actual')
                                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                                       class="absolute left-0 bg-green-500 rounded shadow hover:bg-green-700 transition group flex items-center"
                                                                       style="top: {{ $barTop + $barHeight + $barGap }}px; height: {{ $barHeight }}px; width: calc({{ $spanHours }} * 100% - 2px); min-width: 32px; z-index: 20; text-decoration: none;"
                                                                       title="{{ $wo->unique_id }}">
                                                                        <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                                            {{ $percent }}%
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
                                                                class="absolute right-1 bottom-2 bg-gray-200 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50"
                                                                style="border:1px solid #ccc;">
                                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
            <div class="mt-8 flex justify-start">
                <a href="{{ url('admin/' . (auth()->user()?->factory_id ?? 3) . '/work-order-widgets') }}"
                   class="inline-flex items-center px-6 py-3 bg-blue-600 text-white text-base font-semibold rounded-lg shadow hover:bg-blue-700 transition">
                    ← Back to Previous Page
                </a>
            </div>
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
</body>
</html>
