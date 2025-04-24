<div>
    <h1>Work Order Gantt Chart</h1>

    @php
        use Illuminate\Support\Carbon;

        // Define a broader range if $workOrders is empty
        $defaultStartDate = now()->subYear()->startOfWeek(); // Start of the past year
        $defaultEndDate = now()->endOfWeek(); // End of the current week

        // Calculate start and end dates based on $workOrders
        $startDate = collect($workOrders->items())->min('start_date') 
            ? Carbon::parse(collect($workOrders->items())->min('start_date'))->startOfWeek()
            : $defaultStartDate;

        $endDate = collect($workOrders->items())->max('end_date') 
            ? Carbon::parse(collect($workOrders->items())->max('end_date'))->endOfWeek()
            : $defaultEndDate;

        // Ensure the timeline includes today
        if (now()->greaterThan($endDate)) {
            $endDate = now()->endOfWeek();
        }

        $maxWeeks = 52; // Limit to 1 year of weeks
        $weeks = collect([]);
        $currentDate = $startDate;
        $endWeek = $endDate;
        $weekCount = 0;

        while ($currentDate <= $endWeek && $weekCount < $maxWeeks) {
            $weeks->push([
                'start' => $currentDate->format('Y-m-d'),
                'end' => $currentDate->copy()->endOfWeek()->format('Y-m-d'),
                'label' => $currentDate->format('M d') . ' - ' . $currentDate->copy()->endOfWeek()->format('M d'),
            ]);
            $currentDate->addWeek();
            $weekCount++;
        }

        $workOrderColumnWidth = 350;
        $weekColumnWidth = 150; // Adjust the width of each week column
        $tableWidth = $workOrderColumnWidth + (count($weeks) * $weekColumnWidth);
    @endphp

    <div class="overflow-x-auto w-[1200px]" >
        <div class="w-full" style="min-width: {{ $tableWidth }}px;">
            <div class="p-4 bg-white rounded-lg shadow">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Work Order Timeline</h3>
                    <p class="text-sm text-gray-500">Showing work orders by week</p>
                </div>

                <div class="border rounded-lg overflow-hidden">
                    <table class="w-full table-fixed divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th scope="col" class="sticky left-0 z-20 bg-gray-50 border-r" style="width: {{ $workOrderColumnWidth }}px">
                                    <div class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Work Order
                                    </div>
                                </th>
                                @foreach($weeks as $week)
                                    <th scope="col" class="border-r" style="width: {{ $weekColumnWidth }}px">
                                        <div class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ $week['label'] }}
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($workOrders as $workOrder)
                                @php
                                    $woStart = Carbon::parse($workOrder['start_date']);
                                    $woEnd = Carbon::parse($workOrder['end_date']);
                                    $totalQty = $workOrder['qty'];
                                    $okQtys = $workOrder['ok_qtys'];
                                    $scrappedQtys = $workOrder['scrapped_qtys'];

                                    $actualStart = $workOrder['actual_start_date'] ? Carbon::parse($workOrder['actual_start_date']) : null;
                                    $actualEnd = $workOrder['actual_end_date'] ? Carbon::parse($workOrder['actual_end_date']) : null;

                                    // Calculate the total timeline in days
                                    $timelineStart = Carbon::parse($weeks->first()['start']);
                                    $timelineEnd = Carbon::parse($weeks->last()['end']);
                                    $totalTimelineDays = $timelineStart->diffInDays($timelineEnd);

                                    // Calculate the actual bar position and width
                                    $actualStartOffset = $actualStart ? $timelineStart->diffInDays($actualStart) : 0;
                                    $actualEndOffset = $actualEnd ? $timelineStart->diffInDays($actualEnd) : $totalTimelineDays;

                                    $actualBarLeft = ($actualStartOffset / $totalTimelineDays) * 100;
                                    $actualBarWidth = (($actualEndOffset - $actualStartOffset) / $totalTimelineDays) * 100;

                                    $okPercentage = $totalQty > 0 ? ($okQtys / $totalQty) * 100 : 0;
                                    $scrappedPercentage = $totalQty > 0 ? ($scrappedQtys / $totalQty) * 100 : 0;

                                    $totalWeeks = count($weeks);

                                    // Calculate planned bar position and width
                                    $plannedStartIndex = $weeks->search(fn($week) => $woStart->between($week['start'], $week['end']));
                                    $plannedEndIndex = $weeks->search(fn($week) => $woEnd->between($week['start'], $week['end']));
                                    $plannedWidth = max(($plannedEndIndex - $plannedStartIndex + 1) * 100 / $totalWeeks, 1);
                                    $plannedLeft = $plannedStartIndex * 100 / $totalWeeks;

                                    Log::info("Work Order ID: {$workOrder['unique_id']}, Timeline Start: {$timelineStart}, Actual Start: {$actualStart}, Total Timeline Days: {$totalTimelineDays}, Actual Start Offset: {$actualStartOffset}, Actual Bar Left: {$actualBarLeft}");
                                @endphp
                                <tr>
                                    <td class="sticky left-0 z-20 bg-white border-r" style="width: {{ $workOrderColumnWidth }}px">
                                        <div class="px-4 py-4">
                                            <div class="text-xs font-medium text-gray-900 truncate max-w-[310px]">
                                                <a href="{{ url('admin/' . auth()->user()->factory_id . '/work-orders/' . $workOrder['id']) }}" 
                                                   class="text-blue-500 hover:underline">
                                                    {{ $workOrder['unique_id'] }}
                                                </a>
                                            </div>
                                            <div class="text-xs text-gray-500">{{ $workOrder['status'] }}</div>
                                        </div>
                                    </td>
                                    <td colspan="{{ count($weeks) }}" class="relative p-0" style="height: 64px;">
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
                                            @if ($workOrder['status'] === 'Start')
                                                {{-- Flag for Actual Start Date --}}
                                                <div class="absolute top-[60%] mx-2" style="left: {{ $actualBarLeft }}%;">
                                                    <span class="flex items-center justify-center text-xs  font-medium">
                                                      
                                                        <i class="fas fa-flag fa-xl"></i> {{-- Font Awesome flag icon --}}
                                                    </span>
                                                </div>
                                            @elseif ($workOrder['status'] !== 'Assigned')
                                                {{-- Actual Bar --}}
                                                <div class="absolute h-4 bg-gray-200 rounded top-[60%] mx-2"
                                                     style="width: calc({{ $actualBarWidth }}% - 16px); left: {{ $actualBarLeft }}%; display: flex;">
                                                    {{-- Green Section for OK Quantities --}}
                                                    <div class="h-full bg-green-500 rounded-l" style="width: {{ $okPercentage }}%;">
                                                        <span class="flex items-center justify-center text-xs text-white font-medium h-full">
                                                            {{ round($okPercentage, 1) }}%
                                                        </span>
                                                    </div>
                                                    {{-- Red Section for Scrapped Quantities --}}
                                                    <div class="h-full bg-red-500" style="width: {{ $scrappedPercentage }}%;">
                                                        <span class="flex items-center justify-center text-xs text-white font-medium h-full">
                                                            {{ round($scrappedPercentage, 1) }}%
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $workOrders->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
