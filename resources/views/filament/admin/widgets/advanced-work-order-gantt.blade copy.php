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
            <table class="w-full table-auto border-collapse border border-gray-300">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border border-gray-300 px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Work Order
                        </th>
                        <th class="border border-gray-300 px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Start Date
                        </th>
                        <th class="border border-gray-300 px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            End Date
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($workOrders as $workOrder)
                        <tr>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-900">
                                {{ $workOrder->unique_id }}
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-500">
                                {{ \Illuminate\Support\Carbon::parse($workOrder->start_time)->format('Y-m-d') }}
                            </td>
                            <td class="border border-gray-300 px-4 py-2 text-sm text-gray-500">
                                {{ \Illuminate\Support\Carbon::parse($workOrder->end_time)->format('Y-m-d') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
