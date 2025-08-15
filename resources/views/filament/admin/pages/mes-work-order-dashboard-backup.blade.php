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
        <div class="grid grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Status</label>
                <select wire:model.live="filterStatus" class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded px-3 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Statuses</option>
                    <option value="Assigned">Assigned</option>
                    <option value="Start">Start</option>
                    <option value="Hold">Hold</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Machine</label>
                <select wire:model.live="filterMachine" class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded px-3 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Machines</option>
                    @foreach($machines ?? [] as $machine)
                        <option value="{{ $machine['name'] }}">{{ $machine['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Operator</label>
                <select wire:model.live="filterOperator" class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded px-3 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Operators</option>
                    @foreach($operators ?? [] as $operator)
                        <option value="{{ $operator['name'] }}">{{ $operator['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Date Range</label>
                <input type="date" wire:model.live="filterDate" class="w-full bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded px-3 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                @foreach($machines ?? [] as $machine)
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
                                <span class="font-medium @if($machine['status'] === 'RUNNING') text-green-600 dark:text-green-400
                                @else text-red-600 dark:text-red-400 @endif">{{ $machine['status'] }}</span>
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

        <!-- Chart.js and immediate initialization -->
        <script>
            // Load Chart.js dynamically and wait for it
            (function() {
                console.log('🎯 Loading Chart.js for Overview tab...');
                
                // Create script element to load Chart.js
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
                script.async = true;
                
                script.onload = function() {
                    console.log('✅ Chart.js loaded successfully');
                    createCharts();
                };
                
                script.onerror = function() {
                    console.error('❌ Failed to load Chart.js, trying alternative CDN...');
                    // Try alternative CDN
                    const altScript = document.createElement('script');
                    altScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.js';
                    altScript.onload = function() {
                        console.log('✅ Chart.js loaded from alternative CDN');
                        createCharts();
                    };
                    altScript.onerror = function() {
                        console.error('❌ Both Chart.js CDNs failed');
                    };
                    document.head.appendChild(altScript);
                };
                
                document.head.appendChild(script);
                
                function createCharts() {
                    console.log('🚀 Creating charts...');
                    
                    // Wait a bit more to ensure DOM is ready
                    setTimeout(() => {
                        const statusCanvas = document.getElementById('statusChart');
                        const utilizationCanvas = document.getElementById('utilizationChart');
                        
                        if (!statusCanvas || !utilizationCanvas) {
                            console.error('❌ Canvas elements not found');
                            return;
                        }
                        
                        if (typeof Chart === 'undefined') {
                            console.error('❌ Chart constructor not available');
                            return;
                        }
                        
                        console.log('✅ Creating status chart...');
                        // Status Distribution Chart
                        try {
                            new Chart(statusCanvas.getContext('2d'), {
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
                                    plugins: { legend: { display: false } }
                                }
                            });
                            console.log('✅ Status chart created!');
                        } catch (error) {
                            console.error('❌ Status chart error:', error);
                        }
                        
                        console.log('✅ Creating utilization chart...');
                        // Machine Utilization Chart
                        try {
                            new Chart(utilizationCanvas.getContext('2d'), {
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
                                    plugins: { legend: { display: false } }
                                }
                            });
                            console.log('✅ Utilization chart created!');
                        } catch (error) {
                            console.error('❌ Utilization chart error:', error);
                        }
                        
                        console.log('🎉 All charts created successfully!');
                    }, 100);
                }
            })();
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
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-slate-600">
                            <th class="text-left py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">Work Order</th>
                            <th class="text-left py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">Status</th>
                            <th class="text-left py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">Part Number</th>
                            <th class="text-left py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">Machine</th>
                            <th class="text-left py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">Operator</th>
                            <th class="text-left py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">Progress</th>
                            <th class="text-left py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">OK</th>
                            <th class="text-left py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">KO</th>
                            <th class="text-left py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">Yield</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($workOrders ?? [] as $wo)
                            <tr class="border-b border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">WO-{{ str_pad($wo['number'], 4, '0', STR_PAD_LEFT) }}</td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($wo['status'] === 'Start') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                                        @elseif($wo['status'] === 'Assigned') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                                        @elseif($wo['status'] === 'Hold') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                        @elseif($wo['status'] === 'Completed') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400 @endif">
                                        {{ $wo['status'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-gray-700 dark:text-gray-300">{{ $wo['part_number'] }}</td>
                                <td class="py-3 px-4 text-gray-700 dark:text-gray-300">{{ $wo['machine'] }}</td>
                                <td class="py-3 px-4 text-gray-700 dark:text-gray-300">{{ $wo['operator'] }}</td>
                                <td class="py-3 px-4 text-gray-700 dark:text-gray-300">{{ $wo['progress'] }}%</td>
                                <td class="py-3 px-4 text-green-600 dark:text-green-400 font-medium">{{ $wo['ok'] }}</td>
                                <td class="py-3 px-4 text-red-600 dark:text-red-400 font-medium">{{ $wo['ko'] }}</td>
                                <td class="py-3 px-4 text-gray-700 dark:text-gray-300">{{ $wo['yield'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<!-- Global Chart Management -->
<script>
    // Global Chart Management
    window.dashboardCharts = {
        statusChart: null,
        utilizationChart: null,
        statusData: @json($statusDistribution ?? [])
    };

    function destroyExistingCharts() {
        if (window.dashboardCharts.statusChart) {
            window.dashboardCharts.statusChart.destroy();
            window.dashboardCharts.statusChart = null;
        }
        if (window.dashboardCharts.utilizationChart) {
            window.dashboardCharts.utilizationChart.destroy();
            window.dashboardCharts.utilizationChart = null;
        }
    }

    function initializeOverviewCharts() {
        console.log('🎯 Initializing overview charts...');

        // Check if Chart.js is available
        if (typeof Chart === 'undefined') {
            console.error('❌ Chart.js not available');
            return;
        }

        // Find canvas elements
        const statusCanvas = document.getElementById('statusChart');
        const utilizationCanvas = document.getElementById('utilizationChart');

        if (!statusCanvas || !utilizationCanvas) {
            console.log('⏳ Canvas elements not ready yet');
            return;
        }

        console.log('✅ Canvas elements found, creating charts...');

        // Destroy existing charts first
        destroyExistingCharts();

        // Create Status Distribution Chart
        try {
            const statusData = window.dashboardCharts.statusData;
            console.log('📊 Status data:', statusData);

            // Use fallback data if needed
            const finalStatusData = Object.keys(statusData).length > 0 ? statusData : {
                'Completed': 20.7,
                'Start': 17.3,
                'Hold': 14.7,
                'Quality_Check': 14.0,
                'Assigned': 16.7,
                'Cancelled': 16.7
            };

            const labels = Object.keys(finalStatusData).map(key => {
                switch(key) {
                    case 'Start': return 'In Progress';
                    case 'Assigned': return 'Pending';
                    case 'Quality_Check': return 'Quality Check';
                    default: return key;
                }
            });

            const values = Object.values(finalStatusData);
            const colors = ['#3B82F6', '#F97316', '#8B5CF6', '#10B981', '#14B8A6', '#F59E0B'];

            window.dashboardCharts.statusChart = new Chart(statusCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, values.length),
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

            console.log('✅ Status chart created');
        } catch (error) {
            console.error('❌ Status chart error:', error);
        }

        // Create Machine Utilization Chart
        try {
            window.dashboardCharts.utilizationChart = new Chart(utilizationCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['LATHE-001', 'MILL-002', 'MILL-001', 'CNC-001', 'ASSEMBLY-001', 'PRESS-002', 'CNC-002', 'LATHE-002', 'PRESS-001', 'CNC-003'],
                    datasets: [{
                        label: 'Utilization %',
                        data: [25, 30, 28, 26, 35, 32, 40, 30, 22, 45],
                        backgroundColor: '#8B5CF6',
                        borderRadius: 4,
                        maxBarThickness: 25
                    }, {
                        label: 'Yield %',
                        data: [95, 92, 88, 85, 90, 87, 93, 89, 86, 91],
                        backgroundColor: '#10B981',
                        borderRadius: 4,
                        maxBarThickness: 25
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            ticks: { maxRotation: 45, color: '#6B7280' },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                color: '#6B7280',
                                stepSize: 25,
                                callback: function(value) { return value + '%'; }
                            },
                            grid: { color: '#E5E7EB' }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + '%';
                                }
                            }
                        }
                    }
                }
            });

            console.log('✅ Utilization chart created');
        } catch (error) {
            console.error('❌ Utilization chart error:', error);
        }

        console.log('🎉 Charts initialization completed');
    }

    // Initialize charts when page loads and Overview tab is active
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, current tab: {{ $activeTab }}');
        if ('{{ $activeTab }}' === 'overview') {
            // Wait a bit for Chart.js to load
            setTimeout(initializeOverviewCharts, 1000);
        }
        
        // Add click listeners to tab buttons
        const overviewButton = document.querySelector('button[wire\\:click="setActiveTab(\'overview\')"]');
        if (overviewButton) {
            overviewButton.addEventListener('click', function() {
                console.log('🔄 Overview tab clicked');
                // Wait for Livewire to update the DOM
                setTimeout(() => {
                    const statusCanvas = document.getElementById('statusChart');
                    const utilizationCanvas = document.getElementById('utilizationChart');
                    
                    if (statusCanvas && utilizationCanvas) {
                        console.log('🚀 Initializing charts after tab click...');
                        initializeOverviewCharts();
                    } else {
                        console.log('⏳ Retrying chart initialization...');
                        setTimeout(initializeOverviewCharts, 500);
                    }
                }, 500);
            });
        }
    });

    // Backup: Try to initialize charts whenever the overview tab becomes visible
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                const statusChart = document.getElementById('statusChart');
                const utilizationChart = document.getElementById('utilizationChart');
                
                if (statusChart && utilizationChart && !window.dashboardCharts.statusChart) {
                    console.log('📡 Observer detected charts, initializing...');
                    setTimeout(initializeOverviewCharts, 200);
                }
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
</script>

</x-filament-panels::page>
