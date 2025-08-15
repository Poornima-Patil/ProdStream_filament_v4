<div class="space-y-4">
    <!-- Calendar Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    📅 Schedule for {{ $machine->assetId }} - {{ $machine->name }}
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
        <!-- Simple legend with just color indicators -->
        <div class="flex items-center justify-center space-x-8">
            <div class="flex items-center">
                <div class="w-5 h-5 bg-[#fb923c] rounded shadow-sm border border-orange-600"></div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 px-2">Planned</div>
            </div>
            <div class="flex items-center">
                <div class="w-5 h-5 bg-blue-500 rounded shadow-sm border border-blue-600"></div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 px-2">Actual</div>
            </div>
        </div>
    </div>

    <!-- Calendar Content -->
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
    @if(count($events) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-4">
            <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">📋 Work Orders in Current View</h4>
            <div class="space-y-2">
                @foreach($events as $event)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 rounded-full" 
                                style="background-color: {{ $event['backgroundColor'] }}"></div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    <a href="{{ url("/admin/" . auth()->user()->factory_id . "/work-orders/" . $event['work_order_id']) }}" 
                                       class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 underline" target="_blank">
                                        {{ $event['title'] }}
                                    </a>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $event['subtitle'] }} • {{ $event['operator'] }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($event['start'])->format('M d, H:i') }} - 
                                {{ \Carbon\Carbon::parse($event['end'])->format('H:i') }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                @if($event['status'] === 'Start')
                                    🔴 Running
                                @else
                                    ⏰ Scheduled
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-1">No Work Orders Scheduled</h4>
                <p class="text-gray-600 dark:text-gray-400">This machine has no work orders in the current {{ $viewType }} view.</p>
            </div>
        </div>
    @endif
</div>

<script>
    // Auto-refresh calendar every 30 seconds for real-time updates
    setInterval(function() {
        @this.call('loadEvents');
    }, 30000);
</script>
