<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles 

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.1/dist/tailwind.min.css" rel="stylesheet">
    @endif

    </head>
    <body>
<div class="flex justify-center items-center min-h-screen bg-gray-100">
    <div class="w-full max-w-4xl bg-white rounded-lg shadow-lg p-6">
        {{-- Header Section --}}
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Advanced Work Order Gantt Chart</h1>

            {{-- Dropdown and Date Picker --}}
            <div class="flex items-center space-x-4">
                <select id="timeRangeSelector" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring focus:ring-blue-200">
                    <option value="week" {{ $timeRange === 'week' ? 'selected' : '' }}>Week</option>
                    <option value="day" {{ $timeRange === 'day' ? 'selected' : '' }}>Day</option>
                    <option value="month" {{ $timeRange === 'month' ? 'selected' : '' }}>Month</option>
                </select>
                <input 
                    type="{{ $timeRange === 'month' ? 'month' : ($timeRange === 'day' ? 'date' : 'week') }}" 
                    id="datePicker" 
                    class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring focus:ring-blue-200" 
                    value="{{ $timeRange === 'week' ? \Carbon\Carbon::parse($selectedDate)->format('Y-\WW') : $selectedDate }}" />
            </div>
        </div>

        {{-- Debugging Logs --}}
        <div class="mb-4 text-gray-600">
            <p class="text-sm">Time Range: <span class="font-medium">{{ $timeRange }}</span></p>
            <p class="text-sm">Selected Date: <span class="font-medium">{{ $selectedDate }}</span></p>
        </div>

        {{-- Gantt Chart Table --}}
        <div class="overflow-x-auto">
          {{-- filepath: resources/views/filament/admin/widgets/advanced-work-order-gantt.blade.php --}}
@php
use Illuminate\Support\Carbon;

// Timeline range based on filtered $workOrders
$defaultStartDate = now()->subMonth()->startOfWeek();
$defaultEndDate = now()->endOfWeek();

$startDate = collect($workOrders)->min('start_time')
    ? Carbon::parse(collect($workOrders)->min('start_time'))->startOfWeek()
    : $defaultStartDate;

$endDate = collect($workOrders)->max('end_time')
    ? Carbon::parse(collect($workOrders)->max('end_time'))->endOfWeek()
    : $defaultEndDate;

// Build columns based on $timeRange
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
    } else { // week
        $columns->push([
            'start' => $currentDate->format('Y-m-d'),
            'end' => $currentDate->copy()->endOfWeek()->format('Y-m-d'),
            'label' => $currentDate->format('M d') . ' - ' . $currentDate->copy()->endOfWeek()->format('M d'),
        ]);
        $currentDate->addWeek();
    }
    $colCount++;
}

$workOrderColumnWidth = 350;
$colWidth = 150;
$tableWidth = $workOrderColumnWidth + (count($columns) * $colWidth);
@endphp

<div class="overflow-x-auto w-[1200px]">
<div class="w-full" style="min-width: {{ $tableWidth }}px;">
    <div class="p-4 bg-white rounded-lg shadow">
        <div class="mb-4">
            <h3 class="text-lg font-medium text-gray-900">Work Order Timeline</h3>
            <p class="text-sm text-gray-500">Filtered by {{ ucfirst($timeRange) }}</p>
        </div>
        <div class="border rounded-lg overflow-hidden">
            <table class="w-full table-fixed divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="sticky left-0 z-20 bg-gray-50 border-r" style="width: {{ $workOrderColumnWidth }}px">
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

                            $actualStart = $workOrder->actual_start_date ?? null;
                            $actualEnd = $workOrder->actual_end_date ?? null;
                            $actualStart = $actualStart ? \Carbon\Carbon::parse($actualStart) : null;
                            $actualEnd = $actualEnd ? \Carbon\Carbon::parse($actualEnd) : null;

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

                            // Planned bar
                            $plannedStartIndex = $columns->search(fn($col) => $woStart->between($col['start'], $col['end']));
                            $plannedEndIndex = $columns->search(fn($col) => $woEnd->between($col['start'], $col['end']));
                            $plannedWidth = ($plannedEndIndex !== false && $plannedStartIndex !== false)
                                ? max(($plannedEndIndex - $plannedStartIndex + 1) * 100 / $totalCols, 1)
                                : 0;
                            $plannedLeft = ($plannedStartIndex !== false)
                                ? $plannedStartIndex * 100 / $totalCols
                                : 0;
                        @endphp
                        <tr>
                            <td class="sticky left-0 z-20 bg-white border-r" style="width: {{ $workOrderColumnWidth }}px">
                                <div class="px-4 py-4">
                                    <div class="text-xs font-medium text-gray-900 truncate max-w-[310px]">
                                        @php
                                        $factoryId = auth()->user()?->factory_id ?? 'default-factory';
                                    @endphp
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
                                    @if ($workOrder->status === 'Start')
                                        <div class="absolute top-[60%] mx-2" style="left: {{ $actualBarLeft }}%;">
                                            <span class="flex items-center justify-center text-xs font-medium"  style="color: {{ config('work_order_status.start') }}">
                                                <i class="fas fa-flag fa-xl"></i>
                                            </span>
                                        </div>
                                    @elseif ($workOrder->status !== 'Assigned')
                                        <div class="absolute h-4 bg-gray-200 rounded top-[60%] mx-2"
                                             style="width: calc({{ $actualBarWidth }}% - 16px); left: {{ $actualBarLeft }}%; display: flex;">
                                            <div class="h-full bg-green-500 rounded-l" style="width: {{ $okPercentage }}%;">
                                                <span class="flex items-center justify-center text-xs text-white font-medium h-full">
                                                    {{ round($okPercentage, 1) }}%
                                                </span>
                                            </div>
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
        </div>
        <div class="mt-6">
            <a href="{{ url('admin/' . (auth()->user()?->factory_id ?? 3) . '/work-order-widgets') }}"
               class="inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                ← Back to Previous Page
            </a>
        </div>
</div>
</div>  
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const timeRangeSelector = document.getElementById('timeRangeSelector');
            const datePicker = document.getElementById('datePicker');

            // Persist the selected values in the dropdown and date picker
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

            // Update the date picker type when the time range changes
            timeRangeSelector.addEventListener('change', function () {
                const selectedValue = this.value;

                if (selectedValue === 'week') {
                    datePicker.type = 'week';
                    datePicker.value = ''; // Reset the date picker value
                } else if (selectedValue === 'day') {
                    datePicker.type = 'date';
                    datePicker.value = ''; // Reset the date picker value
                } else if (selectedValue === 'month') {
                    datePicker.type = 'month';
                    datePicker.value = ''; // Reset the date picker value
                }
            });

            // Update the URL when the date picker value changes
            datePicker.addEventListener('change', function () {
                const timeRange = timeRangeSelector.value;
                const selectedDate = this.value;

                // Reload the page with the selected filters
                window.location.href = `?timeRange=${timeRange}&selectedDate=${selectedDate}`;
            });
        });
    </script>
</div>
    </body>
</html>
