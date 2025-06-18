{{-- 
Chefilepath: resources/views/filament/admin/widgets/simple-work-order-gantt.blade.php
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

<div class="">
    <div class="min-w-[1100px] w-full mx-auto">
        <h1 class="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-100">Work Order Calendar (Weekly)</h1>
        <div class="w-full bg-white dark:bg-gray-900 rounded shadow border border-gray-200 dark:border-gray-700 p-4">
            <div class="w-full">
                <div class="grid grid-cols-8 bg-indigo-200 dark:bg-indigo-900 border-b border-gray-200 dark:border-gray-700 mb-2 w-full table-fixed">
                    <div class="text-xs font-semibold py-2 px-2 text-gray-900 dark:text-gray-100 bg-indigo-300 dark:bg-indigo-800">Week</div>
                    @foreach($dayNames as $day)
                        <div class="text-xs font-semibold py-2 px-2 text-gray-900 dark:text-gray-100 bg-indigo-200 dark:bg-indigo-900">{{ $day }}</div>
                    @endforeach
                </div>
                @foreach($weeks as $weekIdx => $week)
                    @php
                        $rowId = "row_{$weekIdx}";
                        // Prepare cellBarsArr for this week
                        $cellBarsArr = [];
                        $maxBarsInRow = 0;
                        $woStackOrder = [];
                        $woIndex = 0;
                        foreach ($workOrders as $wo) {
                            $plannedStart = \Carbon\Carbon::parse($wo['start_date'])->startOfDay();
                            $plannedEnd = \Carbon\Carbon::parse($wo['end_date'])->endOfDay();
                            if (
                                ($plannedEnd >= $week[0] && $plannedStart <= end($week))
                            ) {
                                $woStackOrder[$wo['id']] = $woIndex++;
                            }
                        }
                        foreach($week as $dayIdx => $day) {
                            $cellBars = [];
                            foreach ($workOrders as $wo) {
                                $stackIdx = $woStackOrder[$wo['id']] ?? null;
                                if ($stackIdx === null) continue;
                                $plannedStart = \Carbon\Carbon::parse($wo['start_date'])->startOfDay();
                                $plannedEnd = \Carbon\Carbon::parse($wo['end_date'])->endOfDay();
                                if ($plannedStart <= $day && $plannedEnd >= $day) {
                                    $cellBars[] = [
                                        'type' => 'planned',
                                        'wo' => $wo,
                                        'stackIdx' => $stackIdx,
                                    ];
                                }
                            }
                            $cellBarsArr[$dayIdx] = $cellBars;
                            $maxBarsInRow = max($maxBarsInRow, count($cellBars));
                        }
                        $maxVisibleBars = 2;
                        $collapsedHeight = 20 + ($maxVisibleBars * 24) + (count($cellBarsArr) > $maxVisibleBars ? 36 : 0);
                        $expandedHeight = 20 + ($maxBarsInRow * 24) + 36;
                    @endphp
                    <div class="grid grid-cols-8 min-h-[80px] border-b last:border-b-0 relative w-full table-fixed">
                        <div class="flex items-center justify-center bg-indigo-300 dark:bg-indigo-800 border-r border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-900 dark:text-gray-100">
                            {{ $week[0]->format('W') }}
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
                                class="relative min-h-[80px] border-r border-b border-gray-200 dark:border-gray-700 last:border-r-0 bg-white dark:bg-gray-900 group transition-all duration-200 px-2 w-full"
                                id="{{ $cellId }}_container"
                                data-row="{{ $rowId }}"
                                data-expanded="false"
                                style="height: {{ $collapsedHeight }}px;"
                            >
                                <div class="absolute top-1 left-1 text-xs font-semibold {{ $day->isToday() ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-200' }}">
                                    {{ $day->format('j') }}
                                </div>
                                <div id="{{ $cellId }}_bars" class="pb-8">
                                    @foreach($cellBars as $barIdx => $bar)
                                        @php
                                            $wo = $bar['wo'];
                                            $stackIdx = $bar['stackIdx'];
                                            $barTop = 20 + $stackIdx * 24;
                                            $isHidden = $barIdx >= $maxVisibleBars;
                                        @endphp
                                        <a href="#"
                                           class="absolute left-4 right-4 h-5 rounded flex items-center shadow hover:bg-blue-700 dark:hover:bg-blue-800 transition group"
                                           style="background: #3b82f6; top: {{ $barTop }}px; z-index: 10; text-decoration: none; {{ $isHidden ? 'display:none;' : '' }}"
                                           data-bar="{{ $cellId }}_bar_{{ $barIdx }}"
                                           title="{{ $wo['unique_id'] }}">
                                            <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: 20px;">
                                                {{ $wo['unique_id'] }}
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                                @if(count($hiddenBars) > 0)
                                    <button 
                                        id="{{ $cellId }}_expand"
                                        class="right-2 bottom-2 bg-blue-500 dark:bg-blue-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 border border-blue-600 dark:border-blue-800 mb-2 text-white"
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
                                        <svg class="w-3 h-3 mr-1 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                        +{{ count($hiddenBars) }} more
                                    </button>
                                    <button
                                        id="{{ $cellId }}_collapse"
                                        class="right-2 bottom-2 bg-blue-500 dark:bg-blue-700 text-xs px-2 py-1 rounded flex items-center cursor-pointer z-50 border border-blue-600 dark:border-blue-800 mb-2 text-white"
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
                                        <svg class="w-3 h-3 mr-1 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                        </svg>
                                        Collapse
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>