<x-filament-panels::page>
<div>
    <div class="space-y-6 bg-white dark:bg-slate-900 text-gray-900 dark:text-white min-h-screen p-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">MES Work Order Dashboard</h1>
                <p class="text-gray-600 dark:text-slate-400 mt-1">Manufacturing Execution System - Real-time Production Monitoring</p>
            </div>
            <div class="flex gap-4 items-center">
                <span class="bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-300 px-3 py-1 rounded text-sm">Sample Data</span>
                <span class="text-gray-600 dark:text-slate-400 text-sm">{{ $filteredCount ?? 150 }} of {{ $totalRecords ?? 150 }} records</span>
                <button class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-white flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    Upload ProdStream Data
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
            <div class="flex items-center gap-4 mb-4">
                <svg class="w-5 h-5 text-gray-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Filters</h3>
                <span class="ml-auto text-sm text-gray-600 dark:text-slate-400">
                    {{ $filteredCount ?? 150 }} of {{ $totalRecords ?? 150 }} records
                    @if(!empty($startDate) || !empty($endDate))
                        <span class="text-xs text-blue-600 dark:text-blue-400">
                            ({{ $startDate ?? 'Start' }} - {{ $endDate ?? 'End' }})
                        </span>
                    @endif
                </span>
            </div>

            <div class="grid grid-cols-5 gap-4">
                <!-- Date Range Filter -->
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Date Range</label>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <input type="date" wire:model.live="startDate" 
                                   class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm"
                                   value="{{ date('Y-m-01') }}" />
                        </div>
                        <span class="flex items-center text-gray-500 dark:text-slate-400 px-2">to</span>
                        <div class="flex-1">
                            <input type="date" wire:model.live="endDate" 
                                   class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm"
                                   value="{{ date('Y-m-d') }}" />
                        </div>
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Status</label>
                    <select wire:model.live="selectedStatus" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white">
                        <option value="">All Statuses</option>
                        <option value="Assigned">Assigned</option>
                        <option value="Start">Start</option>
                        <option value="Completed">Completed</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Machine Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Machine</label>
                    <select wire:model.live="selectedMachine" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white">
                        <option value="">All Machines</option>
                        @if(!empty($machines))
                            @foreach($machines as $machine)
                                <option value="{{ $machine['name'] }}">{{ $machine['name'] }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Operator Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Operator</label>
                    <select wire:model.live="selectedOperator" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white">
                        <option value="">All Operators</option>
                        @if(!empty($operators))
                            @foreach($operators as $operator)
                                <option value="{{ $operator['id'] }}">{{ $operator['name'] }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>

            <!-- Quick Date Range Buttons -->
            <div class="flex gap-2 mt-3">
                <button wire:click="setDateRange('today')" class="px-3 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 rounded-full hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                    Today
                </button>
                <button wire:click="setDateRange('week')" class="px-3 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 rounded-full hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                    This Week
                </button>
                <button wire:click="setDateRange('month')" class="px-3 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 rounded-full hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                    This Month
                </button>
                <button wire:click="setDateRange('quarter')" class="px-3 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 rounded-full hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                    This Quarter
                </button>
                <button wire:click="setDateRange('year')" class="px-3 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 rounded-full hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                    This Year
                </button>
                <button wire:click="setDateRange('all')" class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                    All Time
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-4 gap-4">
            <!-- Total Work Orders -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-slate-400">Total Work Orders</span>
                        </div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $totalWorkOrders ?? 150 }}</p>
                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">{{ $completedOrders ?? 0 }} completed, {{ $inProgressOrders ?? 0 }} started</p>
                    </div>
                </div>
            </div>

            <!-- Overall Yield -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-slate-400">Overall Yield</span>
                        </div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $overallYield ?? '93.10%' }}</p>
                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">OK: {{ number_format($totalOkQty ?? 0) }} | NG: {{ number_format($totalKoQty ?? 0) }}</p>
                    </div>
                </div>
            </div>

            <!-- Completion Rate -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-slate-400">Completion Rate</span>
                        </div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $completionRate ?? '30.91%' }}</p>
                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">{{ number_format($totalOkQty ?? 0) }} of {{ number_format($totalPlannedQty ?? 0) }} planned</p>
                    </div>
                </div>
            </div>

            <!-- Defect Rate -->
            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-slate-400">Defect Rate</span>
                        </div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $defectRate ?? '6.90%' }}</p>
                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Quality control monitoring</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg">
            <nav class="flex space-x-1 p-2 border-b border-gray-200 dark:border-slate-700">
                <button wire:click="setActiveTab('real-time')" class="@if($activeTab === 'real-time') bg-blue-600 text-white @else text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Real-time
                </button>
                <button wire:click="setActiveTab('overview')" class="@if($activeTab === 'overview') bg-blue-600 text-white @else text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Overview
                </button>
                <button wire:click="setActiveTab('pivot')" class="@if($activeTab === 'pivot') bg-blue-600 text-white @else text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Pivot Table
                </button>
                <button wire:click="setActiveTab('analytics')" class="@if($activeTab === 'analytics') bg-blue-600 text-white @else text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Analytics
                </button>
                <button wire:click="setActiveTab('details')" class="@if($activeTab === 'details') bg-blue-600 text-white @else text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-700 @endif px-4 py-2 rounded-md text-sm font-medium transition-all">
                    Work Order Details
                </button>
            </nav>

            <!-- Tab Content Container -->
            <div class="p-6">
                @if($activeTab === 'real-time')
                    <div class="space-y-6">
                        <!-- Real-time Production Status Header -->
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Real-time Production Status</h3>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                                    <span class="text-sm text-gray-600 dark:text-slate-400">Live</span>
                                </div>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-slate-400">Last updated: {{ now()->format('H:i:s') }}</span>
                        </div>

                        <!-- Real-time Cards -->
                        <div class="grid grid-cols-4 gap-4">
                            <!-- Active Orders -->
                            <div class="bg-white rounded-lg p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-600">Active Orders</span>
                                        <p class="text-3xl font-bold text-blue-600 mt-1">{{ $activeOrders ?? 26 }}</p>
                                    </div>
                                    <div class="bg-blue-100 p-3 rounded-full">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Assigned Orders -->
                            <div class="bg-white rounded-lg p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-600">Assigned Orders</span>
                                        <p class="text-3xl font-bold text-orange-600 mt-1">{{ $pendingOrders ?? 25 }}</p>
                                    </div>
                                    <div class="bg-orange-100 p-3 rounded-full">
                                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Completed Today -->
                            <div class="bg-white rounded-lg p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-600">Completed Today</span>
                                        <p class="text-3xl font-bold text-green-600 mt-1">{{ $completedToday ?? 0 }}</p>
                                    </div>
                                    <div class="bg-green-100 p-3 rounded-full">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Average Utilization -->
                            <div class="bg-white rounded-lg p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-600">Avg. Utilization</span>
                                        <p class="text-3xl font-bold text-purple-600 mt-1">{{ $avgUtilization ?? '63%' }}</p>
                                    </div>
                                    <div class="bg-purple-100 p-3 rounded-full">
                                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Machine Status Monitor -->
                        <div class="bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7"></path>
                                </svg>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Machine Status Monitor</h3>
                            </div>

                            <div class="grid grid-cols-5 gap-4">
                                @if(!empty($machineStatuses))
                                    @foreach(array_slice($machineStatuses, 0, 10) as $machine)
                                        <div class="bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $machine['name'] ?? 'Unknown' }}</h4>
                                                <div class="flex items-center gap-1">
                                                    @if(($machine['status'] ?? 'IDLE') === 'RUNNING')
                                                        <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                                        <span class="text-xs text-green-600 dark:text-green-400">RUNNING</span>
                                                    @elseif(($machine['status'] ?? 'IDLE') === 'MAINTENANCE')
                                                        <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                                        <span class="text-xs text-red-600 dark:text-red-400">MAINTENANCE</span>
                                                    @else
                                                        <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                                                        <span class="text-xs text-orange-600 dark:text-orange-400">IDLE</span>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="flex justify-between text-xs text-gray-600 dark:text-slate-300 mb-1">
                                                    <span>Utilization</span>
                                                    <span>{{ $machine['utilization'] ?? 0 }}%</span>
                                                </div>
                                                <div class="w-full bg-gray-300 dark:bg-slate-600 rounded-full h-2">
                                                    <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $machine['utilization'] ?? 0 }}%"></div>
                                                </div>
                                            </div>
                                            
                                            @if(isset($machine['current_wo']))
                                                <div class="text-xs">
                                                    <span class="text-gray-600 dark:text-slate-400">Current:</span>
                                                    <br>
                                                    <span class="text-gray-900 dark:text-white">{{ $machine['current_wo'] }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    @foreach(['CNC-001', 'CNC-002', 'CNC-003', 'MILL-001', 'MILL-002', 'LATHE-001', 'LATHE-002', 'PRESS-001', 'PRESS-002', 'ASSEMBLY-001'] as $machineName)
                                        <div class="bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $machineName }}</h4>
                                                <div class="flex items-center gap-1">
                                                    @php
                                                        $statuses = ['RUNNING', 'IDLE', 'MAINTENANCE'];
                                                        $status = $statuses[array_rand($statuses)];
                                                        $utilization = rand(20, 95);
                                                    @endphp
                                                    @if($status === 'RUNNING')
                                                        <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                                        <span class="text-xs text-green-600 dark:text-green-400">RUNNING</span>
                                                    @elseif($status === 'MAINTENANCE')
                                                        <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                                        <span class="text-xs text-red-600 dark:text-red-400">MAINTENANCE</span>
                                                    @else
                                                        <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                                                        <span class="text-xs text-orange-600 dark:text-orange-400">IDLE</span>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="flex justify-between text-xs text-gray-600 dark:text-slate-300 mb-1">
                                                    <span>Utilization</span>
                                                    <span>{{ $utilization }}%</span>
                                                </div>
                                                <div class="w-full bg-gray-300 dark:bg-slate-600 rounded-full h-2">
                                                    <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $utilization }}%"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="text-xs">
                                                <span class="text-gray-600 dark:text-slate-400">Current:</span>
                                                <br>
                                                <span class="text-gray-900 dark:text-white">WO-2024-{{ str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT) }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if($activeTab === 'overview')
                    <!-- Hidden element containing chart config for JavaScript -->
                    <script type="application/json" id="chart-config-data">@json($this->getChartConfig())</script>
                    
                    <div class="space-y-6">
                        <!-- Debug Info (remove in production) -->
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 text-xs">
                            <h4 class="font-semibold text-yellow-800 dark:text-yellow-300 mb-2">Debug Info:</h4>
                            <div class="grid grid-cols-2 gap-4 text-yellow-700 dark:text-yellow-400">
                                <div>
                                    <strong>Status Distribution:</strong><br>
                                    Completed: {{ $statusDistribution['Completed'] ?? 0 }}<br>
                                    Start: {{ $statusDistribution['Start'] ?? 0 }}<br>
                                    Assigned: {{ $statusDistribution['Assigned'] ?? 0 }}<br>
                                    Hold: {{ $statusDistribution['Hold'] ?? ($statusDistribution['On Hold'] ?? 0) }}
                                </div>
                                <div>
                                    <strong>Machine Data:</strong><br>
                                    @php $machineData = $this->getMachineUtilizationData(); @endphp
                                    Machines: {{ count($machineData['machines'] ?? []) }}<br>
                                    {{ implode(', ', array_slice($machineData['machines'] ?? [], 0, 3)) }}{{ count($machineData['machines'] ?? []) > 3 ? '...' : '' }}
                                </div>
                            </div>
                        </div>

                        <!-- Charts Row -->
                        <div class="grid grid-cols-2 gap-6">
                            <!-- Status Distribution Chart -->
                            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6" style="min-height: 400px;">
                                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Status Distribution</h3>
                                <div style="width: 100%; height: 320px; position: relative; border: 1px solid #ccc; background: #f0f0f0;">
                                    <canvas id="statusChart" width="400" height="320" style="width: 100%; height: 100%; display: block; background: rgba(255,0,0,0.1);"></canvas>
                                </div>
                            </div>

                            <!-- Machine Utilization Chart -->
                            <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6" style="min-height: 400px;">
                                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Machine Utilization</h3>
                                <div style="width: 100%; height: 320px; position: relative; border: 1px solid #ccc; background: #f0f0f0;">
                                    <canvas id="utilizationChart" width="400" height="320" style="width: 100%; height: 100%; display: block; background: rgba(0,255,0,0.1);"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

