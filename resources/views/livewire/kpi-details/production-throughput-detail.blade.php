{{-- Production Throughput KPI Detail View --}}
<div class="space-y-6">
    {{-- Hero Section with Key Metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- Current Throughput --}}
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 dark:from-blue-600 dark:to-blue-700 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Current Throughput</p>
                    <p class="text-3xl font-bold mt-1">{{ number_format($kpis['production_throughput']['throughput']) }}</p>
                    <p class="text-blue-100 text-sm">units/day</p>
                </div>
                <div class="bg-blue-400 dark:bg-blue-500 rounded-full p-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
            @if($kpis['production_throughput']['trend'] != 0)
                <div class="mt-3 flex items-center">
                    <span class="text-blue-100 text-xs">
                        {{ $kpis['production_throughput']['trend'] > 0 ? '↗' : '↘' }} 
                        {{ abs($kpis['production_throughput']['trend']) }} from previous period
                    </span>
                </div>
            @endif
        </div>

        {{-- Total Units Produced --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total Units</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($kpis['production_throughput']['total_units']) }}</p>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">in {{ $kpis['production_throughput']['days'] }} days</p>
                </div>
                <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-3">
                    <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Peak Daily Output --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Peak Daily Output</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">1,245</p>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">units/day</p>
                </div>
                <div class="bg-green-100 dark:bg-green-900/50 rounded-full p-3">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Efficiency Score --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Efficiency Score</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">87.3%</p>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">vs target</p>
                </div>
                <div class="bg-yellow-100 dark:bg-yellow-900/50 rounded-full p-3">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Performance Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Production Analysis by Part Number --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Production by Part Number</h3>
            <div class="space-y-4">
                @if(count($kpis['production_throughput']['production_by_part']) > 0)
                    @foreach($kpis['production_throughput']['production_by_part'] as $production)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $production['part'] }}</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($production['units']) }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $production['percentage'] }}%</p>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $production['percentage'] }}%"></div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">No production data available for this period</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Production Analysis by Machine Group --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Production by Machine</h3>
            <div class="space-y-4">
                @if(count($kpis['production_throughput']['production_by_machine']) > 0)
                    @foreach($kpis['production_throughput']['production_by_machine'] as $machine)
                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ $machine['machine'] }}</h4>
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    {{ $machine['status'] === 'excellent' ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : '' }}
                                    {{ $machine['status'] === 'good' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : '' }}
                                    {{ $machine['status'] === 'warning' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300' : '' }}
                                    {{ $machine['status'] === 'critical' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300' : '' }}">
                                    {{ $machine['efficiency'] }}%
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">{{ number_format($machine['units']) }} units</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $machine['orders'] }} orders completed</span>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">No machine data available for this period</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Charts Section --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Throughput Trend Chart --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Daily Throughput Trend</h3>
            <div class="h-64 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Chart integration coming soon</p>
                </div>
            </div>
        </div>

        {{-- Hourly Production Pattern --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Hourly Production Pattern</h3>
            <div class="h-64 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Time-based analytics coming soon</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Performance Insights & Actions --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Performance Insights & Recommendations</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Key Insights --}}
            <div>
                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Key Insights</h4>
                <div class="space-y-3">
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-green-500 rounded-full mt-2 flex-shrink-0"></div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">CNC Group A is performing 15% above target with 92.3% efficiency</p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-yellow-500 rounded-full mt-2 flex-shrink-0"></div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Assembly Line 2 shows 25% below average throughput</p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Peak production hours: 10 AM - 2 PM daily</p>
                    </div>
                </div>
            </div>

            {{-- Recommended Actions --}}
            <div>
                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Recommended Actions</h4>
                <div class="space-y-3">
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Investigate Assembly Line 2</span>
                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 rounded-full">High Priority</span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Check for maintenance issues or operator training needs</p>
                    </div>
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Optimize Shift Scheduling</span>
                            <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300 rounded-full">Medium Priority</span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Increase staffing during peak production hours</p>
                    </div>
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Replicate CNC Group A Success</span>
                            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300 rounded-full">Opportunity</span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Apply best practices to other machine groups</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
