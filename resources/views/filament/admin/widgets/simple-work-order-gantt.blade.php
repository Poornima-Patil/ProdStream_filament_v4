<div>
    <h1>Work Order Gantt Chart</h1>

    @php
        use Illuminate\Support\Carbon;

        // Ensure $workOrders is not empty before calculating dates
        $startDate = collect($workOrders->items())->min('start_date') ?? now()->startOfWeek();
        $endDate = collect($workOrders->items())->max('end_date') ?? now()->endOfWeek();

        $weeks = collect([]);
        $currentDate = Carbon::parse($startDate)->startOfWeek();
        $endWeek = Carbon::parse($endDate)->endOfWeek();

        while ($currentDate <= $endWeek) {
            $weeks->push([
                'start' => $currentDate->format('Y-m-d'),
                'end' => $currentDate->copy()->endOfWeek()->format('Y-m-d'),
                'label' => $currentDate->format('M d') . ' - ' . $currentDate->copy()->endOfWeek()->format('M d'),
            ]);
            $currentDate->addWeek();
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
                                    $actualStart = $workOrder['actual_start_date'] ? Carbon::parse($workOrder['actual_start_date']) : null;
                                    $actualEnd = $workOrder['actual_end_date'] ? Carbon::parse($workOrder['actual_end_date']) : null;

                                    $totalWeeks = count($weeks);

                                    // Calculate planned bar position and width
                                    $plannedStartIndex = $weeks->search(fn($week) => $woStart->between($week['start'], $week['end']));
                                    $plannedEndIndex = $weeks->search(fn($week) => $woEnd->between($week['start'], $week['end']));
                                    $plannedWidth = max(($plannedEndIndex - $plannedStartIndex + 1) * 100 / $totalWeeks, 1);
                                    $plannedLeft = $plannedStartIndex * 100 / $totalWeeks;

                                    // Calculate actual bar position and width
                                    $actualStartIndex = $actualStart ? $weeks->search(fn($week) => $actualStart->between($week['start'], $week['end'])) : null;
                                    $actualEndIndex = $actualEnd ? $weeks->search(fn($week) => $actualEnd->between($week['start'], $week['end'])) : null;
                                    $actualWidth = $actualStart && $actualEnd ? max(($actualEndIndex - $actualStartIndex + 1) * 100 / $totalWeeks, 1) : 0;
                                    $actualLeft = $actualStartIndex ? $actualStartIndex * 100 / $totalWeeks : 0;
                                @endphp
                                <tr>
                                    <td class="sticky left-0 z-20 bg-white border-r" style="width: {{ $workOrderColumnWidth }}px">
                                        <div class="px-4 py-4">
                                            <div class="text-xs font-medium text-gray-900 truncate max-w-[310px]">{{ $workOrder['unique_id'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $workOrder['status'] }}</div>
                                        </div>
                                    </td>
                                    <td colspan="{{ count($weeks) }}" class="relative p-0" style="height: 64px;">
                                        <div class="absolute inset-0">
                                            {{-- Planned Bar --}}
                                            <div class="absolute h-4 bg-blue-500 rounded top-[20%] mx-2"
                                                 style="width: calc({{ $plannedWidth }}% - 16px); left: {{ $plannedLeft }}%;">
                                                <div class="flex items-center justify-center h-full">
                                                    <span class="text-xs text-white font-medium px-2 truncate">
                                                        Planned
                                                    </span>
                                                </div>
                                            </div>

                                            {{-- Actual Bar --}}
                                            @if ($actualWidth > 0)
                                                <div class="absolute h-4 bg-green-500 rounded top-[60%] mx-2"
                                                     style="width: calc({{ $actualWidth }}% - 16px); left: {{ $actualLeft }}%;">
                                                    <div class="flex items-center justify-center h-full">
                                                        <span class="text-xs text-white font-medium px-2 truncate">
                                                            Actual
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