@if($activeTab === 'pivot')
    <div class="max-w-7xl mx-auto p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow">
        <livewire:work-order-pivot-table 
            :filterDateFrom="$startDate"
            :filterDateTo="$endDate"
            :filterStatus="$selectedStatus"
            :filterMachine="$selectedMachine"
            :filterOperator="$selectedOperator"
        />
    </div>
@endif

                @if($activeTab === 'analytics')
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Advanced Analytics</h3>
                            <div class="text-center py-8">
                                <p class="text-gray-600 dark:text-slate-400">Analytics dashboard coming soon...</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($activeTab === 'details')
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Work Order Details</h3>
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-2">
                                        <label class="text-sm text-gray-600 dark:text-slate-300">Show:</label>
                                        <select wire:change="changePerPage($event.target.value)" class="text-xs rounded border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white">
                                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                        </select>
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-slate-300">
                                        Showing {{ (($currentPage - 1) * $perPage) + 1 }} to {{ min($currentPage * $perPage, $filteredCount) }} of {{ $filteredCount }} entries
                                    </div>
                                </div>
                            </div>
                            @if(!empty($workOrders))
                                <div class="overflow-x-auto" wire:loading.class="opacity-50" wire:target="nextPage,previousPage,goToPage,changePerPage">
                                    <div wire:loading wire:target="nextPage,previousPage,goToPage,changePerPage" class="absolute inset-0 bg-white dark:bg-slate-800 bg-opacity-75 flex items-center justify-center z-10">
                                        <div class="text-gray-600 dark:text-slate-300">Loading...</div>
                                    </div>
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                                        <thead class="bg-gray-50 dark:bg-slate-700">
                                            <tr>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-600 dark:text-slate-300 uppercase tracking-wider">WO Number</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-600 dark:text-slate-300 uppercase tracking-wider">Status</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-600 dark:text-slate-300 uppercase tracking-wider">Machine</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-600 dark:text-slate-300 uppercase tracking-wider">Operator</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-600 dark:text-slate-300 uppercase tracking-wider">OK</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-600 dark:text-slate-300 uppercase tracking-wider">KO</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-600 dark:text-slate-300 uppercase tracking-wider">Progress</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-600 dark:text-slate-300 uppercase tracking-wider">Yield</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                                            @foreach($workOrders as $wo)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                    <td class="px-2 py-1 whitespace-nowrap">
                                                        <a href="http://prodstream_v1.1.test/admin/{{ $wo['factory_id'] ?? 1 }}/work-orders/{{ $wo['id'] ?? 1 }}" 
                                                           class="text-blue-600 dark:text-blue-400 text-xs font-medium hover:underline" 
                                                           target="_blank">
                                                            {{ $wo['wo_number'] ?? 'N/A' }}
                                                        </a>
                                                    </td>
                                                    <td class="px-2 py-1 whitespace-nowrap">
                                                        <span class="inline-flex px-1 py-0.5 text-xs font-medium rounded 
                                                            @if(($wo['status'] ?? '') === 'Completed') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300
                                                            @elseif(($wo['status'] ?? '') === 'Start') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300
                                                            @elseif(($wo['status'] ?? '') === 'Assigned') bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300
                                                            @elseif(($wo['status'] ?? '') === 'On Hold') bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300
                                                            @else bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-300 @endif">
                                                            {{ $wo['status'] ?? 'Unknown' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-2 py-1 whitespace-nowrap text-xactiveTabactiveTabs text-gray-900 dark:text-white">
                                                        {{ $wo['machine'] ?? 'N/A' }}
                                                    </td>
                                                    <td class="px-2 py-1 whitespace-nowrap text-xs text-gray-900 dark:text-white">
                                                        {{ $wo['operator'] ?? 'Unassigned' }}
                                                    </td>
                                                                                                        <td class="px-2 py-1 whitespace-nowrap text-xs text-green-800 dark:text-green-400 font-semibold">
                                                                                                            {{ $wo['ok'] ?? 0 }}
                                                                                                        </td>
                                                                                                        <td class="px-2 py-1 whitespace-nowrap text-xs text-orange-700 dark:text-red-400 font-semibold">
                                                                                                            {{ $wo['ko'] ?? 0 }}
                                                                                                        </td>
                                                    <td class="px-2 py-1 whitespace-nowrap text-xs text-gray-900 dark:text-white">
                                                        {{ $wo['progress'] ?? '0' }}%
                                                    </td>
                                                    <td class="px-2 py-1 whitespace-nowrap">
                                                        <span class="text-xs font-medium
                                                            @if(($wo['yield'] ?? 0) >= 90) text-green-600 dark:text-green-400
                                                            @elseif(($wo['yield'] ?? 0) >= 75) text-yellow-600 dark:text-yellow-400
                                                            @else text-red-600 dark:text-red-400 @endif">
                                                            {{ $wo['yield'] ?? '0' }}%
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination Controls -->
                                @if($totalPages > 1)
                                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-slate-600">
                                        <div class="flex items-center gap-2">
                                            <button wire:click="previousPage" 
                                                    {{ $currentPage <= 1 ? 'disabled' : '' }}
                                                    class="px-3 py-1 text-xs bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 rounded hover:bg-gray-200 dark:hover:bg-slate-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                                Previous
                                            </button>
                                            
                                            @php
                                                $start = max(1, $currentPage - 2);
                                                $end = min($totalPages, $currentPage + 2);
                                            @endphp
                                            
                                            @if($start > 1)
                                                <button wire:click="goToPage(1)" class="px-2 py-1 text-xs bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 rounded hover:bg-gray-200 dark:hover:bg-slate-600">1</button>
                                                @if($start > 2)
                                                    <span class="text-gray-500 dark:text-slate-400 text-xs">...</span>
                                                @endif
                                            @endif
                                            
                                            @for($i = $start; $i <= $end; $i++)
                                                <button wire:click="goToPage({{ $i }})" 
                                                        class="px-2 py-1 text-xs rounded {{ $i == $currentPage ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-600' }}">
                                                    {{ $i }}
                                                </button>
                                            @endfor
                                            
                                            @if($end < $totalPages)
                                                @if($end < $totalPages - 1)
                                                    <span class="text-gray-500 dark:text-slate-400 text-xs">...</span>
                                                @endif
                                                <button wire:click="goToPage({{ $totalPages }})" class="px-2 py-1 text-xs bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 rounded hover:bg-gray-200 dark:hover:bg-slate-600">{{ $totalPages }}</button>
                                            @endif
                                            
                                            <button wire:click="nextPage" 
                                                    {{ $currentPage >= $totalPages ? 'disabled' : '' }}
                                                    class="px-3 py-1 text-xs bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 rounded hover:bg-gray-200 dark:hover:bg-slate-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                                Next
                                            </button>
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-slate-300">
                                            Page {{ $currentPage }} of {{ $totalPages }}
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="text-center py-8">
                                    <p class="text-gray-600 dark:text-slate-400">No work orders found with current filters.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script>
        // Global variables
        window.statusChart = null;
        window.utilizationChart = null;

        // Prepare data from PHP - this will update with Livewire
        let chartConfig = @json($this->getChartConfig());

        console.log('📊 Chart Config:', chartConfig);

        // Add fallback data if chartConfig is empty or invalid
        if (!chartConfig || !chartConfig.statusData) {
            console.log('⚠️ Chart config is invalid, using fallback data');
            chartConfig = {
                statusData: {
                    completed: 1,
                    start: 1, 
                    assigned: 1,
                    hold: 0
                },
                machineData: {
                    machines: ['Machine 1', 'Machine 2', 'Machine 3'],
                    utilization: [75, 45, 60]
                }
            };
        }

        // Function to update chart config from DOM element (for Livewire updates)
        function updateChartConfig() {
            const configElement = document.getElementById('chart-config-data');
            if (configElement) {
                try {
                    const newConfig = JSON.parse(configElement.textContent);
                    console.log('🔄 Previous config:', chartConfig);
                    chartConfig = newConfig;
                    console.log('📊 Chart config updated from DOM:', chartConfig);
                    return true;
                } catch (e) {
                    console.error('❌ Error parsing chart config from DOM:', e);
                    return false;
                }
            } else {
                console.log('⚠️ Chart config element not found');
                return false;
            }
        }

        function destroyExistingCharts() {
            if (window.statusChart) {
                window.statusChart.destroy();
                window.statusChart = null;
            }
            if (window.utilizationChart) {
                window.utilizationChart.destroy();
                window.utilizationChart = null;
            }
        }

        function createStatusChart() {
            console.log('🎯 createStatusChart() called');
            const canvas = document.getElementById('statusChart');
            if (!canvas) {
                console.error('❌ Status chart canvas not found');
                return;
            }

            console.log('🎨 Status Canvas Found:', canvas);
            console.log('🎨 Status Canvas Dimensions:', {
                width: canvas.width,
                height: canvas.height,
                clientWidth: canvas.clientWidth,
                clientHeight: canvas.clientHeight,
                offsetWidth: canvas.offsetWidth,
                offsetHeight: canvas.offsetHeight,
                style: canvas.style.cssText
            });

            // Ensure canvas is visible and has proper dimensions
            canvas.style.display = 'block';
            canvas.style.width = '100%';
            canvas.style.height = '320px';
            
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                console.error('❌ Could not get canvas context');
                return;
            }
            
            // Destroy existing chart
            if (window.statusChart) {
                window.statusChart.destroy();
            }
            
            // Destroy existing chart
            if (window.statusChart) {
                window.statusChart.destroy();
            }
            
            // Wait a moment, then try Chart.js
            setTimeout(() => {
                console.log('🎯 Now creating Chart.js instance...');
                
                try {
                    // Create chart immediately
                    const { completed, start, assigned, hold } = chartConfig.statusData || {};
                    const data = [completed || 0, start || 0, assigned || 0, hold || 0];
                    const labels = ['Completed', 'Start', 'Assigned', 'Hold'];
                    const colors = ['#10B981', '#3B82F6', '#F59E0B', '#EF4444'];

                    console.log('🎯 Chart Creation - Raw data from chartConfig:', {
                        statusData: chartConfig.statusData,
                        data: data,
                        labels: labels
                    });

                    // Filter out zero values for display
                    const filteredData = [];
                    const filteredLabels = [];
                    const filteredColors = [];

                    data.forEach((value, index) => {
                        if (value > 0) {
                            filteredData.push(value);
                            filteredLabels.push(labels[index]);
                            filteredColors.push(colors[index]);
                        }
                    });

                    console.log('🎯 Filtered Chart Data:', {
                        data: filteredData,
                        labels: filteredLabels,
                        colors: filteredColors
                    });

                    // If no data, show a default message
                    if (filteredData.length === 0) {
                        console.log('⚠️ No chart data available, using placeholder');
                        filteredData.push(1);
                        filteredLabels.push('No Data');
                        filteredColors.push('#CBD5E1');
                    }

                // Special handling for single slice pie chart
                if (filteredData.length === 1) {
                    console.log('📊 Single status detected, creating single-slice pie chart');
                }

                try {
                    // Clear the test rectangles
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    console.log('🎨 Canvas cleared, creating Chart.js instance...');
                    
                    const chartConfig = {
                        type: 'pie',
                        data: {
                            labels: filteredLabels,
                            datasets: [{
                                data: filteredData,
                                backgroundColor: filteredColors,
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: false,
                            maintainAspectRatio: true,
                            animation: {
                                duration: 1000
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 10,
                                        usePointStyle: true
                                    }
                                }
                            }
                        }
                    };
                    
                    console.log('📊 Creating Chart.js with config:', chartConfig);
                    
                    window.statusChart = new Chart(ctx, chartConfig);
                    
                    console.log('✅ Chart.js instance created:', window.statusChart);
                    
                    // Check if chart rendered
                    setTimeout(() => {
                        console.log('🎨 Chart render check - Canvas data URL length:', canvas.toDataURL().length);
                    }, 1500);
                    
                } catch (error) {
                    console.error('❌ Error creating status chart:', error);
                    console.error('❌ Error stack:', error.stack);
                }
                
                } catch (error) {
                    console.error('❌ Error in status chart creation:', error);
                }
            }, 100); // Wait 100ms then try Chart.js
        }

        function createUtilizationChart() {
            console.log('🔧 createUtilizationChart() called');
            const canvas = document.getElementById('utilizationChart');
            if (!canvas) {
                console.error('❌ Utilization chart canvas not found');
                return;
            }

            console.log('🎨 Utilization Canvas Dimensions:', {
                width: canvas.width,
                height: canvas.height,
                clientWidth: canvas.clientWidth,
                clientHeight: canvas.clientHeight,
                offsetWidth: canvas.offsetWidth,
                offsetHeight: canvas.offsetHeight
            });

            // Ensure canvas is visible and has proper dimensions
            canvas.style.display = 'block';
            canvas.style.width = '100%';
            canvas.style.height = '320px';

            const ctx = canvas.getContext('2d');
            
            const machineData = chartConfig.machineData || {};
            console.log('🔧 Machine Data:', machineData);

            if (!machineData.machines || machineData.machines.length === 0) {
                console.log('⚠️ No machine data to display, using placeholder');
                // Create placeholder data
                machineData.machines = ['Machine 1', 'Machine 2', 'Machine 3'];
                machineData.utilization = [0, 0, 0];
            }

            try {
                console.log('🔧 Creating utilization chart with data:', {
                    machines: machineData.machines,
                    utilization: machineData.utilization
                });
                
                window.utilizationChart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: machineData.machines,
                        datasets: [{
                            label: 'Utilization %',
                            data: machineData.utilization,
                            backgroundColor: machineData.utilization.map(value => {
                                if (value > 80) return '#10B981';
                                if (value > 50) return '#F59E0B';
                                if (value > 0) return '#3B82F6';
                                return '#EF4444';
                            }),
                            borderWidth: 1,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: false,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `Utilization: ${context.parsed.y}%`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    maxRotation: 45
                                }
                            },
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        }
                    }
                });
                
                console.log('✅ Utilization chart created successfully');
                console.log('🎨 Final Canvas State:', {
                    width: canvas.width,
                    height: canvas.height,
                    display: canvas.style.display,
                    chart: window.utilizationChart
                });
            } catch (error) {
                console.error('❌ Error creating utilization chart:', error);
            }
        }

        function initCharts() {
            console.log('🚀 Initializing charts...');
            
            // Check if Chart.js is available
            if (typeof Chart === 'undefined') {
                console.error('❌ Chart.js is not loaded!');
                setTimeout(initCharts, 1000); // Try again in 1 second
                return;
            }
            
            console.log('✅ Chart.js is available:', Chart.version);
            
            // Check if charts containers exist (means we're on overview tab)
            const statusChart = document.getElementById('statusChart');
            const utilizationChart = document.getElementById('utilizationChart');
            
            if (!statusChart || !utilizationChart) {
                console.log('📋 Chart containers not found, probably not on overview tab');
                return;
            }

            console.log('✅ Chart containers found, proceeding with chart initialization');

            // Destroy existing charts
            destroyExistingCharts();

            // Create new charts with a small delay to ensure DOM is ready
            setTimeout(() => {
                createStatusChart();
                createUtilizationChart();
            }, 100);
        }

        // Initialize charts when page loads (only if on overview tab)
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📊 Dashboard loaded');
            setTimeout(initCharts, 500);
        });

        // Initialize charts when user clicks overview tab
        document.addEventListener('click', function(e) {
            if (e.target && e.target.textContent && e.target.textContent.trim() === 'Overview') {
                console.log('🔄 Overview tab clicked');
                setTimeout(initCharts, 300);
            }
        });

        // Reinitialize on Livewire updates (this should update charts when filters change)
        document.addEventListener('livewire:updated', function() {
            console.log('🔄 Livewire updated event fired');
            
            // Update chart config from the hidden DOM element
            updateChartConfig();
            
            // Check if we're on the overview tab before recreating charts
            const statusChart = document.getElementById('statusChart');
            const utilizationChart = document.getElementById('utilizationChart');
            
            if (!statusChart || !utilizationChart) {
                console.log('� Not on overview tab during Livewire update, skipping chart recreation');
                return;
            }
            
            console.log('🔄 On overview tab, recreating charts with new data');
            
            // Force recreate charts with a longer delay to ensure DOM is updated
            setTimeout(function() {
                console.log('🔄 Starting chart recreation process...');
                destroyExistingCharts();
                
                // Reinitialize with the updated data
                setTimeout(function() {
                    createStatusChart();
                    createUtilizationChart();
                }, 100);
            }, 200);
        });

        // Also listen for other Livewire events
        document.addEventListener('livewire:load', function() {
            console.log('🔄 Livewire load event fired');
        });

        // Listen for filter changes specifically - try different selectors
        document.addEventListener('change', function(e) {
            console.log('🔍 Change event detected on element:', e.target);
            if (e.target && e.target.closest('select')) {
                console.log('🔍 Filter select changed:', e.target.value);
                setTimeout(function() {
                    const configUpdated = updateChartConfig();
                    if (configUpdated && document.getElementById('statusChart')) {
                        console.log('🔄 Filter change detected, recreating charts');
                        destroyExistingCharts();
                        setTimeout(function() {
                            createStatusChart();
                            createUtilizationChart();
                        }, 300);
                    }
                }, 500);
            }
        });

        // Listen for wire:model updates (Livewire v3)
        document.addEventListener('livewire:commit', function(e) {
            console.log('🔄 Livewire commit event fired:', e);
        });

        // ===========================
        // PIVOT TABLE DRAG & DROP FUNCTIONS
        // ===========================
        let pivotDraggedField = null;
        let pivotDraggedLabel = null;

        window.dragStart = function(event, fieldKey, fieldLabel) {
            console.log('🎯 Drag started:', fieldKey, fieldLabel);
            pivotDraggedField = fieldKey;
            pivotDraggedLabel = fieldLabel;
            event.dataTransfer.effectAllowed = 'move';
        };

        window.allowDrop = function(event) {
            event.preventDefault();
        };

        window.dragEnter = function(event) {
            event.preventDefault();
            event.currentTarget.classList.add('ring-2', 'ring-blue-500');
        };

        window.dragLeave = function(event) {
            event.preventDefault();
            if (!event.currentTarget.contains(event.relatedTarget)) {
                event.currentTarget.classList.remove('ring-2', 'ring-blue-500');
            }
        };

        window.drop = function(event, zone) {
            event.preventDefault();
            event.currentTarget.classList.remove('ring-2', 'ring-blue-500');
            
            console.log('🎯 Drop event:', zone, pivotDraggedField, pivotDraggedLabel);
            
            if (pivotDraggedField && pivotDraggedLabel) {
                // Find the Livewire component and call the method
                const component = event.target.closest('[wire\\:id]');
                console.log('🔍 Found component:', component);
                
                if (component && window.Livewire) {
                    const componentId = component.getAttribute('wire:id');
                    console.log('📡 Calling Livewire method with ID:', componentId);
                    
                    try {
                        window.Livewire.find(componentId).call('addField', zone, pivotDraggedField, pivotDraggedLabel);
                        console.log('✅ Livewire method called successfully');
                    } catch (error) {
                        console.error('❌ Error calling Livewire method:', error);
                    }
                }
                pivotDraggedField = null;
                pivotDraggedLabel = null;
            }
        };
    </script>
    @endpush
</div>
</x-filament-panels::page>