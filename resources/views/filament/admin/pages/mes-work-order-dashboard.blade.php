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
                            <div class="bg-white dark:bg-slate-800 rounded-lg p-6 border border-gray-200 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-900 dark:text-slate-400">Active Orders</span>
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
                            <div class="bg-white dark:bg-slate-800 rounded-lg p-6 border border-gray-200 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-900 dark:text-slate-400">Assigned Orders</span>
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
                            <div class="bg-white dark:bg-slate-800 rounded-lg p-6 border border-gray-200 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-900 dark:text-slate-400">Completed Today</span>
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
                            <div class="bg-white dark:bg-slate-800 rounded-lg p-6 border border-gray-200 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-900 dark:text-slate-400">Avg. Utilization</span>
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
                                    <canvas id="utilizationChart" width="400" height="320" style="width: 100%; height: 100%; display: block; background: rgba(0,255,0,0.1);"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($activeTab === 'pivot')
                    <div class="max-w-7xl mx-auto p-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow">
        
        <!-- Filter Section with Checkboxes -->
        <div class="mb-6 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg p-4">
            <div class="flex items-center gap-3 mb-4">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">🔍 FILTERS (Select fields to filter)</h3>
            </div>
            
            <!-- Filter Fields Container with Checkboxes -->
            <div class="bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg p-4 min-h-[120px]">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Work Order Number Filter -->
                    <div class="space-y-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   wire:model.live="pivotFilters.workOrderNo" 
                                   class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <div class="ml-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">WorkOrderNo</span>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Machine Filter -->
                    <div class="space-y-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   wire:model.live="pivotFilters.machine" 
                                   class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <div class="ml-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Machine</span>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Operator Filter -->
                    <div class="space-y-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   wire:model.live="pivotFilters.operator" 
                                   class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <div class="ml-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Operator</span>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="space-y-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   wire:model.live="pivotFilters.status" 
                                   class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <div class="ml-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Status</span>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Start Time Filter -->
                    <div class="space-y-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   wire:model.live="pivotFilters.startTime" 
                                   class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <div class="ml-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">StartTime</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Filters Display -->
        @if($this->hasActiveFilters())
        <div class="mb-6 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg p-4">
            <div class="flex items-center gap-3 mb-4">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a1.99 1.99 0 01-1.414.586H7a4 4 0 01-4-4V7a4 4 0 014-4z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">🔍 Active Filters (click to toggle):</h3>
            </div>

            <!-- WorkOrderNo Filter Values -->
            @if($pivotFilters['workOrderNo'] ?? false)
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">WorkOrderNo:</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->getUniqueFieldValues('wo_number') as $value)
                        <button wire:click="toggleFilterValue('workOrderNo', '{{ $value }}')" 
                                class="px-3 py-1 text-xs rounded-full transition-colors
                                @if($this->isFilterValueSelected('workOrderNo', $value))
                                    bg-blue-600 text-white hover:bg-blue-700
                                @else
                                    bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800
                                @endif">
                            {{ $value }}
                        </button>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Machine Filter Values -->
            @if($pivotFilters['machine'] ?? false)
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">Machine:</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->getUniqueFieldValues('machine') as $value)
                        <button wire:click="toggleFilterValue('machine', '{{ $value }}')" 
                                class="px-3 py-1 text-xs rounded-full transition-colors
                                @if($this->isFilterValueSelected('machine', $value))
                                    bg-blue-600 text-white hover:bg-blue-700
                                @else
                                    bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800
                                @endif">
                            {{ $value }}
                        </button>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Operator Filter Values -->
            @if($pivotFilters['operator'] ?? false)
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">Operator:</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->getUniqueFieldValues('operator') as $value)
                        <button wire:click="toggleFilterValue('operator', '{{ $value }}')" 
                                class="px-3 py-1 text-xs rounded-full transition-colors
                                @if($this->isFilterValueSelected('operator', $value))
                                    bg-blue-600 text-white hover:bg-blue-700
                                @else
                                    bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800
                                @endif">
                            {{ $value ?: 'Unassigned' }}
                        </button>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Status Filter Values -->
            @if($pivotFilters['status'] ?? false)
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">Status:</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->getUniqueFieldValues('status') as $value)
                        <button wire:click="toggleFilterValue('status', '{{ $value }}')" 
                                class="px-3 py-1 text-xs rounded-full transition-colors
                                @if($this->isFilterValueSelected('status', $value))
                                    bg-blue-600 text-white hover:bg-blue-700
                                @else
                                    bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800
                                @endif">
                            {{ $value }}
                        </button>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- StartTime Filter Values -->
            @if($pivotFilters['startTime'] ?? false)
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">StartTime:</h4>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->getUniqueFieldValues('start_time', true) as $value)
                        <button wire:click="toggleFilterValue('startTime', '{{ $value }}')" 
                                class="px-3 py-1 text-xs rounded-full transition-colors
                                @if($this->isFilterValueSelected('startTime', $value))
                                    bg-blue-600 text-white hover:bg-blue-700
                                @else
                                    bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800
                                @endif">
                            {{ $value }}
                        </button>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        <!-- Pivot Table Builder -->
        <div class="mb-6">
            <!-- Available Fields -->
            <div class="mb-4">
                <h3 class="text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Available Fields:</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach(['Work Order No', 'Status', 'Machine', 'Operator', 'BOM', 'Part Number', 'Revision', 'Start Time', 'End Time', 'Qty', 'OK Qty', 'KO Qty'] as $field)
                        <div draggable="true" 
                             data-field="{{ $field }}"
                             class="px-3 py-1 bg-blue-500 text-white text-xs rounded-full cursor-move hover:bg-blue-600 transition-colors"
                             ondragstart="dragStart(event)">
                            {{ $field }}
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Drop Zones -->
            <div class="grid grid-cols-3 gap-4">
                <!-- Rows -->
                <div class="border-2 border-dashed border-blue-300 dark:border-blue-600 rounded-lg p-4 min-h-[120px]"
                     ondragover="allowDrop(event)" 
                     ondrop="drop(event, 'rows')"
                     ondragenter="dragEnter(event)"
                     ondragleave="dragLeave(event)"
                     data-section="rows">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                        <span class="font-semibold text-blue-600 dark:text-blue-400">Rows</span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-slate-400 mb-2">Drag fields here</div>
                    <div id="pivot-rows" class="space-y-1">
                        @foreach($pivotRows as $field)
                            <div class="px-2 py-1 bg-blue-600 text-white text-xs rounded flex items-center justify-between">
                                <span>{{ $field }}</span>
                                <button onclick="removeFromSection('{{ $field }}', 'rows')" class="text-white hover:text-red-200">×</button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Columns -->
                <div class="border-2 border-dashed border-green-300 dark:border-green-600 rounded-lg p-4 min-h-[120px]"
                     ondragover="allowDrop(event)" 
                     ondrop="drop(event, 'columns')"
                     ondragenter="dragEnter(event)"
                     ondragleave="dragLeave(event)"
                     data-section="columns">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                        <span class="font-semibold text-green-600 dark:text-green-400">Columns</span>
                        <span class="text-xs text-gray-500">({{ count($pivotColumns ?? []) }} fields)</span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-slate-400 mb-2">Drag fields here</div>
                    
                    <div id="pivot-columns" class="space-y-1">
                        @if(!empty($pivotColumns))
                            @foreach($pivotColumns as $index => $field)
                                <div class="px-2 py-1 bg-green-600 text-white text-xs rounded flex items-center justify-between">
                                    <span>{{ $field }}</span>
                                    <button onclick="removeFromSection('{{ $field }}', 'columns')" class="text-white hover:text-red-200">×</button>
                                </div>
                            @endforeach
                        @else
                            <div class="text-xs text-gray-400 italic">No columns selected</div>
                        @endif
                    </div>
                </div>

                <!-- Values -->
                <div class="border-2 border-dashed border-orange-300 dark:border-orange-600 rounded-lg p-4 min-h-[120px]"
                     ondragover="allowDrop(event)" 
                     ondrop="drop(event, 'values')"
                     ondragenter="dragEnter(event)"
                     ondragleave="dragLeave(event)"
                     data-section="values">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                        <span class="font-semibold text-orange-600 dark:text-orange-400">Values</span>
                        <span class="text-xs text-gray-500">({{ count($pivotValues ?? []) }} fields)</span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-slate-400 mb-2">Drag fields here</div>
                    
                    <div id="pivot-values" class="space-y-1">
                        @if(!empty($pivotValues))
                            @foreach($pivotValues as $index => $field)
                                <div class="px-2 py-1 bg-orange-600 text-white text-xs rounded flex items-center justify-between">
                                    <span>{{ $field }}</span>
                                    <button onclick="removeFromSection('{{ $field }}', 'values')" class="text-white hover:text-red-200">×</button>
                                </div>
                            @endforeach
                        @else
                            <div class="text-xs text-gray-400 italic">No values selected</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-4 flex gap-2">
                <button wire:click="generatePivotTable" 
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                    Generate Pivot Table
                </button>
                <button wire:click="clearPivotTable" 
                        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                    Clear All
                </button>
                <button class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                    Export to Excel
                </button>
            </div>
        </div>

        <!-- Pivot Table Display -->
        @if($pivotGenerated && !empty($pivotData))
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-600">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        @foreach($pivotRows as $rowField)
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-slate-300 uppercase tracking-wider">
                                {{ $rowField }}
                            </th>
                        @endforeach
                        @foreach($pivotData['columns'] ?? [] as $column)
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-slate-300 uppercase tracking-wider">
                                {{ $column }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-600">
                    @foreach($pivotData['data'] ?? [] as $rowKey => $rowData)
                        <tr>
                            @foreach($pivotRows as $rowField)
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $rowData['_row_data'][$rowField] ?? 'N/A' }}
                                </td>
                            @endforeach
                            @foreach($pivotData['columns'] ?? [] as $column)
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    @if(isset($rowData[$column]))
                                        @foreach($pivotValues as $valueField)
                                            <div>{{ $valueField }}: {{ $rowData[$column][$valueField] ?? 0 }}</div>
                                        @endforeach
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

                @endif

                @if($activeTab === 'analytics')
                    <!-- Analytics tab content goes here -->
                    <div class="space-y-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Analytics Dashboard</h3>
                        <p class="text-gray-600 dark:text-slate-400">Analytics content will be added here.</p>
                    </div>
                @endif

                @if($activeTab === 'details')
                    <!-- Work Order Details tab content goes here -->
                    <div class="space-y-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Work Order Details</h3>
                        <p class="text-gray-600 dark:text-slate-400">Detailed work order information will be displayed here.</p>
                    </div>
                @endif
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

        // PIVOT TABLE DRAG & DROP FUNCTIONS
        let draggedField = null;

        function dragStart(event) {
            draggedField = event.target.dataset.field;
            event.dataTransfer.effectAllowed = 'move';
            console.log('Dragging:', draggedField);
        }

        function allowDrop(event) {
            event.preventDefault();
        }

        function dragEnter(event) {
            event.preventDefault();
            event.currentTarget.classList.add('ring-2', 'ring-blue-500');
        }

        function dragLeave(event) {
            event.preventDefault();
            if (!event.currentTarget.contains(event.relatedTarget)) {
                event.currentTarget.classList.remove('ring-2', 'ring-blue-500');
            }
        }

        function drop(event, section) {
            event.preventDefault();
            event.currentTarget.classList.remove('ring-2', 'ring-blue-500');
            
            if (!draggedField) {
                console.log('No dragged field');
                return;
            }

            console.log('Dropping', draggedField, 'into', section);

            // Map display names to field names - MUST match PHP array keys exactly
            const fieldMap = {
                'Work Order No': 'wo_number',
                'Status': 'status',
                'Machine': 'machine', 
                'Operator': 'operator',
                'BOM': 'part_number',
                'Part Number': 'part_number',
                'Revision': 'part_number',
                'Start Time': 'start_time',
                'End Time': 'end_time',
                'Qty': 'qty',
                'OK Qty': 'ok_qty',
                'KO Qty': 'ko_qty'
            };

            const mappedField = fieldMap[draggedField] || draggedField.toLowerCase().replace(' ', '_');
            
            console.log('Mapped field:', draggedField, '->', mappedField);

            // Call Livewire method and force refresh
            @this.call('addToPivotSection', mappedField, section).then(() => {
                console.log('Added to section, forcing refresh...');
                // Force Livewire to refresh the component
                @this.$refresh();
            });
            
            draggedField = null;
        }

        function removeFromSection(field, section) {
            console.log('Removing', field, 'from', section);
            @this.call('removeFromPivotSection', field, section);
        }

        // Listen for Livewire updates to refresh the UI
        document.addEventListener('livewire:updated', function () {
            console.log('Livewire component updated');
        });

        // CHART FUNCTIONS
        // ...existing chart functions...

        // Listen for wire:model updates (Livewire v3)
        document.addEventListener('livewire:commit', function(e) {
            console.log('🔄 Livewire commit event fired:', e);
        });
    </script>
    @endpush
</div>
</x-filament-panels::page>