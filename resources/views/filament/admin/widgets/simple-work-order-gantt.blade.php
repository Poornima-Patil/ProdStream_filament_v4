{{-- 
filepath: /Users/poornimapatil/Herd/ProdStream_v1.1/resources/views/filament/admin/widgets/simple-work-order-gantt.blade.php
--}}
@php
    use Illuminate\Support\Carbon;

    $allStart = collect($workOrders)->min('start_date');
    $allEnd = collect($workOrders)->max('end_date');
    $startDate = $allStart ? Carbon::parse($allStart)->startOfWeek() : now()->startOfWeek();
    $endDate = $allEnd ? Carbon::parse($allEnd)->endOfWeek() : now()->endOfWeek();

    $days = [];
    $current = $startDate->copy();
    while ($current <= $endDate) {
        $days[] = $current->copy();
        $current->addDay();
    }
    $weeks = array_chunk($days, 7);
    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

<div>
    <h1 class="text-2xl font-bold mb-4">Work Order Calendar (Weekly)</h1>
    <div class="w-full">
        <table class="w-full bg-white rounded shadow border">
            <thead>
                <tr>
                    <th class="w-20 min-w-[80px] text-xs text-gray-500 bg-blue-50 border p-2">Week</th>
                    @foreach($dayNames as $day)
                        <th class="text-xs text-gray-700 bg-yellow-50 border p-2">{{ $day }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($weeks as $week)
                    @php
                        $weekStart = $week[0];
                        $weekEnd = end($week);
                        $woStackOrder = [];
                        $woIndex = 0;
                        foreach ($workOrders as $wo) {
                            $plannedStart = Carbon::parse($wo['start_date'])->startOfDay();
                            $plannedEnd = Carbon::parse($wo['end_date'])->endOfDay();
                            $logs = $wo['workOrderLogs'] ?? [];
                            $actualStartLog = collect($logs)->where('status', 'Start')->sortBy('changed_at')->first();
                            $actualEndLog = collect($logs)->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                            $actualStart = $actualStartLog ? Carbon::parse($actualStartLog['changed_at'])->startOfDay() : null;
                            $actualEnd = $actualEndLog ? Carbon::parse($actualEndLog['changed_at'])->endOfDay() : null;

                            if (
                                ($plannedEnd >= $weekStart && $plannedStart <= $weekEnd) ||
                                ($actualStart && $actualEnd && $actualEnd >= $weekStart && $actualStart <= $weekEnd) ||
                                ($actualStart && !$actualEnd && $actualStart >= $weekStart && $actualStart <= $weekEnd)
                            ) {
                                $woStackOrder[$wo['id']] = $woIndex++;
                            }
                        }
                    @endphp
                    <tr class="relative">
                        <td class="text-xs text-gray-600 align-top border bg-blue-50 font-semibold">
                            {{ $week[0]->format('W') }}
                        </td>
                        @foreach($week as $dayIndex => $day)
                            @php
                                $cellBars = [];
                                foreach ($workOrders as $wo) {
                                    $stackIdx = $woStackOrder[$wo['id']] ?? null;
                                    if ($stackIdx === null) continue;

                                    // --- PLANNED BAR ---
                                    $plannedStart = Carbon::parse($wo['start_date'])->startOfDay();
                                    $plannedEnd = Carbon::parse($wo['end_date'])->endOfDay();

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
                                    $logs = $wo['workOrderLogs'] ?? [];
                                    $actualStartLog = collect($logs)->where('status', 'Start')->sortBy('changed_at')->first();
                                    $actualEndLog = collect($logs)->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                    $actualStart = $actualStartLog ? Carbon::parse($actualStartLog['changed_at'])->startOfDay() : null;
                                    $actualEnd = $actualEndLog ? Carbon::parse($actualEndLog['changed_at'])->endOfDay() : null;

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

                                // Collapse/Expand logic
                                usort($cellBars, function($a, $b) {
                                    if ($a['type'] === $b['type']) return 0;
                                    if ($a['type'] === 'flag') return 1; // flag last
                                    if ($b['type'] === 'flag') return -1;
                                    return 0;
                                });

                                $maxVisibleBars = 2;
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
                                            $totalQty = $wo['qty'] ?? 0;
                                            $okQtys = $wo['ok_qtys'] ?? 0;
                                            $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                            $logs = $wo['workOrderLogs'] ?? [];
                                            $actualEndLog = collect($logs)->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                            $actualStatusKey = $actualEndLog ? strtolower($actualEndLog['status']) : strtolower($wo['status']);
                                            $actualColor = $statusColors[$actualStatusKey] ?? '#10B981';

                                            $isPlanned = $bar['type'] === 'planned';
                                            $isActual = $bar['type'] === 'actual';

                                            // Find planned bar for this WO in this cell (from all bars)
                                            $plannedBarForWO = collect($allBars)->first(fn($b) => $b['type'] === 'planned' && $b['wo']['id'] === $wo['id']);
                                            $plannedBarTop = $plannedBarForWO ? (18 + $plannedBarForWO['stackIdx'] * 2 * ($barHeight + $barGap)) : null;
                                            $actualBarTop = ($isActual && $plannedBarForWO && $plannedBarForWO['stackIdx'] === $stackIdx)
                                                ? ($plannedBarTop + $barHeight)
                                                : ($barTop + $barHeight + $barGap);
                                        @endphp

                                        @if($bar['type'] === 'planned')
                                            <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo['id']) }}"
                                               class="absolute bg-blue-500 rounded flex items-center shadow hover:bg-blue-700 transition group"
                                               style="top: {{ $barTop }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 10; text-decoration: none;"
                                               title="{{ $wo['unique_id'] }}">
                                                <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                    {{ $wo['unique_id'] }}
                                                </span>
                                            </a>
                                        @elseif($bar['type'] === 'actual')
                                            <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo['id']) }}"
                                               class="absolute rounded flex items-center shadow hover:opacity-90 transition group"
                                               style="background: {{ $actualColor }}; top: {{ $actualBarTop }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 20; text-decoration: none;"
                                               title="{{ $wo['unique_id'] }}">
                                                <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                    @if($percent > 0)
                                                        {{ $percent }}%
                                                    @endif
                                                </span>
                                            </a>
                                        @elseif($bar['type'] === 'flag')
                                            <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo['id']) }}">
                                                <span class="absolute"
                                                      style="top: {{ $barTop + $barHeight + 2 }}px; left: 0; z-index: 40;">
                                                    <svg class="inline mr-1" width="20" height="20" viewBox="0 0 20 20" fill="#065f46" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
                                                        <path d="M5 3v14M5 3l10 4-10 4" stroke="#065f46" stroke-width="2" fill="none"/>
                                                        <circle cx="5" cy="3" r="2" fill="#065f46"/>
                                                    </svg>
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
                                                    $totalQty = $wo['qty'] ?? 0;
                                                    $okQtys = $wo['ok_qtys'] ?? 0;
                                                    $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                                    $logs = $wo['workOrderLogs'] ?? [];
                                                    $actualEndLog = collect($logs)->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                                    $actualStatusKey = $actualEndLog ? strtolower($actualEndLog['status']) : strtolower($wo['status']);
                                                    $actualColor = $statusColors[$actualStatusKey] ?? '#10B981';

                                                    $isPlanned = $bar['type'] === 'planned';
                                                    $isActual = $bar['type'] === 'actual';

                                                    // Find planned bar for this WO in this cell (from all bars)
                                                    $plannedBarForWO = collect($allBars)->first(fn($b) => $b['type'] === 'planned' && $b['wo']['id'] === $wo['id']);
                                                    $plannedBarTop = $plannedBarForWO ? (18 + $plannedBarForWO['stackIdx'] * 2 * ($barHeight + $barGap)) : null;
                                                    $actualBarTop = ($isActual && $plannedBarForWO && $plannedBarForWO['stackIdx'] === $stackIdx)
                                                        ? ($plannedBarTop + $barHeight)
                                                        : ($barTop + $barHeight + $barGap);
                                                @endphp

                                                @if($bar['type'] === 'planned')
                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo['id']) }}"
                                                       class="absolute bg-blue-500 rounded flex items-center shadow hover:bg-blue-700 transition group"
                                                       style="top: {{ $barTop }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 10; text-decoration: none;"
                                                       title="{{ $wo['unique_id'] }}">
                                                        <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                            {{ $wo['unique_id'] }}
                                                        </span>
                                                    </a>
                                                @elseif($bar['type'] === 'actual')
                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo['id']) }}"
                                                       class="absolute rounded flex items-center shadow hover:opacity-90 transition group"
                                                       style="background: {{ $actualColor }}; top: {{ $actualBarTop }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 20; text-decoration: none;"
                                                       title="{{ $wo['unique_id'] }}">
                                                        <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                            @if($percent > 0)
                                                                {{ $percent }}%
                                                            @endif
                                                        </span>
                                                    </a>
                                                @elseif($bar['type'] === 'flag')
                                                    <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $wo['id']) }}">
                                                        <span class="absolute"
                                                              style="top: {{ $barTop + $barHeight + 2 }}px; left: 0; z-index: 40;">
                                                            <svg class="inline mr-1" width="20" height="20" viewBox="0 0 20 20" fill="#065f46" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
                                                                <path d="M5 3v14M5 3l10 4-10 4" stroke="#065f46" stroke-width="2" fill="none"/>
                                                                <circle cx="5" cy="3" r="2" fill="#065f46"/>
                                                            </svg>
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

        {{-- Debug: Dump logs for each WO --}}
        <div class="bg-gray-100 p-4 mt-8 rounded text-xs">
            <strong>DEBUG: Work Order Logs</strong>
            <ul class="list-disc pl-6">
                @foreach($workOrders as $wo)
                    <li>
                        <span class="font-bold">{{ $wo['unique_id'] ?? $wo['id'] }}</span>
                        <ul>
                            @foreach(($wo['workOrderLogs'] ?? []) as $log)
                                <li>
                                    [{{ $log['status'] }}: {{ $log['changed_at'] }}]
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
