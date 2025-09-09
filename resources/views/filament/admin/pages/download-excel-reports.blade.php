<x-filament-panels::page>
<div>
    <div class="space-y-6 bg-white dark:bg-slate-900 text-gray-900 dark:text-white min-h-screen p-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Download Excel Reports</h1>
                <p class="text-gray-600 dark:text-slate-400 mt-1">Export detailed reports for Work Orders, BOMs, and Sales Orders</p>
            </div>
            <div class="flex gap-2 items-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="text-sm font-medium text-green-600 dark:text-green-400">Excel Reports</span>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 dark:border-slate-700">
            <nav class="-mb-px flex space-x-8">
                <button 
                    wire:click="setActiveTab('work-orders')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                        {{ $activeTab === 'work-orders' 
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    Work Orders
                </button>
                <button 
                    wire:click="setActiveTab('boms')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                        {{ $activeTab === 'boms' 
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    BOMs
                </button>
                <button 
                    wire:click="setActiveTab('sales-orders')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                        {{ $activeTab === 'sales-orders' 
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    Sales Orders
                </button>
            </nav>
        </div>

        <!-- Work Orders Tab -->
        @if($activeTab === 'work-orders')
        <div class="bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
            <div class="flex items-center gap-4 mb-6">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Work Order Report</h2>
                    <p class="text-sm text-gray-600 dark:text-slate-400">Export work orders with all related data and columns</p>
                </div>
            </div>

            <div class="grid grid-cols-5 gap-4 mb-6">
                <!-- Date Range -->
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Date Range</label>
                    <div class="flex gap-2">
                        <input type="date" wire:model="woDateFrom" 
                               class="flex-1 rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm" />
                        <span class="flex items-center text-gray-500 px-2">to</span>
                        <input type="date" wire:model="woDateTo" 
                               class="flex-1 rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm" />
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Status</label>
                    <select wire:model="woStatus" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm">
                        <option value="">All Statuses</option>
                        @foreach($this->workOrderStatuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Machine -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Machine</label>
                    <select wire:model="woMachine" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm">
                        <option value="">All Machines</option>
                        @foreach($this->machines as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Operator -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Operator</label>
                    <select wire:model="woOperator" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm">
                        <option value="">All Operators</option>
                        @foreach($this->operators as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-4">
                <button wire:click="downloadWorkOrderReport" 
                        class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg text-white font-medium flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download Excel
                </button>
                <button wire:click="downloadWorkOrderReportCsv" 
                        class="bg-green-600 hover:bg-green-700 px-6 py-2 rounded-lg text-white font-medium flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download CSV
                </button>
                <button wire:click="clearWorkOrderFilters" 
                        class="bg-gray-500 hover:bg-gray-600 px-4 py-2 rounded-lg text-white font-medium transition-colors">
                    Clear Filters
                </button>
            </div>
        </div>
        @endif

        <!-- BOMs Tab -->
        @if($activeTab === 'boms')
        <div class="bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
            <div class="flex items-center gap-4 mb-6">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">BOM Report</h2>
                    <p class="text-sm text-gray-600 dark:text-slate-400">Export BOMs with all related work orders and details</p>
                </div>
            </div>

            <div class="grid grid-cols-6 gap-4 mb-6">
                <!-- Date Range -->
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Date Range</label>
                    <div class="flex gap-2">
                        <input type="date" wire:model="bomDateFrom" 
                               class="flex-1 rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm" />
                        <span class="flex items-center text-gray-500 px-2">to</span>
                        <input type="date" wire:model="bomDateTo" 
                               class="flex-1 rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm" />
                    </div>
                </div>

                <!-- Machine Group -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Machine Group</label>
                    <select wire:model="bomMachineGroup" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm">
                        <option value="">All Groups</option>
                        @foreach($this->machineGroups as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Operator Proficiency -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Operator Proficiency</label>
                    <select wire:model="bomOperatorProficiency" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm">
                        <option value="">All Levels</option>
                        @foreach($this->operatorProficiencies as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Status</label>
                    <select wire:model="bomStatus" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm">
                        <option value="">All Status</option>
                        @foreach($this->bomStatuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Target Completion Date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Target Completion</label>
                    <input type="date" wire:model="bomTargetCompletionDate" 
                           class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm" />
                </div>
            </div>

            <div class="flex gap-4">
                <button wire:click="downloadBomReport" 
                        class="bg-purple-600 hover:bg-purple-700 px-6 py-2 rounded-lg text-white font-medium flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download Excel
                </button>
                <button wire:click="downloadBomReportCsv" 
                        class="bg-green-600 hover:bg-green-700 px-6 py-2 rounded-lg text-white font-medium flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download CSV
                </button>
                <button wire:click="clearBomFilters" 
                        class="bg-gray-500 hover:bg-gray-600 px-4 py-2 rounded-lg text-white font-medium transition-colors">
                    Clear Filters
                </button>
            </div>
        </div>
        @endif

        <!-- Sales Orders Tab -->
        @if($activeTab === 'sales-orders')
        <div class="bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-6">
            <div class="flex items-center gap-4 mb-6">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M8 11v6a2 2 0 002 2h4a2 2 0 002-2v-6M8 11H6a2 2 0 00-2 2v6a2 2 0 002 2h2m8-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2"></path>
                </svg>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Sales Order Report</h2>
                    <p class="text-sm text-gray-600 dark:text-slate-400">Export sales orders with BOMs and work order details</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-6">
                <!-- Date Range -->
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Date Range</label>
                    <div class="flex gap-2">
                        <input type="date" wire:model="soDateFrom" 
                               class="flex-1 rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm" />
                        <span class="flex items-center text-gray-500 px-2">to</span>
                        <input type="date" wire:model="soDateTo" 
                               class="flex-1 rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm" />
                    </div>
                </div>

                <!-- Customer -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Customer</label>
                    <select wire:model="soCustomer" class="w-full rounded-md bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-600 text-gray-900 dark:text-white text-sm">
                        <option value="">All Customers</option>
                        @foreach($this->customers as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-4">
                <button wire:click="downloadSalesOrderReport" 
                        class="bg-green-600 hover:bg-green-700 px-6 py-2 rounded-lg text-white font-medium flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download Excel
                </button>
                <button wire:click="downloadSalesOrderReportCsv" 
                        class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg text-white font-medium flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download CSV
                </button>
                <button wire:click="clearSalesOrderFilters" 
                        class="bg-gray-500 hover:bg-gray-600 px-4 py-2 rounded-lg text-white font-medium transition-colors">
                    Clear Filters
                </button>
            </div>
        </div>
        @endif

        <!-- Information Panel -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-sm text-blue-800 dark:text-blue-300">
                    <p class="font-medium mb-1">Report Information:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Work Order Report:</strong> Includes all work order data with part numbers, machines, operators, quantities, progress, and status information.</li>
                        <li><strong>BOM Report:</strong> Shows all BOMs with their related work orders, providing complete production planning visibility.</li>
                        <li><strong>Sales Order Report:</strong> Combines sales orders, BOMs, and work orders for complete order-to-delivery tracking.</li>
                    </ul>
                    <p class="mt-2 text-xs opacity-75">All reports are filtered by your factory and include comprehensive data for analysis and reporting purposes.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</x-filament-panels::page>
