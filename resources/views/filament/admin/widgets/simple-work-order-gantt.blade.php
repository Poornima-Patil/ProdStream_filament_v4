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
                                ($actualStart && $actualEnd && $actualEnd >= $weekStart && $actualStart <= $weekEnd)
                            ) {
                                $woStackOrder[$wo['id']] = $woIndex++;
                            }
                        }
                        $stackCount = count($woStackOrder);
                        $barHeight = 20;
                        $barGap = 4;
                        $cellPadding = 24;
                        $cellHeight = max($cellPadding + $stackCount * 2 * ($barHeight + $barGap), 64);
                    @endphp
                    <tr class="relative" style="height: {{ $cellHeight }}px;">
                        <td class="text-xs text-gray-600 align-top border bg-blue-50 font-semibold">
                            {{ $week[0]->format('W') }}
                        </td>
                        @foreach($week as $dayIndex => $day)
                            <td class="align-top border relative p-0" style="min-width: 160px; height: {{ $cellHeight }}px;">
                                <div class="text-xs font-semibold {{ $day->isToday() ? 'text-blue-600' : 'text-gray-700' }} px-1 pt-1">
                                    {{ $day->format('j') }}
                                </div>
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
                                    }
                                @endphp

                                @foreach($cellBars as $bar)
                                    @php
                                        $wo = $bar['wo'];
                                        $stackIdx = $bar['stackIdx'];
                                        $factoryId = auth()->user()?->factory_id ?? 'default-factory';
                                        $statusColors = config('work_order_status');
                                        $barHeight = 20;
                                        $barGap = 4;
                                        $barTop = 18 + $stackIdx * 2 * ($barHeight + $barGap);
                                        $spanDays = max(1, $bar['spanDays']);
                                        $totalQty = $wo['qty'] ?? 0;
                                        $okQtys = $wo['ok_qtys'] ?? 0;
                                        $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                        $logs = $wo['workOrderLogs'] ?? [];
                                        $actualEndLog = collect($logs)->whereIn('status', ['Closed', 'Completed', 'Hold'])->sortByDesc('changed_at')->first();
                                        $actualStatusKey = $actualEndLog ? strtolower($actualEndLog['status']) : strtolower($wo['status']);
                                        $actualColor = $statusColors[$actualStatusKey] ?? '#10B981';
                                        $firstStartLog = collect($logs)->where('status', 'Start')->sortBy('changed_at')->first();
                                        $firstStartDate = $firstStartLog ? \Carbon\Carbon::parse($firstStartLog['changed_at'])->toDateString() : null;
                                        $isFirstStartDay = $firstStartDate && $firstStartDate === $day->toDateString();
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
                                           style="background: {{ $actualColor }}; top: {{ $barTop + $barHeight + $barGap }}px; left: 0; height: {{ $barHeight }}px; width: calc({{ $spanDays }} * 100% + ({{ $spanDays-1 }} * 2px)); min-width: 8px; z-index: 20; text-decoration: none;"
                                           title="{{ $wo['unique_id'] }}">
                                            <span class="text-xs text-white font-semibold px-2 truncate w-full" style="line-height: {{ $barHeight }}px;">
                                                @if($percent > 0)
                                                    {{ $percent }}%
                                                @endif
                                            </span>
                                        </a>
                                    @endif
                                @endforeach

                                {{-- Show flag if no actual bar for this WO on this day and it's the first start day --}}
                                @foreach($workOrders as $woFlag)
                                    @php
                                        $logsFlag = $woFlag['workOrderLogs'] ?? [];
                                        $firstStartLogFlag = collect($logsFlag)->where('status', 'Start')->sortBy('changed_at')->first();
                                        $firstStartDateFlag = $firstStartLogFlag ? \Carbon\Carbon::parse($firstStartLogFlag['changed_at'])->toDateString() : null;
                                        $isFirstStartDayFlag = $firstStartDateFlag && $firstStartDateFlag === $day->toDateString();
                                        $hasActualBarToday = collect($cellBars)->contains(fn($b) => $b['wo']['id'] === $woFlag['id'] && $b['type'] === 'actual');
                                        // Find the planned bar for this WO in this cell
                                        $plannedBar = collect($cellBars)->first(fn($b) => $b['wo']['id'] === $woFlag['id'] && $b['type'] === 'planned');
                                        $plannedStackIdx = $plannedBar['stackIdx'] ?? 0;
                                        $barTop = 18;
                                        $barHeight = 20;
                                        $barGap = 4;
                                    @endphp
                                    @if($isFirstStartDayFlag && !$hasActualBarToday && $plannedBar)
                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $woFlag['id']) }}">
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
