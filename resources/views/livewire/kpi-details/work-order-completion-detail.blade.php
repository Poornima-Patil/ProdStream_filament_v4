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

    {{-- Related KPIs Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        {{-- Production Throughput KPI --}}
        @if(isset($kpis['quality_rate']))
            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-800/30 rounded-lg p-6 border border-green-200 dark:border-green-700">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-green-900 dark:text-green-100">Production Throughput</h4>
                    <div class="w-4 h-4 rounded-full {{ $this->getStatusColor($kpis['quality_rate']['status']) }}"></div>
                </div>
                <div class="text-3xl font-bold text-green-900 dark:text-green-100 mb-2">
                    {{ number_format($kpis['quality_rate']['rate'], 1) }}
                    <span class="text-lg font-normal">units/hr</span>
                </div>
                <p class="text-sm text-green-700 dark:text-green-300 mb-3">
                    {{ number_format($kpis['quality_rate']['total_units']) }} units in {{ $kpis['quality_rate']['total_hours'] }}hrs
                </p>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-green-600 dark:text-green-400">Target: 42/hr</span>
                    @if($kpis['quality_rate']['trend'] != 0)
                        <span class="font-medium {{ $this->getTrendColor($kpis['quality_rate']['trend']) }}">
                            {{ $this->getTrendIcon($kpis['quality_rate']['trend']) }} {{ abs($kpis['quality_rate']['trend']) }}
                        </span>
                    @endif
                </div>
            </div>
        @else
            <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800/50 dark:to-gray-700/50 rounded-lg p-6 border border-gray-200 dark:border-gray-600 opacity-60">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-400">Production Throughput</h4>
                    <div class="w-4 h-4 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                </div>
                <div class="text-3xl font-bold text-gray-500 dark:text-gray-400 mb-2">--</div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Data not available</p>
                <div class="text-sm text-gray-400 dark:text-gray-500">Coming Soon</div>
            </div>
        @endif

        {{-- Scrap Rate KPI --}}
        @if(isset($kpis['work_order_scrapped_qty']))
            <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/30 dark:to-red-800/30 rounded-lg p-6 border border-red-200 dark:border-red-700">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-red-900 dark:text-red-100">Scrap Rate</h4>
                    <div class="w-4 h-4 rounded-full {{ $this->getStatusColor($kpis['work_order_scrapped_qty']['status']) }}"></div>
                </div>
                <div class="text-3xl font-bold text-red-900 dark:text-red-100 mb-2">
                    {{ number_format($kpis['work_order_scrapped_qty']['rate'], 1) }}%
                </div>
                <p class="text-sm text-red-700 dark:text-red-300 mb-3">
                    {{ number_format($kpis['work_order_scrapped_qty']['scrapped_qty']) }} of {{ number_format($kpis['work_order_scrapped_qty']['total_qty']) }} units scrapped
                </p>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-red-600 dark:text-red-400">Target: < 3%</span>
                    @if($kpis['work_order_scrapped_qty']['trend'] != 0)
                        <span class="font-medium {{ $this->getTrendColor(-$kpis['work_order_scrapped_qty']['trend']) }}">
                            {{ $this->getTrendIcon(-$kpis['work_order_scrapped_qty']['trend']) }} {{ abs($kpis['work_order_scrapped_qty']['trend']) }}%
                        </span>
                    @endif
                </div>
            </div>
        @else
            <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800/50 dark:to-gray-700/50 rounded-lg p-6 border border-gray-200 dark:border-gray-600 opacity-60">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-400">Scrap Rate</h4>
                    <div class="w-4 h-4 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                </div>
                <div class="text-3xl font-bold text-gray-500 dark:text-gray-400 mb-2">--</div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Data not available</p>
                <div class="text-sm text-gray-400 dark:text-gray-500">Coming Soon</div>
            </div>
        @endif

        {{-- Machine Utilization KPI --}}
        @if(isset($kpis['machine_utilization']) && isset($kpis['machine_utilization']['rate']))
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/30 rounded-lg p-6 border border-purple-200 dark:border-purple-700">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-purple-900 dark:text-purple-100">Machine Utilization</h4>
                    <div class="w-4 h-4 rounded-full {{ $this->getStatusColor($kpis['machine_utilization']['status'] ?? 'warning') }}"></div>
                </div>
                <div class="text-3xl font-bold text-purple-900 dark:text-purple-100 mb-2">
                    {{ $kpis['machine_utilization']['rate'] }}%
                </div>
                <p class="text-sm text-purple-700 dark:text-purple-300 mb-3">
                    {{ $kpis['machine_utilization']['active_hours'] ?? 0 }}h of {{ $kpis['machine_utilization']['total_hours'] ?? 0 }}h utilized
                </p>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-purple-600 dark:text-purple-400">Target: 80%</span>
                    @if(isset($kpis['machine_utilization']['trend']) && $kpis['machine_utilization']['trend'] != 0)
                        <span class="font-medium {{ $this->getTrendColor($kpis['machine_utilization']['trend']) }}">
                            {{ $this->getTrendIcon($kpis['machine_utilization']['trend']) }} {{ abs($kpis['machine_utilization']['trend']) }}%
                        </span>
                    @endif
                </div>
            </div>
        @else
            <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800/50 dark:to-gray-700/50 rounded-lg p-6 border border-gray-200 dark:border-gray-600 opacity-60">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-400">Machine Utilization</h4>
                    <div class="w-4 h-4 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                </div>
                <div class="text-3xl font-bold text-gray-500 dark:text-gray-400 mb-2">--</div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Data not available</p>
                <div class="text-sm text-gray-400 dark:text-gray-500">Coming Soon</div>
            </div>
        @endif

        {{-- On-Time Delivery Rate KPI --}}
        @if(isset($kpis['on_time_delivery_rate']) && isset($kpis['on_time_delivery_rate']['rate']))
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/30 dark:to-orange-800/30 rounded-lg p-6 border border-orange-200 dark:border-orange-700">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-orange-900 dark:text-orange-100">On-Time Delivery</h4>
                    <div class="w-4 h-4 rounded-full {{ $this->getStatusColor($kpis['on_time_delivery_rate']['status'] ?? 'warning') }}"></div>
                </div>
                <div class="text-3xl font-bold text-orange-900 dark:text-orange-100 mb-2">
                    {{ $kpis['on_time_delivery_rate']['rate'] }}%
                </div>
                <p class="text-sm text-orange-700 dark:text-orange-300 mb-3">
                    {{ $kpis['on_time_delivery_rate']['on_time_orders'] ?? 0 }} of {{ $kpis['on_time_delivery_rate']['total_orders'] ?? 0 }} orders on time
                </p>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-orange-600 dark:text-orange-400">Target: 95%</span>
                    @if(isset($kpis['on_time_delivery_rate']['trend']) && $kpis['on_time_delivery_rate']['trend'] != 0)
                        <span class="font-medium {{ $this->getTrendColor($kpis['on_time_delivery_rate']['trend']) }}">
                            {{ $this->getTrendIcon($kpis['on_time_delivery_rate']['trend']) }} {{ abs($kpis['on_time_delivery_rate']['trend']) }}%
                        </span>
                    @endif
                </div>
            </div>
        @else
            <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800/50 dark:to-gray-700/50 rounded-lg p-6 border border-gray-200 dark:border-gray-600 opacity-60">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-400">On-Time Delivery</h4>
                    <div class="w-4 h-4 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                </div>
                <div class="text-3xl font-bold text-gray-500 dark:text-gray-400 mb-2">--</div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Data not available</p>
                <div class="text-sm text-gray-400 dark:text-gray-500">Coming Soon</div>
            </div>
        @endif

        {{-- Quality Score KPI --}}
        <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800/50 dark:to-gray-700/50 rounded-lg p-6 border border-gray-200 dark:border-gray-600 opacity-60">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-400">Quality Score</h4>
                <div class="w-4 h-4 rounded-full bg-gray-300 dark:bg-gray-600"></div>
            </div>
            <div class="text-3xl font-bold text-gray-500 dark:text-gray-400 mb-2">--</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Quality analysis not available</p>
            <div class="text-sm text-gray-400 dark:text-gray-500">Coming Soon</div>
        </div>

        {{-- Lead Time Analysis --}}
        <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800/50 dark:to-gray-700/50 rounded-lg p-6 border border-gray-200 dark:border-gray-600 opacity-60">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-400">Avg Lead Time</h4>
                <div class="w-4 h-4 rounded-full bg-gray-300 dark:bg-gray-600"></div>
            </div>
            <div class="text-3xl font-bold text-gray-500 dark:text-gray-400 mb-2">--</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Lead time analysis not available</p>
            <div class="text-sm text-gray-400 dark:text-gray-500">Coming Soon</div>
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