@php
    $currentDate = \Carbon\Carbon::parse($this->currentDate)->setTimezone(config('app.timezone'));
    $weekStart = $currentDate->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $weekEnd = $weekStart->copy()->addDays(5)->endOfDay(); // Monday to Saturday
    
    // Get gantt data
    $plannedBars = $ganttData['planned_bars'] ?? [];
    $actualBars = $ganttData['actual_bars'] ?? [];
    $shiftBlocks = $ganttData['shift_blocks'] ?? [];
    
    // Create time intervals for the x-axis (2-hour intervals)
    $timeIntervals = [];
    for ($hour = 0; $hour < 24; $hour += 2) {
        $timeIntervals[] = [
            'hour' => $hour,
            'label' => sprintf('%02d:00', $hour)
        ];
    }
    
    // Create weekdays (Monday to Saturday)
    $weekDays = [];
    for ($i = 0; $i < 6; $i++) {
        $weekDays[] = $weekStart->copy()->addDays($i);
    }
    
    // Function to calculate position and width of bars
    if (!function_exists('calculateBarPosition')) {
        function calculateBarPosition($startTime, $endTime, $dayStart, $dayEnd) {
            $startCarbon = \Carbon\Carbon::parse($startTime)->setTimezone(config('app.timezone'));
            $endCarbon = \Carbon\Carbon::parse($endTime)->setTimezone(config('app.timezone'));
            
            // Clamp to day boundaries
            $clampedStart = $startCarbon->lt($dayStart) ? $dayStart->copy() : $startCarbon->copy();
            $clampedEnd = $endCarbon->gt($dayEnd) ? $dayEnd->copy() : $endCarbon->copy();
            
            // Calculate minutes from start of day
            $startMinutes = $dayStart->diffInMinutes($clampedStart, false);
            $duration = $clampedStart->diffInMinutes($clampedEnd, false);
            
            // Convert to percentages (24 hours = 1440 minutes)
            $leftPercent = ($startMinutes / 1440) * 100;
            $widthPercent = ($duration / 1440) * 100;
            
            // Ensure minimum width for visibility
            if ($widthPercent < 1) {
                $widthPercent = 1;
            }
            
            return [
                'left' => max(0, min(100, $leftPercent)),
                'width' => min(100 - $leftPercent, $widthPercent)
            ];
        }
    }
@endphp

