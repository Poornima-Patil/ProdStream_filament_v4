<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    {{-- Controls Section --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 mb-6 shadow-sm">
        {{-- Header Row --}}
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-6 space-y-4 sm:space-y-0">
            <div class="flex-1">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">KPI Dashboard</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Monitor your production metrics and performance indicators</p>
            </div>

            {{-- Last Updated --}}
            <div class="flex items-center space-x-2 text-right">
                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm text-gray-500 dark:text-gray-400">Last updated: {{ $lastUpdated }}</span>
            </div>
        </div>

        {{-- Controls Row --}}
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between space-y-4 lg:space-y-0 lg:gap-6">
            {{-- Left Side: Date Range Filter & Display --}}
            <div class="flex flex-col sm:flex-row sm:items-end space-y-4 sm:space-y-0 sm:gap-8">
                <div class="flex flex-col space-y-2">
                    <label for="fromDate" class="text-sm font-medium text-gray-700 dark:text-gray-300">From Date</label>
                    <input type="date"
                           wire:model.live="fromDate"
                           id="fromDate"
                           class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 w-40 transition-colors">
                </div>

                <div class="flex flex-col space-y-2">
                    <label for="toDate" class="text-sm font-medium text-gray-700 dark:text-gray-300">To Date</label>
                    <input type="date"
                           wire:model.live="toDate"
                           id="toDate"
                           class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 w-40 transition-colors">
                </div>

                {{-- Date Range Display --}}
                <div class="flex items-end h-[42px]">
                    <span class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        Showing: {{ $this->getDateRangeLabel() }}
                    </span>
                </div>
            </div>

            {{-- Right Side: Refresh Button --}}
            <div class="flex justify-end">
                <button wire:click="refreshDashboard"
                        class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-700 text-white px-4 py-2 rounded-md text-xs font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-all duration-200 flex items-center justify-center space-x-1.5 shadow-sm hover:shadow-md whitespace-nowrap">
                    <svg wire:loading.remove wire:target="refreshDashboard" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <div wire:loading wire:target="refreshDashboard" class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        {{-- Factory Context Banner - Only show when loaded --}}
        @if($currentFactory && $kpis)
            <div class="bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6 shadow-sm">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 bg-blue-100 dark:bg-blue-900/50 rounded-lg mr-3">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Factory Analytics</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Showing KPIs for <span class="font-medium text-blue-600 dark:text-blue-400">{{ $currentFactory->name }}</span>
                            from <span class="font-medium">{{ $this->getDateRangeLabel() }}</span>
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- KPI Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
            {{-- Work Order Completion Rate - Primary KPI --}}
            @if(isset($kpis['work_order_completion_rate']))
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-md dark:hover:shadow-lg hover:shadow-gray-200 dark:hover:shadow-gray-900/50 transition-shadow cursor-pointer"
                     wire:click="openModal">
                    {{-- Status Indicator --}}
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white leading-tight">Work Order Completion Rate</h3>
                        <div class="w-3 h-3 rounded-full {{ $this->getStatusColor($kpis['work_order_completion_rate']['status']) }} flex-shrink-0"></div>
                    </div>

                    {{-- Main Value --}}
                    <div class="flex items-baseline mb-2 flex-wrap">
                        <span class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">{{ $kpis['work_order_completion_rate']['rate'] }}%</span>
                        @if($kpis['work_order_completion_rate']['trend'] != 0)
                            <span class="ml-2 text-xs sm:text-sm font-medium {{ $this->getTrendColor($kpis['work_order_completion_rate']['trend']) }}">
                                {{ $this->getTrendIcon($kpis['work_order_completion_rate']['trend']) }} {{ abs($kpis['work_order_completion_rate']['trend']) }}%
                            </span>
                        @endif
                    </div>

                    {{-- Details --}}
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        {{ $kpis['work_order_completion_rate']['completed_orders'] }} of {{ $kpis['work_order_completion_rate']['total_orders'] }} orders completed
                    </div>

                    {{-- Status Text --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                        <span class="text-sm font-medium {{ $this->getTrendColor($kpis['work_order_completion_rate']['trend']) }}">
                            {{ $this->getStatusText($kpis['work_order_completion_rate']['status']) }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Target: 85%</span>
                    </div>
                </div>
            @endif

            {{-- Net Production Throughput (Efficiency Oriented) 1 - Real KPI --}}
            @if(isset($kpis['quality_rate']))
                <div class="bg  -white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-md dark:hover:shadow-lg hover:shadow-gray-200 dark:hover:shadow-gray-900/50 transition-shadow cursor-pointer"
                     wire:click="viewKPIDetails('quality_rate')">
                    {{-- Status Indicator --}}
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white leading-tight">Net Production Throughput (Efficiency Oriented) 1</h3>
                        <div class="w-3 h-3 rounded-full {{ $this->getStatusColor($kpis['quality_rate']['status']) }} flex-shrink-0"></div>
                    </div>

                    {{-- Main Value with Both Day and Hour Rates --}}
                    <div class="flex items-baseline mb-2 flex-wrap">
                        <span class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($kpis['quality_rate']['rate'] * 24, 0) }}
                        </span>
                        <span class="ml-1 text-base sm:text-lg text-gray-600 dark:text-gray-400">units/day</span>
                        @if($kpis['quality_rate']['trend'] != 0)
                            <span class="ml-2 text-xs sm:text-sm font-medium {{ $this->getTrendColor($kpis['quality_rate']['trend']) }}">
                                {{ $this->getTrendIcon($kpis['quality_rate']['trend']) }} {{ abs($kpis['quality_rate']['trend']) }}%
                            </span>
                        @endif
                    </div>

                    {{-- Secondary Rate Display --}}
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        {{ number_format($kpis['quality_rate']['rate'], 1) }} units/hr
                    </div>

                    {{-- Details --}}
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        {{ number_format($kpis['quality_rate']['total_units']) }} units in {{ $kpis['quality_rate']['total_hours'] }}hrs ({{ $kpis['quality_rate']['orders_count'] }} orders)
                    </div>

                    {{-- Status Text --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                        <span class="text-sm font-medium {{ $this->getTrendColor($kpis['quality_rate']['trend']) }}">
                            {{ $this->getStatusText($kpis['quality_rate']['status']) }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Target: 1000/day</span>
                    </div>
                </div>
            @endif

            {{-- Work Order Scrapped Quantity - Real KPI --}}
            @if(isset($kpis['work_order_scrapped_qty']))
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 hover:shadow-md dark:hover:shadow-lg hover:shadow-gray-200 dark:hover:shadow-gray-900/50 transition-shadow cursor-pointer"
                     wire:click="viewKPIDetails('scrap_rate')">
                    {{-- Status Indicator --}}
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white leading-tight">Scrap Rate</h3>
                        <div class="w-3 h-3 rounded-full {{ $this->getStatusColor($kpis['work_order_scrapped_qty']['status']) }} flex-shrink-0"></div>
                    </div>

                    {{-- Main Value --}}
                    <div class="flex items-baseline mb-2 flex-wrap">
                        <span class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpis['work_order_scrapped_qty']['rate'], 1) }}%</span>
                        @if($kpis['work_order_scrapped_qty']['trend'] != 0)
                            <span class="ml-2 text-xs sm:text-sm font-medium {{ $this->getTrendColor(-$kpis['work_order_scrapped_qty']['trend']) }}">
                                {{ $this->getTrendIcon(-$kpis['work_order_scrapped_qty']['trend']) }} {{ abs($kpis['work_order_scrapped_qty']['trend']) }}%
                            </span>
                        @endif
                    </div>

                    {{-- Details --}}
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        {{ number_format($kpis['work_order_scrapped_qty']['scrapped_qty']) }} of {{ number_format($kpis['work_order_scrapped_qty']['total_qty']) }} units scrapped
                    </div>

                    {{-- Status Text --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                        <span class="text-sm font-medium {{ $this->getTrendColor(-$kpis['work_order_scrapped_qty']['trend']) }}">
                            {{ $this->getStatusText($kpis['work_order_scrapped_qty']['status']) }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Target: < 3%</span>
                    </div>
                </div>
            @endif

            {{-- Future KPI Cards - Placeholder Design --}}
            @foreach(['machine_utilization', 'on_time_delivery'] as $kpiKey)
                @if(isset($kpis[$kpiKey]))
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 opacity-60 relative">
                        {{-- Coming Soon Badge --}}
                        <div class="absolute top-2 sm:top-3 right-2 sm:right-3 bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 text-xs font-medium px-2 py-1 rounded-full">
                            Coming Soon
                        </div>

                        {{-- Header --}}
                        <div class="flex items-center justify-between mb-3 sm:mb-4 pr-16 sm:pr-20">
                            <h3 class="text-base sm:text-lg font-semibold text-gray-500 dark:text-gray-400 leading-tight">{{ $kpis[$kpiKey]['name'] }}</h3>
                            <div class="w-3 h-3 rounded-full bg-gray-300 dark:bg-gray-600 flex-shrink-0"></div>
                        </div>

                        {{-- Placeholder Value --}}
                        <div class="flex items-baseline mb-2">
                            <span class="text-2xl sm:text-3xl font-bold text-gray-400 dark:text-gray-500">--</span>
                            <span class="ml-1 text-base sm:text-lg text-gray-400 dark:text-gray-500">{{ $kpis[$kpiKey]['unit'] }}</span>
                        </div>

                        {{-- Placeholder Details --}}
                        <div class="text-sm text-gray-400 dark:text-gray-500 mb-3">
                            Data will be available when implemented
                        </div>

                        {{-- Status --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                            <span class="text-sm font-medium text-gray-400 dark:text-gray-500">Pending Implementation</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">--</span>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Future Sections Placeholder --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
            {{-- Charts Section Placeholder --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 space-y-2 sm:space-y-0">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Production Trends</h3>
                    <div class="bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 text-xs font-medium px-2 py-1 rounded-full self-start sm:self-auto">
                        Coming Soon
                    </div>
                </div>
                <div class="h-48 sm:h-64 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                    <div class="text-center px-4">
                        <svg class="mx-auto h-8 sm:h-12 w-8 sm:w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <p class="mt-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400">Interactive charts coming soon</p>
                    </div>
                </div>
            </div>

            {{-- Real-time Status Placeholder --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 space-y-2 sm:space-y-0">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Real-time Status</h3>
                    <div class="bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 text-xs font-medium px-2 py-1 rounded-full self-start sm:self-auto">
                        Coming Soon
                    </div>
                </div>
                <div class="h-48 sm:h-64 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                    <div class="text-center px-4">
                        <svg class="mx-auto h-8 sm:h-12 w-8 sm:w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="mt-2 text-xs sm:text-sm text-gray-500 dark:text-gray-400">Live machine status coming soon</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <button wire:click="exportKPIs"
                        class="flex items-center justify-center px-3 sm:px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="truncate">Export Report</span>
                </button>

                <button wire:click="viewKPIDetails('work_orders')"
                        class="flex items-center justify-center px-3 sm:px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <span class="truncate">View Work Orders</span>
                </button>

                <button wire:click="viewKPIDetails('machines')"
                        class="flex items-center justify-center px-3 sm:px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                    </svg>
                    <span class="truncate">Machine Status</span>
                </button>

                <button wire:click="viewKPIDetails('quality')"
                        class="flex items-center justify-center px-3 sm:px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="truncate">Quality Report</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Loading Overlay --}}
    <div wire:loading.flex wire:target="loadKPIs,refreshDashboard"
         class="fixed inset-0 bg-gray-500 bg-opacity-50 dark:bg-gray-900 dark:bg-opacity-75 flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 flex items-center space-x-3 shadow-lg max-w-sm w-full">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600 dark:border-indigo-400 flex-shrink-0"></div>
            <span class="text-sm sm:text-base text-gray-700 dark:text-gray-300 font-medium">Loading KPIs...</span>
        </div>
    </div>


    {{-- KPI Detail View Modal --}}
    @if($showKPIDetail)
        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 9999; overflow-y: auto; padding: 1rem;">
            <div class="min-h-screen flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-7xl w-full max-h-[90vh] flex flex-col">
                    {{-- Fixed Header --}}
                    <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $selectedKPITitle }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $currentFactory->name }} - {{ $this->getDateRangeLabel() }}</p>
                        </div>
                        <button wire:click="closeKPIDetail" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    {{-- Scrollable Content --}}
                    <div class="flex-1 overflow-y-auto p-6">
                    @if($selectedKPI === 'work_order_completion_rate')
                        @include('livewire.kpi-details.work-order-completion-detail')
                    @elseif($selectedKPI === 'production_throughput')
                        @include('livewire.kpi-details.production-throughput-detail')
                    @elseif($selectedKPI === 'on_time_delivery_rate')
                        @include('livewire.kpi-details.on-time-delivery-detail')
                    @elseif($selectedKPI === 'quality_rate')
                        @include('livewire.kpi-details.quality-rate-detail')
                    @else
                        {{-- Generic KPI Details for other types --}}
                        <div class="text-center py-8">
                            <svg class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">{{ $selectedKPITitle }}</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">Detailed view for {{ $selectedKPI }} will be available soon.</p>
                            <button wire:click="closeKPIDetail" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Close
                            </button>
                        </div>
                    @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Toast Notifications - FIXED ARRAY HANDLING --}}
<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('notify', function (data) {
            // Fix: Handle data as array (Livewire passes it as array)
            const notification_data = Array.isArray(data) ? data[0] : data;

            console.log('Notification data fixed:', notification_data);
            console.log('Type:', notification_data.type);
            console.log('Message:', notification_data.message);

            // Only show notification if there's actually a message
            if (notification_data.message && notification_data.message.trim().length > 0) {
                const notification = document.createElement('div');
                // Use your primary color with dark mode support
                notification.className = 'fixed top-4 right-4 p-4 rounded-md shadow-lg dark:shadow-xl z-50 text-white bg-blue-600 dark:bg-blue-500 border border-blue-700 dark:border-blue-400';
                notification.textContent = notification_data.message;
                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.remove();
                }, 3000);
            } else {
                console.warn('Empty notification blocked:', notification_data);
            }
        });
    });
</script>
