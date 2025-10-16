<x-filament-panels::page>
    @php
        $data = $this->getProductionScheduleData();
        $summary = $data['summary'];
        $completed = $data['completed'];
        $atRisk = $data['at_risk'];
    @endphp

    {{-- Page Header with Refresh --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold">Production Schedule Adherence</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Track on-time completion rates and identify at-risk work orders
            </p>
        </div>
        <div class="flex gap-3">
            <button
                wire:click="refreshData"
                class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </button>
        </div>
    </div>

    {{-- Summary Metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        {{-- Scheduled Today --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Scheduled Today</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ $summary['scheduled_today'] }}</p>
                </div>
                <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- On-Time Rate --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">On-Time Rate</p>
                    <p class="text-3xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $summary['on_time_rate'] }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $summary['on_time_count'] }} / {{ $summary['scheduled_today'] }} completed on time
                    </p>
                </div>
                <div class="bg-green-100 dark:bg-green-900 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Average Delay --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Delay (Late WOs)</p>
                    <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-2">{{ $summary['avg_delay_minutes'] }} min</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $summary['late_count'] }} work orders late
                    </p>
                </div>
                <div class="bg-red-100 dark:bg-red-900 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Completion Status Breakdown --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-green-800 dark:text-green-300">On Time</p>
                    <p class="text-2xl font-bold text-green-900 dark:text-green-200 mt-1">{{ $summary['on_time_count'] }}</p>
                </div>
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-300">Early</p>
                    <p class="text-2xl font-bold text-blue-900 dark:text-blue-200 mt-1">{{ $summary['early_count'] }}</p>
                </div>
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>

        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-red-800 dark:text-red-300">Late</p>
                    <p class="text-2xl font-bold text-red-900 dark:text-red-200 mt-1">{{ $summary['late_count'] }}</p>
                </div>
                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>
    </div>

    {{-- SECTION 1: COMPLETED TODAY --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-8 border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Completed Today (Scheduled to End Today)
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Work orders that were scheduled to end today and their actual completion timing
            </p>
        </div>

        {{-- On Time --}}
        @php $onTimePaginated = $this->getPaginatedCompleted($completed['on_time'], 'on_time'); @endphp
        <div class="border-b border-gray-200 dark:border-gray-700">
            <button
                wire:click="toggleCompletedSection('on_time')"
                class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="bg-green-100 dark:bg-green-900 p-2 rounded">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">On Time</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Within ±15 minutes of scheduled end time</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ count($completed['on_time']) }}</span>
                    <svg class="w-5 h-5 text-gray-400 transition-transform {{ $onTimeExpanded ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>

            @if($onTimeExpanded && count($completed['on_time']) > 0)
                <div class="px-6 pb-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">WO Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Machine</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Part Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Operator</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Scheduled End</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Actual Completion</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Variance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($onTimePaginated['data'] as $wo)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">{{ $wo['wo_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $wo['machine_name'] }}<br>
                                            <span class="text-xs text-gray-500">{{ $wo['machine_asset_id'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['part_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['operator'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['scheduled_end'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['actual_completion'] }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                {{ $wo['variance_display'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($onTimePaginated['total_pages'] > 1)
                        <x-machine-table-pagination
                            :currentPage="$onTimePaginated['current_page']"
                            :totalPages="$onTimePaginated['total_pages']"
                            :total="$onTimePaginated['total']"
                            :from="$onTimePaginated['from']"
                            :to="$onTimePaginated['to']"
                            status="on_time"
                            wireMethod="gotoCompletedPage"
                        />
                    @endif
                </div>
            @endif
        </div>

        {{-- Early --}}
        @php $earlyPaginated = $this->getPaginatedCompleted($completed['early'], 'early'); @endphp
        <div class="border-b border-gray-200 dark:border-gray-700">
            <button
                wire:click="toggleCompletedSection('early')"
                class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="bg-blue-100 dark:bg-blue-900 p-2 rounded">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Early</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Completed more than 15 minutes early</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ count($completed['early']) }}</span>
                    <svg class="w-5 h-5 text-gray-400 transition-transform {{ $earlyExpanded ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>

            @if($earlyExpanded && count($completed['early']) > 0)
                <div class="px-6 pb-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">WO Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Machine</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Part Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Operator</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Scheduled End</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Actual Completion</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Variance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($earlyPaginated['data'] as $wo)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">{{ $wo['wo_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $wo['machine_name'] }}<br>
                                            <span class="text-xs text-gray-500">{{ $wo['machine_asset_id'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['part_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['operator'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['scheduled_end'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['actual_completion'] }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                {{ $wo['variance_display'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($earlyPaginated['total_pages'] > 1)
                        <x-machine-table-pagination
                            :currentPage="$earlyPaginated['current_page']"
                            :totalPages="$earlyPaginated['total_pages']"
                            :total="$earlyPaginated['total']"
                            :from="$earlyPaginated['from']"
                            :to="$earlyPaginated['to']"
                            status="early"
                            wireMethod="gotoCompletedPage"
                        />
                    @endif
                </div>
            @endif
        </div>

        {{-- Late --}}
        @php $latePaginated = $this->getPaginatedCompleted($completed['late'], 'late'); @endphp
        <div>
            <button
                wire:click="toggleCompletedSection('late')"
                class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="bg-red-100 dark:bg-red-900 p-2 rounded">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Late</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Completed more than 15 minutes late</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ count($completed['late']) }}</span>
                    <svg class="w-5 h-5 text-gray-400 transition-transform {{ $lateExpanded ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>

            @if($lateExpanded && count($completed['late']) > 0)
                <div class="px-6 pb-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">WO Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Machine</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Part Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Operator</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Scheduled End</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Actual Completion</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Variance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($latePaginated['data'] as $wo)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">{{ $wo['wo_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $wo['machine_name'] }}<br>
                                            <span class="text-xs text-gray-500">{{ $wo['machine_asset_id'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['part_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['operator'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['scheduled_end'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['actual_completion'] }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                {{ $wo['variance_display'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($latePaginated['total_pages'] > 1)
                        <x-machine-table-pagination
                            :currentPage="$latePaginated['current_page']"
                            :totalPages="$latePaginated['total_pages']"
                            :total="$latePaginated['total']"
                            :from="$latePaginated['from']"
                            :to="$latePaginated['to']"
                            status="late"
                            wireMethod="gotoCompletedPage"
                        />
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- SECTION 2: AT-RISK WORK ORDERS --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                At-Risk Work Orders
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Currently running work orders with approaching deadlines (within 8 hours)
            </p>
        </div>

        {{-- High Risk --}}
        @php $highRiskPaginated = $this->getPaginatedAtRisk($atRisk['high_risk'], 'high_risk'); @endphp
        <div class="border-b border-gray-200 dark:border-gray-700">
            <button
                wire:click="toggleAtRiskSection('high_risk')"
                class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="bg-red-100 dark:bg-red-900 p-2 rounded">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">High Risk</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Less than 2 hours remaining, progress below 70%</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ count($atRisk['high_risk']) }}</span>
                    <svg class="w-5 h-5 text-gray-400 transition-transform {{ $highRiskExpanded ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>

            @if($highRiskExpanded && count($atRisk['high_risk']) > 0)
                <div class="px-6 pb-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">WO Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Machine</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Part Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Operator</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Scheduled End</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Hours Remaining</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Progress</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Qty Produced</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($highRiskPaginated['data'] as $wo)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">{{ $wo['wo_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $wo['machine_name'] }}<br>
                                            <span class="text-xs text-gray-500">{{ $wo['machine_asset_id'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['part_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['operator'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['scheduled_end'] }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                {{ number_format($wo['hours_remaining'], 1) }}h
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                                    <div class="bg-red-600 dark:bg-red-500 h-2 rounded-full" style="width: {{ $wo['progress_pct'] }}%"></div>
                                                </div>
                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $wo['progress_pct'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $wo['qty_produced'] }} / {{ $wo['qty_target'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($highRiskPaginated['total_pages'] > 1)
                        <x-machine-table-pagination
                            :currentPage="$highRiskPaginated['current_page']"
                            :totalPages="$highRiskPaginated['total_pages']"
                            :total="$highRiskPaginated['total']"
                            :from="$highRiskPaginated['from']"
                            :to="$highRiskPaginated['to']"
                            status="high_risk"
                            wireMethod="gotoAtRiskPage"
                        />
                    @endif
                </div>
            @endif
        </div>

        {{-- Medium Risk --}}
        @php $mediumRiskPaginated = $this->getPaginatedAtRisk($atRisk['medium_risk'], 'medium_risk'); @endphp
        <div class="border-b border-gray-200 dark:border-gray-700">
            <button
                wire:click="toggleAtRiskSection('medium_risk')"
                class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="bg-yellow-100 dark:bg-yellow-900 p-2 rounded">
                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Medium Risk</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Less than 4 hours remaining, progress below 80%</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-yellow-600 dark:text-yellow-400">{{ count($atRisk['medium_risk']) }}</span>
                    <svg class="w-5 h-5 text-gray-400 transition-transform {{ $mediumRiskExpanded ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>

            @if($mediumRiskExpanded && count($atRisk['medium_risk']) > 0)
                <div class="px-6 pb-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">WO Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Machine</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Part Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Operator</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Scheduled End</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Hours Remaining</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Progress</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Qty Produced</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($mediumRiskPaginated['data'] as $wo)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">{{ $wo['wo_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $wo['machine_name'] }}<br>
                                            <span class="text-xs text-gray-500">{{ $wo['machine_asset_id'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['part_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['operator'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['scheduled_end'] }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                                {{ number_format($wo['hours_remaining'], 1) }}h
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                                    <div class="bg-yellow-600 dark:bg-yellow-500 h-2 rounded-full" style="width: {{ $wo['progress_pct'] }}%"></div>
                                                </div>
                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $wo['progress_pct'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $wo['qty_produced'] }} / {{ $wo['qty_target'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($mediumRiskPaginated['total_pages'] > 1)
                        <x-machine-table-pagination
                            :currentPage="$mediumRiskPaginated['current_page']"
                            :totalPages="$mediumRiskPaginated['total_pages']"
                            :total="$mediumRiskPaginated['total']"
                            :from="$mediumRiskPaginated['from']"
                            :to="$mediumRiskPaginated['to']"
                            status="medium_risk"
                            wireMethod="gotoAtRiskPage"
                        />
                    @endif
                </div>
            @endif
        </div>

        {{-- On Track --}}
        @php $onTrackPaginated = $this->getPaginatedAtRisk($atRisk['on_track'], 'on_track'); @endphp
        <div>
            <button
                wire:click="toggleAtRiskSection('on_track')"
                class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="bg-green-100 dark:bg-green-900 p-2 rounded">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">On Track</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Adequate time remaining or high progress</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ count($atRisk['on_track']) }}</span>
                    <svg class="w-5 h-5 text-gray-400 transition-transform {{ $onTrackExpanded ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>

            @if($onTrackExpanded && count($atRisk['on_track']) > 0)
                <div class="px-6 pb-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">WO Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Machine</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Part Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Operator</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Scheduled End</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Hours Remaining</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Progress</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase">Qty Produced</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($onTrackPaginated['data'] as $wo)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">{{ $wo['wo_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $wo['machine_name'] }}<br>
                                            <span class="text-xs text-gray-500">{{ $wo['machine_asset_id'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['part_number'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['operator'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $wo['scheduled_end'] }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                {{ number_format($wo['hours_remaining'], 1) }}h
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                                    <div class="bg-green-600 dark:bg-green-500 h-2 rounded-full" style="width: {{ $wo['progress_pct'] }}%"></div>
                                                </div>
                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $wo['progress_pct'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ $wo['qty_produced'] }} / {{ $wo['qty_target'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($onTrackPaginated['total_pages'] > 1)
                        <x-machine-table-pagination
                            :currentPage="$onTrackPaginated['current_page']"
                            :totalPages="$onTrackPaginated['total_pages']"
                            :total="$onTrackPaginated['total']"
                            :from="$onTrackPaginated['from']"
                            :to="$onTrackPaginated['to']"
                            status="on_track"
                            wireMethod="gotoAtRiskPage"
                        />
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Last Updated --}}
    <div class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
        Last updated: {{ \Carbon\Carbon::parse($data['updated_at'])->format('M d, Y H:i:s') }}
    </div>
</x-filament-panels::page>