<div class="overflow-hidden bg-white dark:bg-gray-900">
    
    <!-- Week Header -->
    <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
        <div class="text-center">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Week of {{ $weekStart->format('M j') }} - {{ $weekStart->copy()->addDays(5)->format('M j, Y') }}
            </div>
        </div>
    </div>

    <!-- Gantt Chart Container -->
    <div class="overflow-x-auto" style="max-height: 600px;">
        <div style="min-width: 800px;">
            
            <!-- Time Header (X-Axis) -->
            <div class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
                <div class="flex">
                    <div class="text-center py-3 border-r border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700" style="width: 120px;">
                        <div class="text-xs font-medium text-gray-600 dark:text-gray-300">Day / Time</div>
                    </div>
                    <div class="flex-1 flex">
                        @foreach($timeIntervals as $interval)
                            <div class="flex-1 text-center py-3 border-r border-gray-300 dark:border-gray-600 {{ $interval['hour'] === 0 ? 'border-l-2 border-l-gray-400 dark:border-l-gray-500' : '' }}">
                                <div class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                    {{ $interval['label'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Day Rows with Dual Bars -->
            @foreach($weekDays as $day)
                @php
                    $dayStart = $day->copy()->startOfDay();
                    $dayEnd = $day->copy()->endOfDay();
                    $isToday = $day->isToday();
                    
                    // Get bars for this day
                    $dayPlannedBars = collect($plannedBars)->filter(function($bar) use ($day) {
                        $barStart = \Carbon\Carbon::parse($bar['start'])->setTimezone(config('app.timezone'));
                        $barEnd = \Carbon\Carbon::parse($bar['end'])->setTimezone(config('app.timezone'));
                        return $barStart->isSameDay($day) || $barEnd->isSameDay($day) || 
                               ($barStart->lt($day->copy()->endOfDay()) && $barEnd->gt($day->copy()->startOfDay()));
                    });
                    
                    $dayActualBars = collect($actualBars)->filter(function($bar) use ($day) {
                        $barStart = \Carbon\Carbon::parse($bar['start'])->setTimezone(config('app.timezone'));
                        $barEnd = \Carbon\Carbon::parse($bar['end'])->setTimezone(config('app.timezone'));
                        return $barStart->isSameDay($day) || $barEnd->isSameDay($day) || 
                               ($barStart->lt($day->copy()->endOfDay()) && $barEnd->gt($day->copy()->startOfDay()));
                    });
                    
                    
                    // Group work orders by work_order_id to stack planned and actual bars properly
                    $workOrderGroups = [];
                    
                    // First, add all planned bars
                    foreach ($dayPlannedBars as $bar) {
                        $woId = $bar['work_order_id'];
                        if (!isset($workOrderGroups[$woId])) {
                            $workOrderGroups[$woId] = ['planned' => null, 'actual' => null];
                        }
                        $workOrderGroups[$woId]['planned'] = $bar;
                    }
                    
                    // Then, add actual bars to corresponding work orders
                    foreach ($dayActualBars as $bar) {
                        $woId = $bar['work_order_id'];
                        if (!isset($workOrderGroups[$woId])) {
                            $workOrderGroups[$woId] = ['planned' => null, 'actual' => null];
                        }
                        $workOrderGroups[$woId]['actual'] = $bar;
                    }
                    
                    $maxBars = count($workOrderGroups);
                    $rowHeight = max(100, 40 + ($maxBars * 50)); // 50px per work order pair (planned + actual)
                @endphp
                
                <div class="flex border-b border-gray-100 dark:border-gray-700 relative" style="height: {{ $rowHeight }}px;">
                    <!-- Day Label -->
                    <div class="bg-gray-50 dark:bg-gray-800 border-r border-gray-200 dark:border-gray-600 flex flex-col items-center justify-start pt-3" style="width: 120px;">
                        <div class="text-sm font-medium {{ $isToday ? 'text-blue-600 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $day->format('D') }}
                        </div>
                        <div class="text-xs {{ $isToday ? 'text-blue-500 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $day->format('M j') }}
                        </div>
                        @if($isToday)
                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1 font-semibold">TODAY</div>
                        @endif
                    </div>
                    
                    <!-- Timeline Area -->
                    <div class="flex-1 relative">
                        
                        <!-- Time Grid Lines -->
                        <div class="absolute inset-0 flex">
                            @foreach($timeIntervals as $interval)
                                <div class="flex-1 border-r border-gray-100 dark:border-gray-600 {{ $interval['hour'] === 0 ? 'border-l-2 border-l-gray-300 dark:border-l-gray-500' : '' }}">
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Current Time Indicator -->
                        @if($isToday)
                            @php
                                $currentTime = now()->setTimezone(config('app.timezone'));
                                $currentTimePercent = ($currentTime->hour * 60 + $currentTime->minute) / 1440 * 100;
                            @endphp
                            <div class="absolute top-0 bottom-0 w-0.5 bg-red-500 z-30" 
                                 style="left: {{ $currentTimePercent }}%;">
                                <div class="absolute -top-2 -left-2 w-4 h-4 bg-red-500 rounded-full"></div>
                            </div>
                        @endif
                        
                        <!-- Work Order Groups (Planned and Actual pairs) -->
                        @foreach($workOrderGroups as $woId => $group)
                            @php $groupIndex = $loop->index; @endphp
                            
                            <!-- Planned Bar (Top) -->
                            @if($group['planned'])
                                @php
                                    $bar = $group['planned'];
                                    $barPosition = calculateBarPosition($bar['start'], $bar['end'], $dayStart, $dayEnd);
                                    $topPosition = 8 + ($groupIndex * 50); // 50px spacing between work order groups
                                    $barColor = $bar['backgroundColor'];
                                    $borderColor = $bar['borderColor'];
                                @endphp
                                <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $bar['work_order_id']) }}" 
                                   target="_blank"
                                   class="absolute rounded shadow-sm border transition-all hover:shadow-lg z-20"
                                   style="background-color: {{ $barColor }}; 
                                          border-color: {{ $borderColor }};
                                          left: {{ $barPosition['left'] }}%; 
                                          width: {{ $barPosition['width'] }}%;
                                          top: {{ $topPosition }}px; 
                                          height: 20px;
                                          min-width: 30px;"
                                   title="Planned: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ $bar['machine'] ?? 'Machine' }}">
                                    <div class="px-2 py-0.5 text-white text-xs font-medium leading-tight truncate">
                                        WO {{ $bar['unique_id'] }}
                                    </div>
                                </a>
                            @endif
                            
                            <!-- Actual Bar or Start Flag (Bottom) -->
                            @if($group['actual'])
                                @php
                                    $bar = $group['actual'];
                                    $workOrder = \App\Models\WorkOrder::with(['workOrderLogs' => function($query) use ($dayStart) {
                                        $query->whereDate('changed_at', $dayStart->format('Y-m-d'))
                                              ->whereIn('status', ['Start', 'Completed', 'Hold'])
                                              ->orderBy('changed_at');
                                    }])->find($bar['work_order_id']);
                                    
                                    $currentStatus = $bar['status'];
                                    $topPosition = 32 + ($groupIndex * 50); // 24px below planned bar
                                @endphp
                                
                                @if($currentStatus === 'Start')
                                    {{-- Show Start Flag for "Start" status --}}
                                    @php
                                        $startLog = $workOrder->workOrderLogs->where('status', 'Start')->first();
                                        if ($startLog) {
                                            $startTime = \Carbon\Carbon::parse($startLog->changed_at)->setTimezone(config('app.timezone'));
                                            $startMinutes = $dayStart->diffInMinutes($startTime, false);
                                            $leftPercent = ($startMinutes / 1440) * 100;
                                        }
                                    @endphp
                                    
                                    @if($startLog)
                                        <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $bar['work_order_id']) }}" 
                                           target="_blank"
                                           class="absolute z-20 flex items-center justify-center transition-all hover:scale-125"
                                           style="left: {{ $leftPercent }}%; 
                                                  top: {{ $topPosition }}px; 
                                                  width: 24px;
                                                  height: 20px;"
                                           title="Started: {{ $bar['unique_id'] }} | Start Time: {{ $startTime->format('H:i') }} | Status: {{ $bar['status'] }}">
                                            <span style="font-size: 16px; color: #ef4444; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">🚩</span>
                                        </a>
                                    @endif
                                    
                                @elseif(in_array($currentStatus, ['Hold', 'Completed', 'Closed']))
                                    {{-- Show Actual Bar for Hold/Completed/Closed status --}}
                                    @php
                                        // Get quantity progress from work order (ok_qtys / qty, excluding scrapped)
                                        $workOrderData = \App\Models\WorkOrder::find($bar['work_order_id']);
                                        $qtyProgress = 0;
                                        if ($workOrderData && $workOrderData->qty > 0) {
                                            $qtyProgress = ($workOrderData->ok_qtys / $workOrderData->qty) * 100;
                                            $qtyProgress = min(100, max(0, $qtyProgress)); // Clamp between 0-100
                                        }
                                        
                                        // Calculate correct bar width based on status and available time data
                                        $startLog = $workOrder->workOrderLogs->where('status', 'Start')->first();
                                        $completedLog = $workOrder->workOrderLogs->whereIn('status', ['Completed', 'Closed'])->first();
                                        
                                        if ($currentStatus === 'Completed' && $completedLog && $startLog) {
                                            // For completed: Use actual start time to actual completion time
                                            $actualStartTime = \Carbon\Carbon::parse($startLog->changed_at)->setTimezone(config('app.timezone'));
                                            $actualEndTime = \Carbon\Carbon::parse($completedLog->changed_at)->setTimezone(config('app.timezone'));
                                            $barPosition = calculateBarPosition($actualStartTime->toDateTimeString(), $actualEndTime->toDateTimeString(), $dayStart, $dayEnd);
                                        } elseif (in_array($currentStatus, ['Hold', 'Start']) && $startLog) {
                                            // For Hold/Start: Use actual start time to planned end time
                                            $actualStartTime = \Carbon\Carbon::parse($startLog->changed_at)->setTimezone(config('app.timezone'));
                                            $plannedEndTime = \Carbon\Carbon::parse($bar['end'])->setTimezone(config('app.timezone'));
                                            $barPosition = calculateBarPosition($actualStartTime->toDateTimeString(), $plannedEndTime->toDateTimeString(), $dayStart, $dayEnd);
                                        } else {
                                            // Fallback to planned times
                                            $barPosition = calculateBarPosition($bar['start'], $bar['end'], $dayStart, $dayEnd);
                                        }
                                        
                                        // Set colors and progress based on status
                                        if ($currentStatus === 'Completed' || $currentStatus === 'Closed') {
                                            $actualBgColor = '#d1d5db'; // Light gray background for completed
                                            $actualBorderColor = '#16a34a'; // Green border
                                            $progressColor = '#22c55e'; // Green progress fill
                                            // Completed shows actual qty percentage, not forced 100%
                                        } elseif ($currentStatus === 'Hold') {
                                            $actualBgColor = '#d1d5db'; // Light gray background for hold
                                            $actualBorderColor = '#dc2626'; // Red border
                                            $progressColor = '#f59e0b'; // Orange/amber progress fill for hold
                                        } else {
                                            $actualBgColor = '#d1d5db'; // Light gray background
                                            $actualBorderColor = '#6b7280'; // Gray border
                                            $progressColor = '#3b82f6'; // Blue progress fill
                                        }
                                    @endphp
                                    
                                    <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $bar['work_order_id']) }}" 
                                       target="_blank"
                                       class="absolute rounded shadow-sm border transition-all hover:shadow-lg z-20 overflow-hidden"
                                       style="background-color: {{ $actualBgColor }}; 
                                              border-color: {{ $actualBorderColor }};
                                              left: {{ $barPosition['left'] }}%; 
                                              width: {{ $barPosition['width'] }}%;
                                              top: {{ $topPosition }}px; 
                                              height: 20px;
                                              min-width: 30px;"
                                       title="{{ ucfirst($currentStatus) }}: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ number_format($qtyProgress, 1) }}% Qty Complete">
                                        
                                        <!-- Progress Fill based on quantity -->
                                        <div class="absolute top-0 left-0 h-full transition-all duration-300"
                                             style="width: {{ $qtyProgress }}%; background-color: {{ $progressColor }};"></div>
                                        
                                        <!-- Text Content showing quantity percentage -->
                                        <div class="relative px-2 py-0.5 text-white text-xs font-medium leading-tight truncate z-10">
                                            {{ number_format($qtyProgress, 1) }}%
                                        </div>
                                    </a>
                                @endif
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
