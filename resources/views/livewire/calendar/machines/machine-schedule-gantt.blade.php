<div class="space-y-4">
    <!-- Machine Gantt Chart Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-4">
                <button wire:click="toggleExpanded" class="flex items-center space-x-2 text-lg font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                    <span class="transform transition-transform {{ $isExpanded ? 'rotate-90' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </span>
                    <span>
                        🏭 Machine Schedule Gantt for {{ $machine->name ?? 'Unknown Machine' }}
                        <span class="text-sm font-normal text-gray-600 dark:text-gray-400">
                            ({{ $machine->assetId }})
                        </span>
                    </span>
                </button>
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

            <!-- Current Date Range Display -->
            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $this->dateRange }}
            </div>

            <!-- Date Jump Controls -->
            <div class="flex items-center space-x-2">
                @if($viewType === 'day')
                    <input type="date" 
                        wire:change="jumpToDate($event.target.value)" 
                        value="{{ $currentDate }}"
                        class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                @elseif($viewType === 'week')
                    <input type="week" 
                        wire:change="jumpToWeek($event.target.value)" 
                        value="{{ \Carbon\Carbon::parse($currentDate)->format('Y-\WW') }}"
                        class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                @elseif($viewType === 'month')
                    <input type="month" 
                        wire:change="jumpToMonth($event.target.value)" 
                        value="{{ \Carbon\Carbon::parse($currentDate)->format('Y-m') }}"
                        class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                @endif
            </div>
        </div>
    </div>

    <!-- Horizontal Gantt Chart -->
    @if($isExpanded)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-4 transition-all duration-300">
            @if(empty($ganttData['tasks']))
                <div class="text-center py-8">
                    <p class="text-gray-500 dark:text-gray-400">No work orders found for this machine (ID: {{ $machine->id }}).</p>
                    <p class="text-xs text-gray-400 mt-2">Date range: {{ $ganttData['date_range']['start']->format('M j, Y') }} to {{ $ganttData['date_range']['end']->format('M j, Y') }}</p>
                    
                    <!-- Visible Debug Info -->
                    <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg text-left">
                        <h3 class="font-bold text-yellow-800 dark:text-yellow-200 mb-2">🐛 Debug Information:</h3>
                        <div class="text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                            <div><strong>Machine:</strong> {{ $machine->name }} (ID: {{ $machine->id }})</div>
                            <div><strong>Asset ID:</strong> {{ $machine->assetId }}</div>
                            <div><strong>Current Date:</strong> {{ $currentDate }}</div>
                            <div><strong>View Type:</strong> {{ $viewType }}</div>
                            <div><strong>Date Range:</strong> {{ $ganttData['date_range']['start']->format('Y-m-d H:i:s') }} to {{ $ganttData['date_range']['end']->format('Y-m-d H:i:s') }}</div>
                            @php
                                $debugWorkOrders = $machine->workOrders()->limit(5)->get();
                            @endphp
                            <div><strong>Total Work Orders for Machine:</strong> {{ $machine->workOrders()->count() }}</div>
                            @if($debugWorkOrders->count() > 0)
                                <div><strong>Sample Work Orders:</strong></div>
                                @foreach($debugWorkOrders as $wo)
                                    <div class="ml-4 text-xs">• {{ $wo->unique_id }} ({{ $wo->start_time }} - {{ $wo->end_time }}) [{{ $wo->status }}]</div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                <!-- Time Header -->
                <div class="flex mb-4 border-b border-gray-200 dark:border-gray-600 pb-2">
                    <div class="w-48 flex-shrink-0 font-semibold text-gray-900 dark:text-white">Work Orders</div>
                    <div class="flex-1 flex items-center justify-between">
                        @foreach($ganttData['time_slots'] as $slot)
                            <div class="text-xs text-center text-gray-600 dark:text-gray-400 font-medium flex-1 whitespace-nowrap">
                                @if($viewType === 'day')
                                    {{ $slot->format('H:i') }}-{{ $slot->copy()->addHours(2)->format('H:i') }}
                                @elseif($viewType === 'week')
                                    {{ $slot->format('D M j') }}
                                @else
                                    {{ $slot->format('M j') }}
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Single Machine Gantt Timeline -->
                <div class="flex mb-3 border-b border-gray-100 dark:border-gray-700 pb-3">
                    <!-- Machine Info -->
                    <div class="w-48 flex-shrink-0 pr-4">
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ $ganttData['machine']->name }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $ganttData['machine']->assetId }}
                        </div>
                    </div>

                    <!-- Gantt Timeline -->
                    
                    
                    @php
                        // Group tasks to stack actual and planned together
                        $workOrderGroups = [];
                        foreach($ganttData['tasks'] as $task) {
                            $groupKey = $task['work_order_id'];
                            if (!isset($workOrderGroups[$groupKey])) {
                                $workOrderGroups[$groupKey] = [];
                            }
                            $workOrderGroups[$groupKey][] = $task;
                        }
                        
                        $totalGroups = count($workOrderGroups);
                        $rowHeight = 45; // Height per work order group (planned + actual) - increased for better text visibility
                        $collapsedThreshold = 3;
                    @endphp
                    
                    
                    <div class="flex-1 relative" style="min-height: {{ $totalGroups * $rowHeight + 40 }}px;">
                        
                        @if($totalGroups > $collapsedThreshold)
                            <!-- Expand/Collapse Button -->
                            <div class="absolute top-2 right-2 z-50">
                                <button wire:click="toggleShowAllRows" 
                                        class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm transition-colors">
                                    {{ $showAllRows ? 'Show Less' : 'Show All' }}
                                </button>
                            </div>
                        @endif
                        
                        @php $groupIndex = 0; @endphp
                        @foreach($workOrderGroups as $groupKey => $taskGroup)
                            @php
                                $task = $taskGroup[0]; // Use first task for common data
                                $groupY = $groupIndex * $rowHeight + 10;
                                
                                // Skip rendering if collapsed and beyond threshold
                                if (!$showAllRows && $groupIndex >= $collapsedThreshold) {
                                    continue;
                                }
                            @endphp
                            
                                @php
                                    $rangeStart = $ganttData['date_range']['start'];
                                    $rangeEnd = $ganttData['date_range']['end'];
                                    $totalMinutes = $rangeStart->diffInMinutes($rangeEnd);
                                    
                                    // Planned task positioning - using Advanced Gantt calculations
                                    $plannedStart = $task['planned_start'];
                                    $plannedEnd = $task['planned_end'];
                                    $startOffset = 0;
                                    $duration = 0;
                                    
                                    if ($plannedStart && $plannedEnd) {
                                        // Check if planned work order overlaps with the view range
                                        if ($plannedStart <= $rangeEnd && $plannedEnd >= $rangeStart) {
                                            // Clamp to view range boundaries (same as Advanced Gantt)
                                            $effectiveStart = $plannedStart->lt($rangeStart) ? $rangeStart->copy() : $plannedStart->copy();
                                            $effectiveEnd = $plannedEnd->gt($rangeEnd) ? $rangeEnd->copy() : $plannedEnd->copy();
                                            
                                            // Calculate exact positioning within the range (Advanced Gantt method)
                                            $minutesFromStart = $rangeStart->diffInMinutes($effectiveStart, false);
                                            $startOffset = max(0, ($minutesFromStart / max(1, $totalMinutes)) * 100);
                                            
                                            $durationMinutes = max(0, $effectiveStart->diffInMinutes($effectiveEnd, false));
                                            $duration = ($durationMinutes / max(1, $totalMinutes)) * 100;
                                            
                                            // Ensure minimum width for visibility (match Advanced Gantt)
                                            if ($duration > 0 && $duration < 2) {
                                                $duration = 2;
                                            }
                                        }
                                    }
                                    
                                    // Actual task positioning - using Advanced Gantt calculations
                                    $actualStart = $task['actual_start'];
                                    $actualEnd = $task['actual_end'];
                                    $actualStartOffset = 0;
                                    $actualDuration = 0;
                                    
                                    if ($actualStart) {
                                        $actualDisplayEnd = $actualEnd ?: now(); // Use current time if no end
                                        
                                        // Check if actual work order overlaps with view range
                                        if ($actualStart <= $rangeEnd && $actualDisplayEnd >= $rangeStart) {
                                            // Clamp to view range boundaries (same as Advanced Gantt)
                                            $effectiveStart = $actualStart->lt($rangeStart) ? $rangeStart->copy() : $actualStart->copy();
                                            $effectiveEnd = $actualDisplayEnd->gt($rangeEnd) ? $rangeEnd->copy() : $actualDisplayEnd->copy();
                                            
                                            // Calculate exact positioning within the range (Advanced Gantt method)
                                            $minutesFromStart = $rangeStart->diffInMinutes($effectiveStart, false);
                                            $actualStartOffset = ($minutesFromStart / (max(1, $totalMinutes))) * 100;
                                            
                                            $durationMinutes = $effectiveStart->diffInMinutes($effectiveEnd, false);
                                            $actualDuration = ($durationMinutes / (max(1, $totalMinutes))) * 100;
                                            
                                            // Ensure minimum width for visibility (match Advanced Gantt)
                                            if ($actualDuration < 2) {
                                                $actualDuration = 2;
                                            }
                                        }
                                    }

                                    // Status colors matching Advanced Gantt
                                    $workOrderStatusColors = config('work_order_status');
                                    $statusColorMap = [
                                        'Draft' => '#a3a3a3',
                                        'Released' => '#60a5fa', // assigned
                                        'Start' => '#fbbf24',
                                        'Hold' => '#f87171',
                                        'Completed' => '#34d399',
                                        'Closed' => '#a3a3a3'
                                    ];
                                    
                                    $plannedColor = '#e0f2fe'; // Light blue for planned
                                    $plannedBorder = '#60a5fa';
                                    $actualColor = $statusColorMap[$task['status']] ?? '#a3a3a3';
                                @endphp

                                @php
                                    $factoryId = auth()->user()->factory_id ?? 'default-factory';
                                    $plannedY = $groupY;
                                    $actualY = $groupY + 20; // Stack actual bar directly below planned with clear separation
                                @endphp

                                
                                @if($task['planned_start'] && $task['planned_end'])
                                    @if($duration > 0)
                                        <!-- Planned Task Bar visible within range -->
                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $task['work_order_id']) }}"
                                           class="absolute bg-blue-500 dark:bg-blue-600 hover:bg-blue-600 dark:hover:bg-blue-700 rounded flex items-center shadow transition group"
                                           style="left: {{ $startOffset }}%; width: {{ $duration }}%; top: {{ $plannedY }}px; height: 16px; min-width: 30px; z-index: {{ 100 + $groupIndex }}; text-decoration: none;"
                                           title="Planned: {{ $task['work_order_name'] }} ({{ $plannedStart->format('M j H:i') }} - {{ $plannedEnd->format('M j H:i') }})">
                                            <span class="text-[10px] text-white font-semibold px-2 truncate w-full" style="line-height: 20px;">
                                                {{ $task['work_order_name'] }}
                                            </span>
                                        </a>
                                    @else
                                        <!-- Work order exists but outside current view - show label -->
                                        <div class="absolute bg-blue-100 dark:bg-blue-900 border-l-2 border-blue-500 dark:border-blue-400 rounded flex items-center opacity-60"
                                             style="left: 0%; width: 100px; top: {{ $plannedY }}px; height: 16px; z-index: {{ 50 + $groupIndex }}; pointer-events: none;"
                                             title="Planned: {{ $task['work_order_name'] }} ({{ $plannedStart->format('M j H:i') }} - {{ $plannedEnd->format('M j H:i') }}) - Outside view range">
                                            <span class="text-[10px] text-blue-600 dark:text-blue-300 font-semibold px-2 truncate" style="line-height: 16px;">
                                                {{ $task['work_order_name'] }}
                                            </span>
                                        </div>
                                    @endif
                                @endif

                                @if($actualDuration > 0)
                                    @if($task['status'] === 'Start')
                                        <!-- Start Flag Indicator - matching Advanced Gantt -->
                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $task['work_order_id']) }}"
                                           class="absolute flex items-center group hover:opacity-80 transition"
                                           style="left: {{ $actualStartOffset }}%; top: {{ $actualY }}px; z-index: {{ 110 + $groupIndex }}; text-decoration: none;"
                                           title="Started: {{ $task['work_order_name'] }} at {{ $actualStart->format('H:i') }}">
                                            <div class="w-3 h-3 rounded-full" style="background-color: {{ $actualColor }}; border: 2px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>
                                            <div class="ml-1 text-xs font-bold text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-800 px-1 py-0.5 rounded shadow-sm border">
                                                STARTED
                                            </div>
                                        </a>
                                    @else
                                        @php
                                            // Get progress and status color matching Advanced Gantt
                                            $totalQty = $task['work_order']->qty ?? 0;
                                            $okQtys = $task['work_order']->ok_qtys ?? 0;
                                            $percent = $totalQty > 0 ? round(($okQtys / $totalQty) * 100) : 0;
                                            $displayPercent = $percent;
                                            $displayText = $percent > 0 ? $percent . '%' : $task['work_order_name'];
                                            
                                            if ($task['status'] === 'Start' && $percent === 0) {
                                                $displayPercent = 20; // Show 20% fill for visibility
                                            }
                                        @endphp
                                        <!-- Regular Actual Bar - matching Advanced Gantt styling -->
                                        <a href="{{ url('admin/' . $factoryId . '/work-orders/' . $task['work_order_id']) }}"
                                           class="absolute bg-gray-200 dark:bg-gray-700 hover:opacity-80 rounded flex items-center shadow transition group overflow-hidden"
                                           style="left: {{ $actualStartOffset }}%; width: {{ $actualDuration }}%; top: {{ $actualY }}px; height: 16px; min-width: 30px; z-index: {{ 100 + $groupIndex }}; text-decoration: none;"
                                           title="Actual: {{ $task['work_order_name'] }} ({{ ucfirst($task['status']) }}) - {{ $percent > 0 ? $percent . '%' : 'Started' }} Complete ({{ $actualStart->format('H:i') }}{{ $actualEnd ? ' - ' . $actualEnd->format('H:i') : ' - Ongoing' }})">

                                            <!-- Progress fill with status color -->
                                            <div class="absolute top-0 left-0 h-full transition-all duration-300"
                                                 style="width: {{ $displayPercent }}%; background-color: {{ $actualColor }}; z-index: 1;"></div>

                                            <span class="relative text-[10px] font-semibold px-2 truncate w-full z-10 mix-blend-difference text-white"
                                                  style="line-height: 20px;">
                                                {{ $task['status'] === 'Start' && $percent === 0 ? 'A: STARTED' : 'A: ' . $displayText }}
                                            </span>
                                        </a>
                                    @endif
                                @endif
                            @php $groupIndex++; @endphp
                        @endforeach
                        
                        @if(!$showAllRows && $totalGroups > $collapsedThreshold)
                            <!-- Show collapsed indicator -->
                            <div class="absolute bottom-2 left-1/2 transform -translate-x-1/2 text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-2 py-1 rounded shadow-sm">
                                Showing {{ $collapsedThreshold }} of {{ $totalGroups }} work orders
                            </div>
                        @endif
                        
                        <!-- Grid lines -->
                        <div class="absolute inset-0 grid grid-cols-{{ count($ganttData['time_slots']) }} gap-0 pointer-events-none">
                            @foreach($ganttData['time_slots'] as $slot)
                                <div class="border-r border-gray-100 dark:border-gray-700"></div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Color Legend matching Advanced Gantt -->
            <div class="mt-4 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded px-3 py-2">
                <div class="flex flex-wrap items-center justify-center gap-4 text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="inline-block w-4 h-4 rounded" style="background: #e0f2fe; border: 1px solid #60a5fa; opacity: 0.7;"></span>
                        <span class="text-gray-700 dark:text-gray-200">Planned</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-block w-4 h-4 rounded" style="background: #60a5fa"></span>
                        <span class="text-gray-700 dark:text-gray-200">Released</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-block w-4 h-4 rounded" style="background: #fbbf24"></span>
                        <span class="text-gray-700 dark:text-gray-200">Start</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-block w-4 h-4 rounded" style="background: #f87171"></span>
                        <span class="text-gray-700 dark:text-gray-200">Hold</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-block w-4 h-4 rounded" style="background: #34d399"></span>
                        <span class="text-gray-700 dark:text-gray-200">Completed</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-block w-4 h-4 rounded" style="background: #a3a3a3"></span>
                        <span class="text-gray-700 dark:text-gray-200">Closed</span>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>