{{-- Production Throughput (Hourly) KPI Detail View --}}
<div class="space-y-6">
    {{-- Hero Section with Key Metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- Current Throughput --}}
        <div class="bg-gradient-to-r from-green-500 to-green-600 dark:from-green-600 dark:to-green-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Production Throughput</p>
                    <p class="text-3xl font-bold mt-1">{{ number_format($kpis['quality_rate']['rate'], 2) }}</p>
                    <p class="text-green-100 text-sm">units/hour</p>
                </div>
                <div class="bg-green-400 dark:bg-green-500 rounded-full p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
            </div>
            @if($kpis['quality_rate']['trend'] != 0)
                <div class="mt-3 flex items-center">
                    <span class="text-green-100 text-xs">
                        {{ $kpis['quality_rate']['trend'] > 0 ? '↗' : '↘' }} 
                        {{ abs($kpis['quality_rate']['trend']) }} from previous period
                    </span>
                </div>
            @endif
        </div>

        {{-- Total Units Produced --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total Units</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($kpis['quality_rate']['total_units']) }}</p>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">in {{ number_format($kpis['quality_rate']['total_hours'], 1) }} hours</p>
                </div>
                <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-3">
                    <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Work Orders Completed --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Orders Completed</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($kpis['quality_rate']['orders_count']) }}</p>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">work orders</p>
                </div>
                <div class="bg-blue-100 dark:bg-blue-900/50 rounded-full p-3">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Efficiency Score --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Status</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ ucfirst($kpis['quality_rate']['status']) }}</p>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">vs target (42/hr)</p>
                </div>
                <div class="
                    {{ $kpis['quality_rate']['status'] === 'excellent' ? 'bg-green-100 dark:bg-green-900/50' : '' }}
                    {{ $kpis['quality_rate']['status'] === 'good' ? 'bg-blue-100 dark:bg-blue-900/50' : '' }}
                    {{ $kpis['quality_rate']['status'] === 'warning' ? 'bg-yellow-100 dark:bg-yellow-900/50' : '' }}
                    {{ $kpis['quality_rate']['status'] === 'critical' ? 'bg-red-100 dark:bg-red-900/50' : '' }}
                    rounded-full p-3">
                    <svg class="w-6 h-6 
                        {{ $kpis['quality_rate']['status'] === 'excellent' ? 'text-green-600 dark:text-green-400' : '' }}
                        {{ $kpis['quality_rate']['status'] === 'good' ? 'text-blue-600 dark:text-blue-400' : '' }}
                        {{ $kpis['quality_rate']['status'] === 'warning' ? 'text-yellow-600 dark:text-yellow-400' : '' }}
                        {{ $kpis['quality_rate']['status'] === 'critical' ? 'text-red-600 dark:text-red-400' : '' }}
                        " fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($kpis['quality_rate']['status'] === 'excellent')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        @elseif($kpis['quality_rate']['status'] === 'critical')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        @endif
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Time-based Performance Analysis --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Time Period Breakdown --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Time-based Analysis</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Average Cycle Time</span>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $kpis['quality_rate']['orders_count'] > 0 && $kpis['quality_rate']['total_hours'] > 0 ? number_format($kpis['quality_rate']['total_hours'] / $kpis['quality_rate']['orders_count'], 2) : 0 }} hrs
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">per work order</p>
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Units per Work Order</span>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $kpis['quality_rate']['orders_count'] > 0 ? number_format($kpis['quality_rate']['total_units'] / $kpis['quality_rate']['orders_count'], 0) : 0 }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">average units</p>
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Daily Equivalent</span>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ number_format($kpis['quality_rate']['rate'] * 24, 0) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">units per day</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Performance Targets --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Performance vs Targets</h3>
            <div class="space-y-4">
                @php
                    $currentRate = $kpis['quality_rate']['rate'];
                    $targets = [
                        ['label' => 'Excellent', 'value' => 42, 'color' => 'green'],
                        ['label' => 'Good', 'value' => 31, 'color' => 'blue'], 
                        ['label' => 'Warning', 'value' => 21, 'color' => 'yellow'],
                        ['label' => 'Critical', 'value' => 0, 'color' => 'red']
                    ];
                @endphp

                @foreach($targets as $target)
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-medium text-gray-900 dark:text-white">{{ $target['label'] }}</h4>
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                bg-{{ $target['color'] }}-100 text-{{ $target['color'] }}-800 
                                dark:bg-{{ $target['color'] }}-900/50 dark:text-{{ $target['color'] }}-300">
                                @if($target['label'] !== 'Critical')
                                    ≥{{ $target['value'] }}/hr
                                @else
                                    <{{ $targets[2]['value'] }}/hr
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            @if($target['label'] !== 'Critical')
                                <div class="bg-{{ $target['color'] }}-500 h-2 rounded-full" 
                                     style="width: {{ $currentRate >= $target['value'] ? '100' : max(($currentRate / $target['value'] * 100), 5) }}%"></div>
                            @else
                                <div class="bg-{{ $target['color'] }}-500 h-2 rounded-full" 
                                     style="width: {{ $currentRate < $targets[2]['value'] ? '100' : '5' }}%"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Charts Section --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Throughput Trend Chart --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Hourly Throughput Trend</h3>
            <div class="h-64 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Chart integration coming soon</p>
                </div>
            </div>
        </div>

        {{-- Work Order Completion Timeline --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Work Order Timeline</h3>
            <div class="h-64 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Timeline visualization coming soon</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Performance Insights & Actions --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Production Throughput Insights</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Key Insights --}}
            <div>
                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Key Insights</h4>
                <div class="space-y-3">
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 
                            {{ $kpis['quality_rate']['status'] === 'excellent' ? 'bg-green-500' : '' }}
                            {{ $kpis['quality_rate']['status'] === 'good' ? 'bg-blue-500' : '' }}
                            {{ $kpis['quality_rate']['status'] === 'warning' ? 'bg-yellow-500' : '' }}
                            {{ $kpis['quality_rate']['status'] === 'critical' ? 'bg-red-500' : '' }}
                            rounded-full mt-2 flex-shrink-0"></div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Current throughput rate: {{ number_format($kpis['quality_rate']['rate'], 2) }} units/hour 
                            ({{ ucfirst($kpis['quality_rate']['status']) }} performance)
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Average work order completion time: 
                            {{ $kpis['quality_rate']['orders_count'] > 0 && $kpis['quality_rate']['total_hours'] > 0 ? number_format($kpis['quality_rate']['total_hours'] / $kpis['quality_rate']['orders_count'], 1) : 0 }} hours
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-green-500 rounded-full mt-2 flex-shrink-0"></div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Total production: {{ number_format($kpis['quality_rate']['total_units']) }} units from {{ $kpis['quality_rate']['orders_count'] }} completed work orders
                        </p>
                    </div>
                </div>
            </div>

            {{-- Recommended Actions --}}
            <div>
                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Recommendations</h4>
                <div class="space-y-3">
                    @if($kpis['quality_rate']['status'] === 'critical')
                        <div class="border border-red-200 dark:border-red-700 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Urgent: Investigate Production Issues</span>
                                <span class="px-2 py-1 text-xs bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 rounded-full">Critical</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Throughput is significantly below target. Check for bottlenecks, equipment issues, or process problems.</p>
                        </div>
                    @elseif($kpis['quality_rate']['status'] === 'warning')
                        <div class="border border-yellow-200 dark:border-yellow-700 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Optimize Production Process</span>
                                <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300 rounded-full">Warning</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Review work order cycle times and identify improvement opportunities.</p>
                        </div>
                    @else
                        <div class="border border-green-200 dark:border-green-700 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Maintain Current Performance</span>
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300 rounded-full">Good</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Production throughput is meeting targets. Focus on consistency and continuous improvement.</p>
                        </div>
                    @endif
                    
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Monitor Cycle Time Trends</span>
                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 rounded-full">Analysis</span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Track work order completion times to identify patterns and optimization opportunities.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>