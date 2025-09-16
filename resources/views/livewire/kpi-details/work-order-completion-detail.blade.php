{{-- Work Order Completion Rate Detail View --}}
<div class="space-y-6">
    {{-- Hero Section --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- Main Metric --}}
        <div class="md:col-span-2 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-blue-100">Current Completion Rate</h3>
                    <div class="mt-2 flex items-baseline">
                        <span class="text-4xl font-bold">{{ $kpis['work_order_completion_rate']['rate'] ?? '0' }}%</span>
                        @if(isset($kpis['work_order_completion_rate']['trend']) && $kpis['work_order_completion_rate']['trend'] != 0)
                            <span class="ml-2 text-lg {{ $kpis['work_order_completion_rate']['trend'] > 0 ? 'text-green-200' : 'text-red-200' }}">
                                {{ $kpis['work_order_completion_rate']['trend'] > 0 ? '↗' : '↘' }} {{ abs($kpis['work_order_completion_rate']['trend']) }}%
                            </span>
                        @endif
                    </div>
                </div>
                <div class="text-blue-200">
                    <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Completed Orders --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed Orders</h4>
            <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {{ $kpis['work_order_completion_rate']['completed_orders'] ?? '0' }}
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total completed</p>
        </div>

        {{-- Total Orders --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Orders</h4>
            <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {{ $kpis['work_order_completion_rate']['total_orders'] ?? '0' }}
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">In selected period</p>
        </div>
    </div>

    {{-- Performance Breakdown --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        {{-- Work Order Status Distribution --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/20 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Work Order Status</dt>
                    </dl>
                </div>
            </div>
            <div class="space-y-2">
                @if(isset($kpis['work_order_completion_rate']['status_distribution']))
                    @php
                        $statusIcons = [
                            'Assigned' => '📋',
                            'Start' => '▶️',
                            'Hold' => '⏸️',
                            'Completed' => '✅',
                            'Closed' => '🔒'
                        ];
                    @endphp

                    @foreach(['Assigned', 'Start', 'Hold', 'Completed', 'Closed'] as $status)
                        @php
                            $statusData = $kpis['work_order_completion_rate']['status_distribution'][$status] ?? ['percentage' => 0, 'count' => 0];
                        @endphp
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center">
                                <span class="text-sm mr-1">{{ $statusIcons[$status] ?? '📋' }}</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ $status }}:</span>
                            </div>
                            <div class="font-medium text-gray-900 dark:text-white">
                                {{ $statusData['percentage'] }}% ({{ $statusData['count'] }})
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-xs text-gray-500 dark:text-gray-400">Status distribution not available</div>
                @endif
            </div>
        </div>

        {{-- Overall Scrap Rate --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-100 dark:bg-red-900/20 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Overall Scrap Rate</dt>
                    </dl>
                </div>
            </div>
            @if(isset($kpis['work_order_scrapped_qty']))
                <div class="text-center mb-3">
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400 mb-1">
                        {{ number_format($kpis['work_order_scrapped_qty']['rate'], 1) }}%
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        {{ number_format($kpis['work_order_scrapped_qty']['scrapped_qty']) }} / {{ number_format($kpis['work_order_scrapped_qty']['total_qty']) }} units
                    </div>
                </div>

                {{-- Breakdown by Status --}}
                @if(isset($kpis['work_order_scrapped_qty']['by_status']))
                    <div class="space-y-2">
                        @php
                            $statusIcons = [
                                'completed' => '✓',
                                'hold' => '⏸',
                                'closed' => '➤'
                            ];
                            $statusLabels = [
                                'completed' => 'Completed',
                                'hold' => 'Hold',
                                'closed' => 'Closed'
                            ];
                        @endphp

                        @foreach(['completed', 'hold', 'closed'] as $status)
                            @php
                                $statusData = $kpis['work_order_scrapped_qty']['by_status'][$status] ?? ['rate' => 0, 'scrapped_qty' => 0, 'total_qty' => 0];
                            @endphp
                            @if($statusData['total_qty'] > 0)
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center">
                                        <span class="text-sm mr-1">{{ $statusIcons[$status] ?? '•' }}</span>
                                        <span class="text-gray-700 dark:text-gray-300">{{ $statusLabels[$status] }}:</span>
                                    </div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ number_format($statusData['rate'], 1) }}% ({{ number_format($statusData['scrapped_qty']) }}/{{ number_format($statusData['total_qty']) }})
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            @else
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-400 dark:text-gray-500 mb-1">--</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Data not available</div>
                </div>
            @endif
        </div>

        {{-- Production Throughput --}}
        @if(isset($kpis['quality_rate']))
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 dark:bg-green-900/20 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Production Throughput</dt>
                        </dl>
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                        {{ number_format($kpis['quality_rate']['rate'], 1) }} <span class="text-sm font-normal">units/hr</span>
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        {{ number_format($kpis['quality_rate']['total_units']) }} units in {{ $kpis['quality_rate']['total_hours'] }}hrs
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center mb-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Production Throughput</dt>
                        </dl>
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-medium text-gray-400 dark:text-gray-500">Coming Soon</div>
                </div>
            </div>
        @endif

        {{-- On-Time Delivery --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900/20 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">On-Time Delivery</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white">Coming Soon</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts and Trends --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        {{-- Completion Trends Chart --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Completion Rate Trends</h3>
            <div class="h-64 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Interactive charts coming soon</p>
                </div>
            </div>
        </div>

        {{-- Resource Performance --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Resource Performance</h3>
            <div class="h-64 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Machine & operator analysis coming soon</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Action Items --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Recommended Actions</h3>
        <div class="space-y-3">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-2 h-2 bg-yellow-400 rounded-full mt-2"></div>
                </div>
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium">Monitor overdue orders:</span>
                        Focus on work orders approaching or past due dates to improve completion rate.
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-2 h-2 bg-blue-400 rounded-full mt-2"></div>
                </div>
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium">Resource optimization:</span>
                        Analyze machine and operator utilization to identify bottlenecks.
                    </p>
                </div>
            </div>
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-2 h-2 bg-green-400 rounded-full mt-2"></div>
                </div>
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium">Quality improvement:</span>
                        Review work orders with quality issues to prevent rework and delays.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>