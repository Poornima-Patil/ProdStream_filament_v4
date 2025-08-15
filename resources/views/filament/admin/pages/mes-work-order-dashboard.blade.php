<x-filament-panels::page>
<div class="bg-gray-50 dark:bg-slate-900 text-gray-900 dark:text-gray-100 min-h-screen p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
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
    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4 mb-6 shadow-sm">
        <div class="flex items-center gap-4 mb-4">
            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
            </svg>
            <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filters</span>
        </div>
        <div class="grid grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Status</label>
                <select wire:model.live="filterStatus" class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded px-3 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">All Statuses</option>
                    <option value="Assigned" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">Assigned</option>
                    <option value="Start" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">Start</option>
                    <option value="Hold" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">Hold</option>
                    <option value="Completed" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">Completed</option>
                    <option value="Cancelled" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">Cancelled</option>
                </select>
            </div>
                        <div>
                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Machine</label>
                <select wire:model.live="filterMachine" class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded px-3 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">All Machines</option>
                    @foreach($machines ?? [] as $machine)
                        <option value="{{ $machine['name'] }}" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">{{ $machine['name'] }}</option>
                    @endforeach
                </select>
            </div>
                                                <div>
                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Operator</label>
                <select wire:model.live="filterOperator" class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded px-3 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">All Operators</option>
                    @foreach($operators ?? [] as $operator)
                        <option value="{{ $operator['name'] }}" class="text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700">{{ $operator['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">From Date</label>
                <input type="date" wire:model.live="filterDateFrom" class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded px-4 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">To Date</label>
                <input type="date" wire:model.live="filterDateTo" class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded px-4 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-sm">
        <div class="border-b border-gray-200 dark:border-slate-700">
            <nav class="flex space-x-8 px-6" aria-label="Tabs">
                <button wire:click="setActiveTab('real-time')" class="@if($activeTab === 'real-time') bg-blue-600 text-white @else text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-200 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Real-time Production
                </button>
                <button wire:click="setActiveTab('overview')" class="@if($activeTab === 'overview') bg-blue-600 text-white @else text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-200 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Overview
                </button>
                <button wire:click="setActiveTab('pivot-table')" class="@if($activeTab === 'pivot-table') bg-blue-600 text-white @else text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-200 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Pivot Table
                </button>
                <button wire:click="setActiveTab('analytics')" class="@if($activeTab === 'analytics') bg-blue-600 text-white @else text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-200 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Analytics
                </button>
                <button wire:click="setActiveTab('work-order-details')" class="@if($activeTab === 'work-order-details') bg-blue-600 text-white @else text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-200 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Work Order Details
                </button>
            </nav>
        </div>
    </div>

    <!-- Tab Content -->
    @if($activeTab === 'real-time')
        <!-- Real-time Production Status -->
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Real-time Production Status</h2>
                <div class="ml-auto flex items-center gap-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Last updated: 15:26:47</span>
                </div>
            </div>
            
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 border border-blue-200 dark:border-blue-700 text-gray-900 dark:text-gray-100 rounded-lg p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-700 dark:text-blue-300 text-sm font-medium">Active Orders</p>
                            <p class="text-3xl font-bold text-blue-900 dark:text-blue-100">{{ $activeOrders ?? 26 }}</p>
                        </div>
                        <div class="p-3 bg-blue-500 rounded-full">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6-8h8a2 2 0 012 2v10l-4 4H7a2 2 0 01-2-2V4a2 2 0 012-2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/20 dark:to-amber-800/20 border border-amber-200 dark:border-amber-700 text-gray-900 dark:text-gray-100 rounded-lg p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-amber-700 dark:text-amber-300 text-sm font-medium">Pending Orders</p>
                            <p class="text-3xl font-bold text-amber-900 dark:text-amber-100">{{ $pendingOrders ?? 25 }}</p>
                        </div>
                        <div class="p-3 bg-amber-500 rounded-full">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 border border-emerald-200 dark:border-emerald-700 text-gray-900 dark:text-gray-100 rounded-lg p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-emerald-700 dark:text-emerald-300 text-sm font-medium">Completed Today</p>
                            <p class="text-3xl font-bold text-emerald-900 dark:text-emerald-100">{{ $completedToday ?? 0 }}</p>
                        </div>
                        <div class="p-3 bg-emerald-500 rounded-full">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 border border-red-200 dark:border-red-700 text-gray-900 dark:text-gray-100 rounded-lg p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-700 dark:text-red-300 text-sm font-medium">On Hold</p>
                            <p class="text-3xl font-bold text-red-900 dark:text-red-100">{{ $onHold ?? 22 }}</p>
                        </div>
                        <div class="p-3 bg-red-500 rounded-full">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Machine Status Grid -->
            <div class="grid grid-cols-5 gap-4">
                @foreach($machineStatuses ?? [] as $machine)
                    <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $machine['name'] }}</h3>
                            @if($machine['status'] === 'RUNNING')
                                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                            @elseif($machine['status'] === 'IDLE')
                                <div class="w-3 h-3 bg-amber-500 rounded-full"></div>
                            @else
                                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                            @endif
                        </div>
                        <div class="text-sm">
                            <p class="text-gray-600 dark:text-gray-400 mb-1">Status: 
                                <span class="font-medium @if($machine['status'] === 'RUNNING') text-green-600 dark:text-green-400 @else text-red-600 dark:text-red-400 @endif">{{ $machine['status'] }}</span>
                            </p>
                            <p class="text-gray-600 dark:text-gray-400 mb-2">Utilization: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $machine['utilization'] }}%</span></p>
                            <div class="w-full bg-gray-200 dark:bg-slate-600 rounded-full h-2 mb-3">
                                <div class="@if($machine['utilization'] >= 80) bg-green-500 @elseif($machine['utilization'] >= 60) bg-amber-500 @else bg-red-500 @endif h-2 rounded-full transition-all" style="width: {{ $machine['utilization'] }}%"></div>
                            </div>
                        </div>
                        @if($machine['current_work_order'])
                            <div class="text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Current:</span>
                                <br>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $machine['current_work_order'] }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($activeTab === 'overview')
        <div class="grid grid-cols-2 gap-6">
            <!-- Work Order Status Distribution -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Work Order Status Distribution</h3>
                <div class="relative" style="height: 320px; width: 100%;">
                    <canvas id="statusChart" width="400" height="320"></canvas>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                        <span class="text-gray-700 dark:text-gray-300">Completed: {{ $statusDistribution['Completed'] ?? '20.7' }}%</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-orange-500 rounded-full mr-2"></div>
                        <span class="text-gray-700 dark:text-gray-300">In Progress: {{ $statusDistribution['Start'] ?? '17.3' }}%</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-purple-500 rounded-full mr-2"></div>
                        <span class="text-gray-700 dark:text-gray-300">On Hold: {{ $statusDistribution['Hold'] ?? '14.7' }}%</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                        <span class="text-gray-700 dark:text-gray-300">Quality Check: {{ $statusDistribution['Quality_Check'] ?? '14.0' }}%</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-teal-500 rounded-full mr-2"></div>
                        <span class="text-gray-700 dark:text-gray-300">Pending: {{ $statusDistribution['Assigned'] ?? '16.7' }}%</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                        <span class="text-gray-700 dark:text-gray-300">Cancelled: {{ $statusDistribution['Cancelled'] ?? '16.7' }}%</span>
                    </div>
                </div>
            </div>

            <!-- Machine Utilization -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Machine Utilization</h3>
                <div class="relative" style="height: 320px; width: 100%;">
                    <canvas id="utilizationChart" width="400" height="320"></canvas>
                </div>
                <div class="mt-4 flex justify-center gap-6 text-sm">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-purple-500 rounded-full mr-2"></div>
                        <span class="text-gray-700 dark:text-gray-300">Utilization %</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                        <span class="text-gray-700 dark:text-gray-300">Yield %</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart.js Simple Implementation -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
        <script>
            let statusChart = null;
            let utilizationChart = null;
            
            function waitForChart() {
                return new Promise((resolve) => {
                    function checkChart() {
                        if (typeof Chart !== 'undefined') {
                            resolve();
                        } else {
                            setTimeout(checkChart, 100);
                        }
                    }
                    checkChart();
                });
            }
            
            async function initializeCharts() {
                await waitForChart();
                
                const statusCanvas = document.getElementById('statusChart');
                const utilizationCanvas = document.getElementById('utilizationChart');
                
                if (!statusCanvas || !utilizationCanvas) {
                    console.log('Canvas elements not found');
                    return;
                }
                
                // Destroy existing charts
                if (statusChart) {
                    statusChart.destroy();
                    statusChart = null;
                }
                if (utilizationChart) {
                    utilizationChart.destroy();
                    utilizationChart = null;
                }
                
                try {
                    // Status Distribution Chart with real data
                    const statusDistribution = @json($statusDistribution ?? []);
                    console.log('Creating status chart with data:', statusDistribution);
                    
                    const labels = [];
                    const data = [];
                    const backgroundColor = [];
                    
                    const statusColors = {
                        'Completed': '#3B82F6',
                        'Start': '#F97316', 
                        'Hold': '#8B5CF6',
                        'Quality_Check': '#10B981',
                        'Assigned': '#14B8A6',
                        'Cancelled': '#F59E0B'
                    };
                    
                    const statusLabels = {
                        'Completed': 'Completed',
                        'Start': 'In Progress',
                        'Hold': 'On Hold',
                        'Quality_Check': 'Quality Check',
                        'Assigned': 'Pending',
                        'Cancelled': 'Cancelled'
                    };
                    
                    for (const [status, percentage] of Object.entries(statusDistribution)) {
                        if (percentage > 0) {
                            labels.push(statusLabels[status] || status);
                            data.push(percentage);
                            backgroundColor.push(statusColors[status] || '#6B7280');
                        }
                    }
                    
                    statusChart = new Chart(statusCanvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: data,
                                backgroundColor: backgroundColor,
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '50%',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + '%';
                                        }
                                    }
                                }
                            }
                        }
                    });
                    
                    console.log('Status chart created successfully');
                } catch (error) {
                    console.error('Error creating status chart:', error);
                }
                
                try {
                    // Machine Utilization Chart (simplified)
                    utilizationChart = new Chart(utilizationCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: @json(collect($machineStatuses ?? [])->pluck('name')),
                            datasets: [{
                                label: 'Utilization %',
                                data: @json(collect($machineStatuses ?? [])->pluck('utilization')),
                                backgroundColor: '#10B981',
                                borderColor: '#047857',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, max: 100 }
                            },
                            plugins: {
                                legend: { display: false }
                            }
                        }
                    });
                    
                    console.log('Utilization chart created successfully');
                } catch (error) {
                    console.error('Error creating utilization chart:', error);
                }
            }
            
            // Initialize when Overview tab becomes active
            document.addEventListener('click', function(e) {
                if (e.target && e.target.textContent && e.target.textContent.trim() === 'Overview') {
                    setTimeout(initializeCharts, 100);
                }
            });
            
            // Initialize on page load if Overview tab is already active
            document.addEventListener('DOMContentLoaded', function() {
                if ('{{ $activeTab }}' === 'overview') {
                    setTimeout(initializeCharts, 500);
                }
            });
        </script>
    @endif

    @if($activeTab === 'pivot-table')
        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Pivot Table Analysis</h2>
            <p class="text-gray-600 dark:text-gray-400">Pivot table content will be displayed here...</p>
        </div>
    @endif

    @if($activeTab === 'analytics')
        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Production Analytics</h2>
            <p class="text-gray-600 dark:text-gray-400">Analytics content will be displayed here...</p>
        </div>
    @endif

    @if($activeTab === 'work-order-details')
        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6 shadow-sm">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Work Order Details</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-slate-600">
                            <th class="text-left py-2 px-3 text-gray-900 dark:text-gray-100 font-medium text-xs">Work Order</th>
                            <th class="text-left py-2 px-3 text-gray-900 dark:text-gray-100 font-medium text-xs">Status</th>
                            <th class="text-left py-2 px-3 text-gray-900 dark:text-gray-100 font-medium text-xs">Part Number</th>
                            <th class="text-left py-2 px-3 text-gray-900 dark:text-gray-100 font-medium text-xs">Machine</th>
                            <th class="text-left py-2 px-3 text-gray-900 dark:text-gray-100 font-medium text-xs">Operator</th>
                            <th class="text-left py-2 px-3 text-gray-900 dark:text-gray-100 font-medium text-xs">Progress</th>
                            <th class="text-left py-2 px-3 text-gray-900 dark:text-gray-100 font-medium text-xs">OK</th>
                            <th class="text-left py-2 px-3 text-gray-900 dark:text-gray-100 font-medium text-xs">KO</th>
                            <th class="text-left py-2 px-3 text-gray-900 dark:text-gray-100 font-medium text-xs">Yield</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($workOrders ?? [] as $wo)
                            <tr class="border-b border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="py-2 px-3 font-semibold text-gray-900 dark:text-gray-100 text-sm">WO-{{ str_pad($wo['number'], 4, '0', STR_PAD_LEFT) }}</td>
                                <td class="py-2 px-3">
                                    @php
                                        $statusClass = '';
                                        switch($wo['status']) {
                                            case 'Start':
                                                $statusClass = 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
                                                break;
                                            case 'Assigned':
                                                $statusClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
                                                break;
                                            case 'Hold':
                                                $statusClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
                                                break;
                                            case 'Completed':
                                                $statusClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400';
                                                break;
                                            default:
                                                $statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
                                        }
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                        {{ $wo['status'] }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300 text-sm">{{ $wo['part_number'] }}</td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300 text-sm">{{ $wo['machine'] }}</td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300 text-sm">{{ $wo['operator'] }}</td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300 text-sm">{{ $wo['progress'] }}%</td>
                                <td class="py-2 px-3 text-green-600 dark:text-green-400 font-medium text-sm">{{ $wo['ok'] }}</td>
                                <td class="py-2 px-3 text-red-600 dark:text-red-400 font-medium text-sm">{{ $wo['ko'] }}</td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300 text-sm">{{ $wo['yield'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

<!-- Livewire Integration Script -->
<script>
    // Simple chart management
    window.dashboardCharts = {
        statusChart: null,
        utilizationChart: null,
        initialized: false
    };

    function initializeCharts() {
        console.log('Attempting to initialize charts...');
        
        const statusCanvas = document.getElementById('statusChart');
        const utilizationCanvas = document.getElementById('utilizationChart');
        
        if (!statusCanvas || !utilizationCanvas) {
            console.log('Canvas elements not found');
            return false;
        }
        
        if (typeof Chart === 'undefined') {
            console.log('Chart.js not available');
            return false;
        }

        console.log('Creating charts...');

        // Destroy existing charts
        if (window.dashboardCharts.statusChart) {
            window.dashboardCharts.statusChart.destroy();
            window.dashboardCharts.statusChart = null;
        }
        if (window.dashboardCharts.utilizationChart) {
            window.dashboardCharts.utilizationChart.destroy();
            window.dashboardCharts.utilizationChart = null;
        }

        try {
            // Create Status Distribution Chart
            window.dashboardCharts.statusChart = new Chart(statusCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'In Progress', 'On Hold', 'Quality Check', 'Pending', 'Cancelled'],
                    datasets: [{
                        data: [20.7, 17.3, 14.7, 14.0, 16.7, 16.7],
                        backgroundColor: ['#3B82F6', '#F97316', '#8B5CF6', '#10B981', '#14B8A6', '#F59E0B'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '50%',
                    plugins: { 
                        legend: { display: false }
                    }
                }
            });

            // Create Utilization Chart
            window.dashboardCharts.utilizationChart = new Chart(utilizationCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['LATHE-001', 'MILL-002', 'MILL-001', 'CNC-001', 'ASSEMBLY-001'],
                    datasets: [{
                        label: 'Utilization %',
                        data: [25, 30, 28, 26, 35],
                        backgroundColor: '#8B5CF6'
                    }, {
                        label: 'Yield %',
                        data: [95, 92, 88, 85, 90],
                        backgroundColor: '#10B981'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { 
                        y: { 
                            beginAtZero: true, 
                            max: 100 
                        } 
                    },
                    plugins: { 
                        legend: { display: false } 
                    }
                }
            });
            
            window.dashboardCharts.initialized = true;
            console.log('✅ Charts created successfully!');
            return true;
        } catch (error) {
            console.error('❌ Chart creation error:', error);
            return false;
        }
    }

    // Listen for Livewire navigation events
    document.addEventListener('livewire:navigated', function () {
        console.log('Livewire navigated - checking for charts');
        setTimeout(function() {
            if (document.getElementById('statusChart') && document.getElementById('utilizationChart')) {
                initializeCharts();
            }
        }, 100);
    });

    // Listen for tab clicks specifically
    document.addEventListener('click', function(e) {
        if (e.target && e.target.textContent && e.target.textContent.trim() === 'Overview') {
            console.log('Overview tab clicked - initializing charts');
            setTimeout(function() {
                initializeCharts();
            }, 500);
        }
    });

    // Initialize on page load if we're already on overview tab
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, checking for overview tab');
        setTimeout(function() {
            if (document.getElementById('statusChart') && document.getElementById('utilizationChart')) {
                console.log('Found chart canvases, initializing...');
                initializeCharts();
            }
        }, 1000);
    });
</script>

</x-filament-panels::page>
