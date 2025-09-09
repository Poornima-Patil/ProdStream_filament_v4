<div class="space-y-4">
    <!-- Gantt Chart Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    📊 Gantt Chart for {{ $machine->name ?? 'Unknown Machine' }}
                    <span class="text-sm font-normal text-gray-600 dark:text-gray-400">
                        ({{ $machine->assetId }})
                    </span>
                </h3>
            </div>
            
            <!-- View Type Selector -->
            <div class="flex items-center space-x-2 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                <button wire:click="changeView('day')" 
                    class="px-3 py-1 text-sm font-medium rounded-md transition-colors {{ $viewType === 'day' ? 'bg-white dark:bg-gray-600 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                    Day
                </button>
                <button wire:click="changeView('week')" 
                    class="px-3 py-1 text-sm font-medium rounded-md transition-colors {{ $viewType === 'week' ? 'bg-white dark:bg-gray-600 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                    Week
                </button>
                <button wire:click="changeView('month')" 
                    class="px-3 py-1 text-sm font-medium rounded-md transition-colors {{ $viewType === 'month' ? 'bg-white dark:bg-gray-600 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                    Month
                </button>
            </div>
        </div>

        <!-- Navigation Controls -->
        <div class="flex items-center justify-between">
            <!-- Date Navigation -->
            <div class="flex items-center space-x-4">
                <button wire:click="navigateDate('prev')" 
                    class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    ← Previous
                </button>
                <button wire:click="goToToday()" 
                    class="px-3 py-2 text-sm bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded-md hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                    Today
                </button>
                <button wire:click="navigateDate('next')" 
                    class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Next →
                </button>
            </div>

            <!-- Date Picker Controls -->
            @if($viewType === 'day')
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Select date:</label>
                    <input type="date" 
                           wire:change="jumpToDate($event.target.value)"
                           value="{{ $currentDate }}"
                           class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            @elseif($viewType === 'week')
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Select week:</label>
                    <input type="week" 
                           wire:change="jumpToWeek($event.target.value)"
                           value="{{ \Carbon\Carbon::parse($currentDate)->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-\WW') }}"
                           class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            @elseif($viewType === 'month')
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Select month:</label>
                    <input type="month" 
                           wire:change="jumpToMonth($event.target.value + '-01')"
                           value="{{ \Carbon\Carbon::parse($currentDate)->format('Y-m') }}"
                           class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            @endif

            <!-- Date Range Display -->
            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $this->dateRange }}
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-4">
        <div class="flex items-center justify-center space-x-6">
            <div class="flex items-center">
                <div class="w-5 h-5 rounded shadow-sm border" style="background-color: #3b82f6; border-color: #1e40af;"></div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 px-2">Planned</div>
            </div>
            <div class="flex items-center">
                <span style="font-size: 16px; color: #ef4444;">🚩</span>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 px-2">Started</div>
            </div>
            <div class="flex items-center">
                <div class="w-5 h-5 rounded shadow-sm border" style="background-color: #d1d5db; border-color: #dc2626;">
                    <div style="width: 60%; height: 100%; background-color: #f59e0b;"></div>
                </div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 px-2">Hold</div>
            </div>
            <div class="flex items-center">
                <div class="w-5 h-5 rounded shadow-sm border" style="background-color: #d1d5db; border-color: #16a34a;">
                    <div style="width: 100%; height: 100%; background-color: #22c55e;"></div>
                </div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 px-2">Completed</div>
            </div>
        </div>
    </div>

    <!-- Gantt Chart Content -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
        @if($viewType === 'day')
            @include('livewire.calendar.machines.views.day-view')
        @elseif($viewType === 'week')
            @include('livewire.calendar.machines.views.week-view')
        @elseif($viewType === 'month')
            @include('livewire.calendar.machines.views.month-view')
        @endif
    </div>

    <!-- Work Orders Summary -->
    @php
        $plannedBars = $ganttData['planned_bars'] ?? [];
        $actualBars = $ganttData['actual_bars'] ?? [];
        $allWorkOrders = collect($plannedBars)->merge($actualBars)->unique('work_order_id');
    @endphp
    
    @if($allWorkOrders->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-4">
            <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">📋 Work Orders in Current View</h4>
            <div class="space-y-2">
                @foreach($plannedBars as $planned)
                    @php
                        $actual = collect($actualBars)->where('work_order_id', $planned['work_order_id'])->first();
                    @endphp
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="flex flex-col space-y-1">
                                <!-- Planned bar indicator -->
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <!-- Start flag indicator (if exists) -->
                                @if($actual && $actual['is_running'])
                                    <span style="font-size: 12px; color: #ef4444;">🚩</span>
                                @endif
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $planned['work_order_id']) }}" 
                                       class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 underline" target="_blank">
                                        {{ $planned['title'] }}
                                    </a>
                                    @if($actual && $actual['is_running'])
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300 ml-2">
                                            🚩 Started
                                        </span>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $planned['subtitle'] }} • {{ $planned['machine'] ?? 'No Machine' }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                <div>📅 Planned: {{ \Carbon\Carbon::parse($planned['start'])->format('M d, H:i') }} - {{ \Carbon\Carbon::parse($planned['end'])->format('H:i') }}</div>
                                @if($actual && $actual['is_running'])
                                    <div class="text-yellow-600 dark:text-yellow-400">
                                        🚩 Started: {{ \Carbon\Carbon::parse($actual['start'])->format('M d, H:i') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-8 text-center">
            <div class="text-gray-500 dark:text-gray-400">
                <svg class="w-6 h-6 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2z"></path>
                </svg>
                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-1">No Work Orders Scheduled</h4>
                <p class="text-gray-600 dark:text-gray-400">This machine has no work orders in the current {{ $viewType }} view.</p>
            </div>
        </div>
    @endif
</div>

<script>
    // Auto-refresh gantt chart every 30 seconds for real-time updates
    setInterval(function() {
        @this.call('loadGanttData');
    }, 30000);
</script>