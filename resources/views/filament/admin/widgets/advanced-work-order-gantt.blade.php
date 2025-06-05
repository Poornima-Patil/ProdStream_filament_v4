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

            {{-- Gantt Chart Table --}}
            <div class="overflow-x-auto">
                @php
                    use Illuminate\Support\Carbon;
                    $defaultStartDate = now()->subMonth()->startOfWeek();
                    $defaultEndDate = now()->endOfWeek();
                    $startDate = collect($workOrders)->min('start_time')
                        ? Carbon::parse(collect($workOrders)->min('start_time'))->startOfWeek()
                        : $defaultStartDate;
                    $endDate = collect($workOrders)->max('end_time')
                        ? Carbon::parse(collect($workOrders)->max('end_time'))->endOfWeek()
                        : $defaultEndDate;
                    $columns = collect([]);
                    $currentDate = $startDate->copy();
                    $end = $endDate->copy();
                    $colType = $timeRange;
                    $maxCols = 52;
                    $colCount = 0;
                    while ($currentDate <= $end && $colCount < $maxCols) {
                        if ($colType === 'month') {
                            $columns->push([
                                'start' => $currentDate->copy()->startOfMonth()->format('Y-m-d'),
                                'end' => $currentDate->copy()->endOfMonth()->format('Y-m-d'),
                                'label' => $currentDate->format('M Y'),
                            ]);
                            $currentDate->addMonth();
                        } elseif ($colType === 'day') {
                            $columns->push([
                                'start' => $currentDate->format('Y-m-d'),
                                'end' => $currentDate->format('Y-m-d'),
                                'label' => $currentDate->format('M d'),
                            ]);
                            $currentDate->addDay();
                        } else {
                            $columns->push([
                                'start' => $currentDate->format('Y-m-d'),
                                'end' => $currentDate->copy()->endOfWeek()->format('Y-m-d'),
                                'label' => $currentDate->format('M d') . ' - ' . $currentDate->copy()->endOfWeek()->format('M d'),
                            ]);
                            $currentDate->addWeek();
                        }
                        $colCount++;
                    }
                    $workOrderColumnWidth = 250;
                    $colWidth = 150;
                    $tableWidth = $workOrderColumnWidth + (count($columns) * $colWidth);
                @endphp
                <div class="w-full" style="min-width: {{ $tableWidth }}px;">
                    <div class="p-4 bg-white rounded-lg shadow border">
                        <div class="mb-4">
                            <h3 class="text-xl font-semibold text-gray-800">Work Order Timeline</h3>
                            <p class="text-sm text-gray-500">Filtered by {{ ucfirst($timeRange) }}</p>
                        </div>
                        <div class="border rounded-lg overflow-hidden">
                            <table class="w-full table-fixed divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="sticky left-0 z-20 bg-gray-50 border-r w-64 max-w-xs">
                                            <div class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Work Order
                                            </div>
                                        </th>
                                        @foreach($columns as $col)
                                            <th class="border-r" style="width: {{ $colWidth }}px">
                                                <div class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    {{ $col['label'] }}
                                                </div>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($workOrders as $workOrder)
                                        @php
                                            $woStart = \Carbon\Carbon::parse($workOrder->start_time);
                                            $woEnd = \Carbon\Carbon::parse($workOrder->end_time);
                                            $totalQty = $workOrder->qty ?? 0;
                                            $okQtys = $workOrder->ok_qtys ?? 0;
                                            $scrappedQtys = $workOrder->scrapped_qtys ?? 0;
                                            $actualStartLog = $workOrder->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                            $actualEndLog = $workOrder->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                            $actualStart = $actualStartLog ? \Carbon\Carbon::parse($actualStartLog->changed_at) : null;
                                            $actualEnd = $actualEndLog ? \Carbon\Carbon::parse($actualEndLog->changed_at) : null;
                                            $timelineStart = \Carbon\Carbon::parse($columns->first()['start']);
                                            $timelineEnd = \Carbon\Carbon::parse($columns->last()['end']);
                                            $totalTimelineDays = $timelineStart->diffInDays($timelineEnd) ?: 1;
                                            $actualStartOffset = $actualStart ? $timelineStart->diffInDays($actualStart) : 0;
                                            $actualEndOffset = $actualEnd ? $timelineStart->diffInDays($actualEnd) : $totalTimelineDays;
                                            $actualBarLeft = ($actualStartOffset / $totalTimelineDays) * 100;
                                            $actualBarWidth = (($actualEndOffset - $actualStartOffset) / $totalTimelineDays) * 100;
                                            $okPercentage = $totalQty > 0 ? ($okQtys / $totalQty) * 100 : 0;
                                            $scrappedPercentage = $totalQty > 0 ? ($scrappedQtys / $totalQty) * 100 : 0;
                                            $totalCols = count($columns);
                                            $plannedStartIndex = $columns->search(fn($col) => $woStart->between($col['start'], $col['end']));
                                            $plannedEndIndex = $columns->search(fn($col) => $woEnd->between($col['start'], $col['end']));
                                            $plannedWidth = ($plannedEndIndex !== false && $plannedStartIndex !== false)
                                                ? max(($plannedEndIndex - $plannedStartIndex + 1) * 100 / $totalCols, 1)
                                                : 0;
                                            $plannedLeft = ($plannedStartIndex !== false)
                                                ? $plannedStartIndex * 100 / $totalCols
                                                : 0;
                                            $factoryId = auth()->user()?->factory_id ?? 'default-factory';
                                            $statusColors = config('work_order_status');
                                            $statusKey = strtolower($workOrder->status);
                                            $okColor = $statusColors[$statusKey] ?? '#10B981'; // fallback to green if not found
                                            // For flag logic
                                            $firstStartLog = $workOrder->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                            $firstStartDate = $firstStartLog ? \Carbon\Carbon::parse($firstStartLog->changed_at)->toDateString() : null;
                                            $hasActualBar = $actualStart && $actualEnd;
                                        @endphp
                                        <tr>
                                            <td class="sticky left-0 z-20 bg-white border-r w-64 max-w-xs">
                                                <div class="px-4 py-4">
                                                    <div class="text-xs font-medium text-gray-900 break-all whitespace-normal">
                                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $workOrder->id) }}"
                                                           class="text-blue-500 hover:underline">
                                                            {{ $workOrder->unique_id }}
                                                        </a>
                                                    </div>
                                                    <div class="text-xs text-gray-500">{{ $workOrder->status }}</div>
                                                </div>
                                            </td>
                                            <td colspan="{{ count($columns) }}" class="relative p-0" style="height: 64px;">
                                                <div class="absolute inset-0">
                                                    {{-- Planned Bar --}}
                                                    @if ($plannedWidth > 0)
                                                        <div class="absolute h-4 bg-blue-500 rounded top-[20%] mx-2"
                                                             style="width: calc({{ $plannedWidth }}% - 16px); left: {{ $plannedLeft }}%;">
                                                            <div class="flex items-center justify-center h-full">
                                                                <span class="text-xs text-white font-medium px-2 truncate">
                                                                    Planned
                                                                </span>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    {{-- Actual Bar --}}
                                                    @if ($actualStart && $actualEnd)
                                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $workOrder->id) }}"
                                                           class="absolute h-4 bg-green-500 rounded top-[60%] mx-2 flex items-center shadow hover:bg-green-700 transition group"
                                                           style="width: calc({{ $actualBarWidth }}% - 16px); left: {{ $actualBarLeft }}%; z-index: 20; text-decoration: none;"
                                                           title="{{ $workOrder->unique_id }}">
                                                            <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: 16px;">
                                                                {{ $workOrder->unique_id }}
                                                            </span>
                                                        </a>
                                                    @endif

                                                    {{-- Start Flag if no bars --}}
                                                    @if($firstStartDate)
                                                        @php
                                                            // Find the column index for the first start date
                                                            $flagColIdx = $columns->search(fn($col) =>
                                                                $firstStartDate >= $col['start'] && $firstStartDate <= $col['end']
                                                            );
                                                        @endphp
                                                        @if($flagColIdx !== false && (!$hasActualBar))
                                                            <span class="absolute" style="top: 0.5rem; left: calc({{ $flagColIdx * 100 / $totalCols }}% + 8px); z-index: 40;">
                                                                <svg class="inline mr-1" width="16" height="16" viewBox="0 0 20 20" fill="#065f46" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
                                                                    <path d="M5 3v14M5 3l10 4-10 4" stroke="#065f46" stroke-width="2" fill="none"/>
                                                                    <circle cx="5" cy="3" r="2" fill="#065f46"/>
                                                                </svg>
                                                                {{-- DEBUG: WO={{ $workOrder->unique_id }}, FlagColIdx={{ $flagColIdx }}, FirstStartDate={{ $firstStartDate }}, hasActualBar={{ $hasActualBar ? 'true' : 'false' }} --}}
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Calendar View (hide for day view) --}}
            @if($timeRange !== 'day')
            @php
                $carbonSelected = Carbon::parse($selectedDate);

                // Set up calendar grid based on view
                if ($timeRange === 'month') {
                    $firstDay = $carbonSelected->copy()->startOfMonth()->startOfWeek();
                    $lastDay = $carbonSelected->copy()->endOfMonth()->endOfWeek();
                } elseif ($timeRange === 'week') {
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
                                    @if($timeRange === 'month' || $timeRange === 'week')
                                        <th class="w-14 text-xs text-gray-500 bg-blue-50 border p-2">Week</th>
                                    @endif
                                    @foreach($dayNames as $day)
                                        <th class="text-xs text-gray-700 bg-yellow-50 border p-2">{{ $day }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($weeks as $week)
                                <tr class="relative" style="height: 64px;">
                                    @if($timeRange === 'month' || $timeRange === 'week')
                                        <td class="text-xs text-gray-600 align-top border bg-blue-50 font-semibold">
                                            {{ $week[0]->format('W') }}
                                        </td>
                                    @endif

                                    @php
                                        // Calculate stack order for this week
                                        $weekStart = $week[0];
                                        $weekEnd = end($week);
                                        $woStackOrder = [];
                                        $woIndex = 0;
                                        foreach ($workOrders as $wo) {
                                            $plannedStart = \Carbon\Carbon::parse($wo->start_time)->startOfDay();
                                            $plannedEnd = \Carbon\Carbon::parse($wo->end_time)->endOfDay();
                                            $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                            $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                            $actualStart = $actualStartLog ? \Carbon\Carbon::parse($actualStartLog->changed_at)->startOfDay() : null;
                                            $actualEnd = $actualEndLog ? \Carbon\Carbon::parse($actualEndLog->changed_at)->endOfDay() : null;

                                            if (
                                                ($plannedEnd >= $weekStart && $plannedStart <= $weekEnd) ||
                                                ($actualStart && $actualEnd && $actualEnd >= $weekStart && $actualStart <= $weekEnd)
                                            ) {
                                                $woStackOrder[$wo->id] = $woIndex++;
                                            }
                                        }
                                        $stackCount = count($woStackOrder);
                                        $barHeight = 20;
                                        $barGap = 4;
                                        $cellPadding = 24;
                                        $cellHeight = max($cellPadding + $stackCount * 2 * ($barHeight + $barGap), 64);
                                    @endphp

                                    @foreach($week as $dayIndex => $day)
                                        <td class="align-top border relative" style="height: {{ $cellHeight }}px;">
                                            <div class="text-xs font-semibold {{ $day->isToday() ? 'text-blue-600' : 'text-gray-700' }}">
                                                {{ $day->format('j') }}
                                            </div>
                                            @php
                                                $barGap = 4;
                                                $barHeight = 20;
                                                $barTop = 18;
                                                $cellBars = [];

                                                foreach ($workOrders as $wo) {
                                                    $stackIdx = $woStackOrder[$wo->id] ?? null;
                                                    if ($stackIdx === null) continue;

                                                    // --- PLANNED BAR ---
                                                    $plannedStart = \Carbon\Carbon::parse($wo->start_time)->startOfDay();
                                                    $plannedEnd = \Carbon\Carbon::parse($wo->end_time)->endOfDay();

                                                    // Clamp to week
                                                    $barStart = $plannedStart->greaterThan($weekStart) ? $plannedStart : $weekStart;
                                                    $barEnd = $plannedEnd->lessThan($weekEnd) ? $plannedEnd : $weekEnd;

                                                    // Only show if bar is visible in this week
                                                    if ($barEnd >= $weekStart && $barStart <= $weekEnd) {
                                                        $plannedStartIdx = collect($week)->search(fn($d) => $d->equalTo($barStart));
                                                        if ($plannedStartIdx === false) $plannedStartIdx = 0;

                                                        // Find the last day in the week that is not after the planned end
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
                                                    $actualStart = $actualStartLog ? \Carbon\Carbon::parse($actualStartLog->changed_at)->startOfDay() : null;
                                                    $actualEnd = $actualEndLog ? \Carbon\Carbon::parse($actualEndLog->changed_at)->endOfDay() : null;

                                                    if ($actualStart && $actualEnd) {
                                                        $actualBarStart = $actualStart->greaterThan($weekStart) ? $actualStart : $weekStart;
                                                        $actualBarEnd = $actualEnd->lessThan($weekEnd) ? $actualEnd : $weekEnd;

                                                        if ($actualBarEnd >= $weekStart && $actualBarStart <= $weekEnd) {
                                                            $actualStartIdx = collect($week)->search(fn($d) => $d->equalTo($actualBarStart));
                                                            if ($actualStartIdx === false) $actualStartIdx = 0;

                                                            // Find the last day in the week that is not after the actual end
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
                                                }
                                            @endphp

                                            @foreach($cellBars as $bar)
                                                @php
                                                    $wo = $bar['wo'];
                                                    $stackIdx = $bar['stackIdx'];
                                                    $factoryId = auth()->user()?->factory_id ?? 'default-factory';
                                                    $statusColors = config('work_order_status');
                                                    $statusKey = strtolower($wo->status);
                                                    $okColor = $statusColors[$statusKey] ?? '#6B7280';
                                                    $totalQty = $wo->qty ?? 0;
                                                    $okQtys = $wo->ok_qtys ?? 0;
                                                    $scrappedQtys = $wo->scrapped_qtys ?? 0;
                                                    $okPercentage = $totalQty > 0 ? ($okQtys / $totalQty) * 100 : 0;
                                                    $scrappedPercentage = $totalQty > 0 ? ($scrappedQtys / $totalQty) * 100 : 0;
                                                    $barHeight = 20;
                                                    $barGap = 4;
                                                    $barTop = 18;
                                                    $spanDays = max(1, $bar['spanDays']);
                                                    $firstStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                                    $firstStartDate = $firstStartLog ? \Carbon\Carbon::parse($firstStartLog->changed_at)->toDateString() : null;
                                                    $isFirstStartDay = $firstStartDate && $firstStartDate === $day->toDateString();
                                                @endphp

                                                @if($bar['type'] === 'planned')
                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                       class="absolute bg-blue-500 rounded flex items-center shadow hover:bg-blue-700 transition group"
                                                       style="top: {{ $barTop + $stackIdx * 2 * ($barHeight + $barGap) }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 10; text-decoration: none;"
                                                       title="{{ $wo->unique_id }}">
                                                        <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                            {{ $wo->unique_id }}
                                                        </span>
                                                    </a>
                                                @elseif($bar['type'] === 'actual')
                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                       class="absolute bg-green-500 rounded flex items-center shadow hover:bg-green-700 transition group"
                                                       style="top: {{ $barTop + $stackIdx * 2 * ($barHeight + $barGap) + $barHeight + $barGap }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 20; text-decoration: none;"
                                                       title="{{ $wo->unique_id }}">
                                                        <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                            @if($isFirstStartDay)
                                                                <svg class="inline mr-1" width="16" height="16" viewBox="0 0 20 20" fill="#065f46" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
                                                                    <path d="M5 3v14M5 3l10 4-10 4" stroke="#065f46" stroke-width="2" fill="none"/>
                                                                    <circle cx="5" cy="3" r="2" fill="#065f46"/>
                                                                </svg>
                                                            @endif
                                                            {{ $wo->qty && $wo->ok_qtys ? round(($wo->ok_qtys / $wo->qty) * 100) : 0 }}%
                                                        </span>
                                                    </a>
                                                @endif
                                            @endforeach

                                            {{-- Show flag if no bars for this WO on this day and it's the first start day --}}
                                            @foreach($workOrders as $woFlag)
                                                @php
                                                    $firstStartLogFlag = $woFlag->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                                    $firstStartDateFlag = $firstStartLogFlag ? \Carbon\Carbon::parse($firstStartLogFlag->changed_at)->toDateString() : null;
                                                    $isFirstStartDayFlag = $firstStartDateFlag && $firstStartDateFlag === $day->toDateString();
                                                    $hasActualBarToday = collect($cellBars)->contains(fn($b) => $b['wo']->id === $woFlag->id && $b['type'] === 'actual');
                                                    // Find the planned bar for this WO in this cell
                                                    $plannedBar = collect($cellBars)->first(fn($b) => $b['wo']->id === $woFlag->id && $b['type'] === 'planned');
                                                    $plannedStackIdx = $plannedBar['stackIdx'] ?? 0;
                                                    $plannedSpanDays = $plannedBar['spanDays'] ?? 1;
                                                    $barTop = 18;
                                                    $barHeight = 20;
                                                    $barGap = 4;
                                                @endphp
                                                @if($isFirstStartDayFlag && !$hasActualBarToday && $plannedBar)
                                                <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}">

                                                    <span class="absolute"
                                                          style="top: {{ $barTop + $plannedStackIdx * 2 * ($barHeight + $barGap) + $barHeight + 2 }}px; left: 0; z-index: 40;">
                                                        <svg class="inline mr-1" width="20" height="20" viewBox="0 0 20 20" fill="#065f46" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
                                                            <path d="M5 3v14M5 3l10 4-10 4" stroke="#065f46" stroke-width="2" fill="none"/>
                                                            <circle cx="5" cy="3" r="2" fill="#065f46"/>
                                                        </svg>
                                                    </span>
                                                </a>
                                                @endif
                                            @endforeach
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
                $carbonSelected = \Carbon\Carbon::parse($selectedDate);
                $hours = range(0, 23);
                // Group work orders by date (should be only one group for daily view, but supports multiple if needed)
                $grouped = $workOrders->groupBy(function($wo) use ($carbonSelected) {
                    return $carbonSelected->format('Y-m-d');
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
                                    $selectedDay = $carbonSelected->copy()->startOfDay();
                                    $selectedDayEnd = $carbonSelected->copy()->endOfDay();
                                    // Only include WOs where the selected day is between planned start and planned end (inclusive)
                                    $filteredWOs = $workOrders->filter(function($wo) use ($selectedDay, $selectedDayEnd) {
                                        $plannedStart = \Carbon\Carbon::parse($wo->start_time);
                                        $plannedEnd = \Carbon\Carbon::parse($wo->end_time);
                                        return $plannedStart->startOfDay() <= $selectedDayEnd && $plannedEnd->endOfDay() >= $selectedDay;
                                    });
                                @endphp

@php
$barHeight = 16;
$barGap = 4;
$rowHeight = max(36, $filteredWOs->count() * ($barHeight + $barGap) * 2);
@endphp

                                @foreach($filteredWOs as $wo)
                                    <tr>
                                         <td class="bg-white border p-2 align-top" style="height: {{ $rowHeight }}px;">
            {{ $carbonSelected->format('M d, Y') }}
        </td>
                                        @for($h = 0; $h < 24; $h++)
                                            @php
                                                $plannedStart = \Carbon\Carbon::parse($wo->start_time);
                                                $plannedEnd = \Carbon\Carbon::parse($wo->end_time);
                                                $rowStart = $selectedDay;
                                                $rowEnd = $selectedDayEnd;
                                                // Determine bar start/end hour for this day
                                                $startHour = ($plannedStart->isSameDay($rowStart)) ? $plannedStart->hour : 0;
                                                $endHour = ($plannedEnd->isSameDay($rowStart)) ? $plannedEnd->hour : 23;
                                                $spanHours = max(1, $endHour - $startHour + 1);
                                                $factoryId = auth()->user()?->factory_id ?? 'default-factory';

                                                // Actual bar logic
                                                $actualStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                                $actualEndLog = $wo->workOrderLogs->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                                $actualStart = $actualStartLog ? \Carbon\Carbon::parse($actualStartLog->changed_at) : null;
                                                $actualEnd = $actualEndLog ? \Carbon\Carbon::parse($actualEndLog->changed_at) : null;
                                                $actualStartHour = ($actualStart && $actualStart->isSameDay($rowStart)) ? $actualStart->hour : null;
                                                $actualEndHour = ($actualEnd && $actualEnd->isSameDay($rowStart)) ? $actualEnd->hour : null;
                                                $actualSpanHours = ($actualStartHour !== null && $actualEndHour !== null) ? max(1, $actualEndHour - $actualStartHour + 1) : null;
                                            @endphp
            <td class="relative border align-top p-0" style="height: {{ $rowHeight }}px;">
                {{-- Planned Bar --}}
                                                @if($h === $startHour)
                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                       class="absolute left-0 top-2 bg-blue-500 rounded shadow hover:bg-blue-700 transition group flex items-center"
                                                       style="height: 16px; width: calc({{ $spanHours }} * 100% - 2px); min-width: 32px; z-index: 10; text-decoration: none;"
                                                       title="{{ $wo->unique_id }}">
                                                        <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: 16px;">
                                                            {{ $wo->unique_id }}
                                                        </span>
                                                    </a>
                                                @endif

                                                {{-- Actual Bar --}}
                                                @if($h === $actualStartHour && $actualSpanHours)
                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}"
                                                       class="absolute left-0 top-4 bg-green-500 rounded shadow hover:bg-green-700 transition group flex items-center"
                                                       style="height: 16px; width: calc({{ $actualSpanHours }} * 100% - 2px); min-width: 32px; z-index: 20; text-decoration: none;"
                                                       title="{{ $wo->unique_id }}">
                                                        <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: 16px;">
                                                            {{ $wo->qty && $wo->ok_qtys ? round(($wo->ok_qtys / $wo->qty) * 100) : 0 }}%
                                                            @php
                                                                $firstStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                                                $firstStartDateTime = $firstStartLog ? \Carbon\Carbon::parse($firstStartLog->changed_at) : null;
                                                                $firstStartHour = $firstStartDateTime ? $firstStartDateTime->hour : null;
                                                                $firstStartDay = $firstStartDateTime ? $firstStartDateTime->isSameDay($rowStart) : false;
                                                                $hasActualBar = $actualStartHour !== null && $actualEndHour !== null;
                                                            @endphp
                                                            @if($firstStartLog && $firstStartDay && $h === $firstStartHour && !$hasActualBar)
                                                            <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}">

                                                                <span class="absolute left-0 top-2 z-40">
                                                                    <svg class="inline ml-1" width="24" height="24" viewBox="0 0 20 20" fill="#065f46" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
                                                                        <path d="M5 3v14M5 3l10 4-10 4" stroke="#065f46" stroke-width="2" fill="none"/>
                                                                        <circle cx="5" cy="3" r="2" fill="#065f46"/>
                                                                    </svg>
                                                            @endif
                                                        </span>
                                                    </a>
                                                @endif
                                                @php
                                                $firstStartLog = $wo->workOrderLogs->where('status', 'Start')->sortBy('changed_at')->first();
                                                $firstStartDateTime = $firstStartLog ? \Carbon\Carbon::parse($firstStartLog->changed_at) : null;
                                                $firstStartHour = $firstStartDateTime ? $firstStartDateTime->hour : null;
                                                $firstStartDay = $firstStartDateTime ? $firstStartDateTime->isSameDay($rowStart) : false;
                                                $hasActualBar = $actualStartHour !== null && $actualEndHour !== null;
                                            @endphp
                                            @if($firstStartLog && $firstStartDay && $h === $firstStartHour && !$hasActualBar)
                                            <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo->id) }}">

                                                <span class="absolute left-0 top-2 z-40">
                                                    <svg class="inline ml-1" width="24" height="24" viewBox="0 0 20 20" fill="#065f46" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
                                                        <path d="M5 3v14M5 3l10 4-10 4" stroke="#065f46" stroke-width="2" fill="none"/>
                                                        <circle cx="5" cy="3" r="2" fill="#065f46"/>
                                                    </svg>
                                                </span>
                                            </a>
                                            @endif
                                            
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

            {{-- Debug: Show work order count and first work order --}}
            <div class="text-xs text-red-600">
                WorkOrders: {{ $workOrders->count() }}
                @if($workOrders->count())
                    <br>First WO: {{ $workOrders->first()->unique_id ?? '' }}
                    <br>Logs: {{ $workOrders->first()->workOrderLogs->count() }}
                @endif
            </div>

            {{-- Back Button --}}
            <div class="mt-8 flex justify-start">
                <a href="{{ url('admin/' . (auth()->user()?->factory_id ?? 3) . '/work-order-widgets') }}"
                   class="inline-flex items-center px-6 py-3 bg-blue-600 text-white text-base font-semibold rounded-lg shadow hover:bg-blue-700 transition">
                    ← Back to Previous Page
                </a>
            </div>

            {{-- Debug Info --}}
            <div class="mt-8 text-xs text-gray-500">
                @foreach($workOrders as $wo)
                    <div class="text-xs text-gray-400">
                        WO: {{ $wo->unique_id }}<br>
                        Planned: {{ $wo->start_time }} - {{ $wo->end_time }}<br>
                        Actual: 
                        @foreach($wo->workOrderLogs as $log)
                            [{{ $log->status }}: {{ $log->changed_at }}]
                        @endforeach
                    </div>
                @endforeach
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
