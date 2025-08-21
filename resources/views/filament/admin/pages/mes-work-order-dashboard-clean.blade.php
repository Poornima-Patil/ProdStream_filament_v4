<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">MES Work Order Dashboard</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Manufacturing Execution System - Real-time Production Monitoring</p>
            </div>
            <div class="flex gap-4 items-center">
                <span class="bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-gray-300 px-3 py-1 rounded text-sm">Sample Data</span>
                <span class="text-gray-500 dark:text-gray-400 text-sm">150 of 150 records</span>
                <button class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 px-4 py-2 rounded text-white flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Upload ProdStream Data
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4 shadow-sm">
            <div class="flex items-center gap-4 mb-4">
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filters</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $totalWorkOrders ?? 0 }} of {{ $totalWorkOrders ?? 0 }} records</span>
            </div>

            <div class="grid grid-cols-5 gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                    <select wire:model.live="filterStatus" class="w-full rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="Completed">Completed</option>
                        <option value="Start">Start</option>
                        <option value="Hold">Hold</option>
                        <option value="Quality_Check">Quality Check</option>
                        <option value="Assigned">Assigned</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Machine Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Machine</label>
                    <select wire:model.live="filterMachine" class="w-full rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Machines</option>
                        @foreach($machines as $machine)
                            <option value="{{ $machine['name'] }}">{{ $machine['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Operator Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Operator</label>
                    <select wire:model.live="filterOperator" class="w-full rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Operators</option>
                        @foreach($operators as $operator)
                            <option value="{{ $operator['id'] }}">{{ $operator['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- From Date Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Date</label>
                    <input type="date" wire:model.live="filterDateFrom" class="w-full rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <!-- To Date Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">To Date</label>
                    <input type="date" wire:model.live="filterDateTo" class="w-full rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 dark:border-slate-700">
            <nav class="flex space-x-8" aria-label="Tabs">
                <button wire:click="setActiveTab('overview')" class="@if($activeTab === 'overview') bg-blue-600 text-white @else text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-200 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Overview
                </button>
                <button wire:click="setActiveTab('pivot')" class="@if($activeTab === 'pivot') bg-blue-600 text-white @else text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-200 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Pivot Table
                </button>
                <button wire:click="setActiveTab('analytics')" class="@if($activeTab === 'analytics') bg-blue-600 text-white @else text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-200 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Analytics
                </button>
                <button wire:click="setActiveTab('details')" class="@if($activeTab === 'details') bg-blue-600 text-white @else text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-200 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Work Order Details
                </button>
            </nav>
        </div>

        <div class="tab-content">
        @if($activeTab === 'overview')
            <div class="space-y-6" data-tab="overview">
                <!-- KPI Cards Row -->
                <div class="grid grid-cols-4 gap-4">
                    <!-- Total Work Orders -->
                    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Work Orders</h3>
                                </div>
                                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $totalWorkOrders ?? 0 }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $completedOrders ?? 0 }} completed, {{ $inProgressOrders ?? 0 }} in progress
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Overall Yield -->
                    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Overall Yield</h3>
                                </div>
                                <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ $overallYield ?? '0%' }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    OK: {{ number_format($totalOkQty ?? 0) }} | KO: {{ number_format($totalKoQty ?? 0) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Completion Rate -->
                    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Completion Rate</h3>
                                </div>
                                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $completionRate ?? '0%' }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($completedOrders ?? 0) }} of {{ number_format($totalWorkOrders ?? 0) }} orders
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Defect Rate -->
                    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Defect Rate</h3>
                                </div>
                                <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ $defectRate ?? '0%' }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($totalKoQty ?? 0) }} defective units
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-2 gap-6">
                    <!-- Work Order Status Distribution -->
                    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Work Order Status Distribution</h3>
                        <div class="relative h-80">
                            <canvas id="statusChart" width="400" height="300"></canvas>
                        </div>
                        
                        <!-- Custom Legend -->
                        <div class="mt-4 grid grid-cols-3 gap-2 text-xs">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                                <span class="text-gray-700 dark:text-gray-300">Completed: <span id="completed-percent">0%</span></span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-blue-500 mr-2"></div>
                                <span class="text-gray-700 dark:text-gray-300">In Progress: <span id="progress-percent">0%</span></span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-orange-500 mr-2"></div>
                                <span class="text-gray-700 dark:text-gray-300">Hold: <span id="hold-percent">0%</span></span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-purple-500 mr-2"></div>
                                <span class="text-gray-700 dark:text-gray-300">Quality: <span id="quality-percent">0%</span></span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-teal-500 mr-2"></div>
                                <span class="text-gray-700 dark:text-gray-300">Pending: <span id="pending-percent">0%</span></span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-red-500 mr-2"></div>
                                <span class="text-gray-700 dark:text-gray-300">Cancelled: <span id="cancelled-percent">0%</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Machine Utilization -->
                    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Machine Utilization</h3>
                        <div class="relative h-80">
                            <canvas id="utilizationChart" width="400" height="300"></canvas>
                        </div>
                        
                        <!-- Custom Legend -->
                        <div class="mt-4 flex justify-center gap-6 text-xs">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-sm bg-purple-500 mr-2"></div>
                                <span class="text-gray-700 dark:text-gray-300">Utilization %</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-sm bg-green-500 mr-2"></div>
                                <span class="text-gray-700 dark:text-gray-300">Yield %</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($activeTab === 'pivot')
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Work Order Pivot Analysis</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                            <thead class="bg-gray-50 dark:bg-slate-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Count</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Percentage</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                @foreach($statusDistribution ?? [] as $status => $percentage)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $status }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ round(($totalWorkOrders ?? 0) * ($percentage / 100)) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $percentage }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if($activeTab === 'analytics')
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Production Analytics</h3>
                    <p class="text-gray-600 dark:text-gray-400">Advanced analytics coming soon...</p>
                </div>
            </div>
        @endif

        @if($activeTab === 'details')
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Work Order Details</h3>
                    @if(!empty($workOrders))
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                <thead class="bg-gray-50 dark:bg-slate-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">WO Number</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Machine</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Operator</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Qty</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Produced</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Yield</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                    @foreach($workOrders as $wo)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="/admin/work-orders/{{ $wo['id'] ?? '' }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                                                {{ $wo['wo_number'] ?? 'N/A' }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                @if(($wo['status'] ?? '') === 'Completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                                @elseif(($wo['status'] ?? '') === 'In Progress' || ($wo['status'] ?? '') === 'Start') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                                @elseif(($wo['status'] ?? '') === 'Hold') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                                                @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                                                {{ $wo['status'] ?? 'Unknown' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $wo['machine'] ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $wo['operator'] ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ number_format($wo['qty'] ?? 0) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ number_format($wo['produced_qty'] ?? 0) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $wo['yield'] ?? '0' }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-gray-500 dark:text-gray-400">No work orders found with current filters.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        </div>

        <!-- Chart.js and JavaScript Functions -->
        <script>
            // Initialize dashboard charts object
            window.dashboardCharts = window.dashboardCharts || {};

            // Function to dynamically load Chart.js
            function loadChartJS() {
                return new Promise((resolve, reject) => {
                    if (typeof Chart !== 'undefined') {
                        console.log('Chart.js already loaded');
                        resolve();
                        return;
                    }

                    console.log('Loading Chart.js dynamically...');
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
                    script.onload = () => {
                        console.log('Chart.js loaded successfully');
                        resolve();
                    };
                    script.onerror = () => {
                        console.error('Failed to load Chart.js from CDN');
                        reject();
                    };
                    document.head.appendChild(script);
                });
            }

            // Load Chart.js when needed
            window.loadChartsWhenReady = async function() {
                try {
                    await loadChartJS();
                    initializeCharts();
                } catch (error) {
                    console.error('Failed to load charts:', error);
                }
            };

            // Initialize charts function
            function initializeCharts() {
                console.log('🎯 Initializing dashboard charts...');
                
                const statusCanvas = document.getElementById('statusChart');
                const utilizationCanvas = document.getElementById('utilizationChart');

                if (!statusCanvas || !utilizationCanvas) {
                    console.log('❌ Chart canvas elements not found');
                    return false;
                }

                try {
                    // Get status distribution data from backend
                    const statusDistribution = @json($statusDistribution ?? []);
                    console.log('📊 Status distribution data:', statusDistribution);
                    console.log('📊 Status distribution keys:', Object.keys(statusDistribution));
                    console.log('📊 Status distribution values:', Object.values(statusDistribution));
                    
                    // Prepare status chart data
                    const statusLabels = Object.keys(statusDistribution);
                    const statusValues = Object.values(statusDistribution);
                    
                    // Use fallback data if no filtered data available
                    const finalStatusLabels = statusLabels.length > 0 ? statusLabels : ['Completed', 'Start', 'Hold', 'Quality_Check', 'Assigned', 'Cancelled'];
                    const finalStatusValues = statusValues.length > 0 ? statusValues : [20.7, 17.3, 14.7, 14.0, 16.7, 16.7];

                    console.log('📊 Final chart labels:', finalStatusLabels);
                    console.log('📊 Final chart values:', finalStatusValues);

                    // Update legend percentages
                    const statusMapping = {
                        'Completed': 'completed-percent',
                        'Start': 'progress-percent', 
                        'In Progress': 'progress-percent',
                        'Hold': 'hold-percent',
                        'On Hold': 'hold-percent',
                        'Quality_Check': 'quality-percent',
                        'Quality Check': 'quality-percent',
                        'Assigned': 'pending-percent',
                        'Pending': 'pending-percent',
                        'Cancelled': 'cancelled-percent'
                    };

                    // Update legend with actual values
                    finalStatusLabels.forEach((label, index) => {
                        const elementId = statusMapping[label];
                        const element = document.getElementById(elementId);
                        if (element) {
                            element.textContent = finalStatusValues[index] + '%';
                        }
                    });

                    // Create dynamic color mapping for statuses
                    const statusColors = {
                        'Completed': '#10B981',   // Green
                        'Start': '#3B82F6',       // Blue 
                        'In Progress': '#3B82F6', // Blue
                        'Hold': '#F97316',        // Orange
                        'On Hold': '#F97316',     // Orange
                        'Quality_Check': '#8B5CF6', // Purple
                        'Quality Check': '#8B5CF6', // Purple
                        'Assigned': '#14B8A6',    // Teal
                        'Pending': '#14B8A6',     // Teal
                        'Cancelled': '#EF4444'    // Red
                    };

                    // Map colors to actual status labels
                    const chartColors = finalStatusLabels.map(label => 
                        statusColors[label] || '#6B7280' // Default gray for unknown statuses
                    );

                    // Create Status Distribution Chart with filtered data
                    window.dashboardCharts.statusChart = new Chart(statusCanvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: finalStatusLabels,
                            datasets: [{
                                data: finalStatusValues,
                                backgroundColor: chartColors,
                                borderWidth: 2,
                                borderColor: 'rgba(255, 255, 255, 0.8)',
                                hoverBorderWidth: 3,
                                hoverOffset: 8
                            }]
                        },
                        plugins: [{
                            id: 'customDataLabels',
                            afterDatasetsDraw: function(chart) {
                                const ctx = chart.ctx;
                                const meta = chart.getDatasetMeta(0);

                                meta.data.forEach((element, index) => {
                                    const value = chart.data.datasets[0].data[index];
                                    const label = chart.data.labels[index];
                                    
                                    // Only show labels for segments > 3%
                                    if (value > 3) {
                                        const centerPoint = element.getCenterPoint();
                                        const radius = (element.innerRadius + element.outerRadius) / 2;
                                        const angle = element.startAngle + (element.endAngle - element.startAngle) / 2;
                                        
                                        // Position the text slightly outward from center of segment
                                        const x = centerPoint.x + Math.cos(angle) * radius * 0.85;
                                        const y = centerPoint.y + Math.sin(angle) * radius * 0.85;

                                        ctx.save();
                                        
                                        // Theme-aware text color
                                        const isDarkMode = document.documentElement.classList.contains('dark') || 
                                                         document.body.classList.contains('dark') ||
                                                         getComputedStyle(document.documentElement).getPropertyValue('color-scheme').includes('dark');
                                        
                                        ctx.fillStyle = 'white';
                                        ctx.font = 'bold 10px Arial';
                                        ctx.textAlign = 'center';
                                        ctx.textBaseline = 'middle';
                                        
                                        // Enhanced shadow for better visibility
                                        ctx.shadowColor = isDarkMode ? 'rgba(0, 0, 0, 0.8)' : 'rgba(0, 0, 0, 0.6)';
                                        ctx.shadowOffsetX = 1;
                                        ctx.shadowOffsetY = 1;
                                        ctx.shadowBlur = 3;
                                        
                                        // Multi-line text: label on first line, percentage on second
                                        const labelText = label;
                                        const percentageText = value + '%';
                                        
                                        ctx.fillText(labelText, x, y - 6);
                                        ctx.fillText(percentageText, x, y + 6);
                                        
                                        ctx.restore();
                                    }
                                });
                            }
                        }],
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: { 
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + '%';
                                        }
                                    },
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: 'white',
                                    bodyColor: 'white',
                                    borderColor: 'rgba(255, 255, 255, 0.2)',
                                    borderWidth: 1
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    });

                    // Get machine utilization data from backend
                    const machineStatuses = @json($machineStatuses ?? []);
                    console.log('Machine statuses data:', machineStatuses);
                    
                    // Prepare machine utilization data
                    const machineNames = machineStatuses.map(machine => machine.name || 'Unknown');
                    const utilizationValues = machineStatuses.map(machine => parseFloat(machine.utilization) || 0);
                    const yieldValues = machineStatuses.map(machine => {
                        // Calculate yield from machine data if available, otherwise use a default
                        return machine.yield ? parseFloat(machine.yield) : Math.max(85, 100 - (machine.utilization || 0) / 4);
                    });

                    // Use fallback data if no machine data available
                    const finalMachineNames = machineNames.length > 0 ? machineNames : ['LATHE-001', 'MILL-002', 'MILL-001', 'CNC-001', 'ASSEMBLY-001', 'PRESS-002', 'CNC-002', 'LATHE-002', 'PRESS-001', 'CNC-003'];
                    const finalUtilizationValues = utilizationValues.length > 0 ? utilizationValues : [25, 30, 28, 26, 35, 33, 40, 29, 22, 38];
                    const finalYieldValues = yieldValues.length > 0 ? yieldValues : [95, 92, 88, 85, 90, 89, 93, 91, 87, 86];

                    // Create Machine Utilization Chart with filtered data
                    window.dashboardCharts.utilizationChart = new Chart(utilizationCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: finalMachineNames,
                            datasets: [{
                                label: 'Utilization %',
                                data: finalUtilizationValues,
                                backgroundColor: '#8B5CF6',
                                borderRadius: 4,
                                borderSkipped: false
                            }, {
                                label: 'Yield %',
                                data: finalYieldValues,
                                backgroundColor: '#10B981',
                                borderRadius: 4,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { 
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: '#6B7280',
                                        font: {
                                            size: 11
                                        },
                                        maxRotation: 45
                                    }
                                },
                                y: { 
                                    beginAtZero: true, 
                                    max: 100,
                                    grid: {
                                        color: 'rgba(107, 114, 128, 0.1)'
                                    },
                                    ticks: {
                                        color: '#6B7280',
                                        font: {
                                            size: 11
                                        }
                                    }
                                }
                            },
                            plugins: { 
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: 'white',
                                    bodyColor: 'white',
                                    borderColor: 'rgba(255, 255, 255, 0.2)',
                                    borderWidth: 1,
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y + '%';
                                        }
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    });

                    window.dashboardCharts.initialized = true;
                    console.log('✅ Charts created successfully with filtered data!');
                    return true;
                } catch (error) {
                    console.error('❌ Error creating charts:', error);
                    console.error('Error details:', {
                        message: error.message,
                        stack: error.stack,
                        statusDistribution: @json($statusDistribution ?? []),
                        machineStatuses: @json($machineStatuses ?? [])
                    });
                    
                    // Show error in UI
                    const statusChartContainer = document.getElementById('statusChart');
                    const utilizationChartContainer = document.getElementById('utilizationChart');
                    
                    if (statusChartContainer) {
                        statusChartContainer.innerHTML = `
                            <div style="display: flex; align-items: center; justify-content: center; height: 300px; color: #ef4444;">
                                <div style="text-align: center;">
                                    <p>❌ Chart Error</p>
                                    <p style="font-size: 12px;">${error.message}</p>
                                </div>
                            </div>
                        `;
                    }
                    
                    return false;
                }
            }

            // Listen for Livewire updates (when filters change)
            document.addEventListener('livewire:updated', function () {
                console.log('🔄 Livewire updated - checking for chart refresh needed');
                
                // Wait for DOM to settle after Livewire update
                setTimeout(function() {
                    // Only refresh if we're on the overview tab and charts should be visible
                    const overviewSection = document.querySelector('[data-tab="overview"]');
                    const statusChart = document.getElementById('statusChart');
                    const utilizationChart = document.getElementById('utilizationChart');
                    
                    console.log('Overview section:', overviewSection ? 'exists' : 'missing');
                    console.log('Status chart element:', statusChart ? 'exists' : 'missing');
                    console.log('Utilization chart element:', utilizationChart ? 'exists' : 'missing');
                    
                    if (overviewSection && statusChart && utilizationChart) {
                        console.log('✅ All chart elements present - refreshing charts');
                        
                        // Destroy existing charts first to prevent memory leaks
                        if (window.dashboardCharts) {
                            Object.keys(window.dashboardCharts).forEach(key => {
                                if (key !== 'initialized' && window.dashboardCharts[key] && typeof window.dashboardCharts[key].destroy === 'function') {
                                    console.log('🗑️ Destroying existing chart:', key);
                                    window.dashboardCharts[key].destroy();
                                }
                            });
                            window.dashboardCharts.initialized = false;
                        }
                        
                        // Reload charts with fresh data
                        if (typeof window.loadChartsWhenReady === 'function') {
                            console.log('🔄 Loading charts with new filtered data');
                            window.loadChartsWhenReady();
                        } else {
                            console.error('❌ window.loadChartsWhenReady function not available');
                        }
                    } else {
                        console.log('ℹ️ Not refreshing charts - not on overview tab or elements missing');
                    }
                }, 100);
            });

            // Initialize on page load if we're already on overview tab
            document.addEventListener('DOMContentLoaded', function() {
                console.log('🚀 DOM loaded - checking for initial chart setup');
                
                setTimeout(function() {
                    const overviewSection = document.querySelector('[data-tab="overview"]');
                    const statusChart = document.getElementById('statusChart');
                    const utilizationChart = document.getElementById('utilizationChart');
                    
                    if (overviewSection && statusChart && utilizationChart) {
                        console.log('✅ Initial chart setup - loading charts');
                        if (typeof window.loadChartsWhenReady === 'function') {
                            window.loadChartsWhenReady();
                        } else {
                            console.log('⏳ Chart function not ready yet, retrying...');
                            setTimeout(function() {
                                if (typeof window.loadChartsWhenReady === 'function') {
                                    window.loadChartsWhenReady();
                                }
                            }, 1000);
                        }
                    } else {
                        console.log('ℹ️ Not on overview tab or chart elements not ready');
                    }
                }, 500);
            });

            // Additional fallback: Try to initialize charts when tab becomes visible
            document.addEventListener('click', function(e) {
                if (e.target && e.target.textContent && e.target.textContent.trim() === 'Overview') {
                    console.log('📊 Overview tab clicked - ensuring charts are loaded');
                    setTimeout(function() {
                        if (typeof window.loadChartsWhenReady === 'function') {
                            if (!window.dashboardCharts || !window.dashboardCharts.initialized) {
                                console.log('🔄 Loading charts after tab switch');
                                window.loadChartsWhenReady();
                            }
                        }
                    }, 300);
                }
            });
        </script>
    </div>
</x-filament-panels::page>
