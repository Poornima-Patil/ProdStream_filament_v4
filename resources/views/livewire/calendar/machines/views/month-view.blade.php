@php
    $currentDate = \Carbon\Carbon::parse($this->currentDate)->setTimezone(config('app.timezone'));
    $monthStart = $currentDate->copy()->startOfMonth();
    $monthEnd = $currentDate->copy()->endOfMonth();
    
    // Get gantt data
    $plannedBars = $ganttData['planned_bars'] ?? [];
    $actualBars = $ganttData['actual_bars'] ?? [];
    
    // Create time intervals for the x-axis (weekly intervals for month view)
    $weeks = [];
    $current = $monthStart->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $weekNumber = 1;
    
    while($current <= $monthEnd->copy()->endOfWeek(\Carbon\Carbon::MONDAY)) {
        $weekStart = $current->copy();
        $weekEnd = $current->copy()->addDays(5)->endOfDay(); // Monday to Saturday
        
        // Only include weeks that have days in this month
        if ($weekStart <= $monthEnd && $weekEnd >= $monthStart) {
            $weeks[] = [
                'number' => $weekNumber,
                'start' => $weekStart,
                'end' => $weekEnd,
                'label' => 'Week ' . $weekNumber
            ];
        }
        
        $current->addWeek();
        $weekNumber++;
    }
    
    // Get all days in the month (Monday to Saturday only)
    $monthDays = [];
    $current = $monthStart->copy();
    while ($current <= $monthEnd) {
        // Only include Monday to Saturday (1-6, skip Sunday which is 0)
        if ($current->dayOfWeek >= 1 && $current->dayOfWeek <= 6) {
            $monthDays[] = $current->copy();
        }
        $current->addDay();
    }
    
    // Function to calculate position and width of bars for month view
    if (!function_exists('calculateMonthBarPosition')) {
        function calculateMonthBarPosition($startTime, $endTime, $currentDay, $weeks) {
            $startCarbon = \Carbon\Carbon::parse($startTime)->setTimezone(config('app.timezone'));
            $endCarbon = \Carbon\Carbon::parse($endTime)->setTimezone(config('app.timezone'));
            
            // Clamp to current day boundaries
            $dayStart = $currentDay->copy()->startOfDay();
            $dayEnd = $currentDay->copy()->endOfDay();
            $clampedStart = $startCarbon->lt($dayStart) ? $dayStart->copy() : $startCarbon->copy();
            $clampedEnd = $endCarbon->gt($dayEnd) ? $dayEnd->copy() : $endCarbon->copy();
            
            // Find which week this day belongs to
            $weekIndex = null;
            foreach ($weeks as $index => $week) {
                if ($currentDay >= $week['start'] && $currentDay <= $week['end']) {
                    $weekIndex = $index;
                    break;
                }
            }
            
            if ($weekIndex === null) {
                return ['left' => 0, 'width' => 1]; // Fallback
            }
            
            // Calculate position within the week column
            $totalWeeks = count($weeks);
            $weekWidth = 100 / $totalWeeks; // Each week gets equal width
            
            // Calculate time position within the day (0-100%)
            $dayStartMinute = $dayStart->diffInMinutes($clampedStart, false);
            $duration = $clampedStart->diffInMinutes($clampedEnd, false);
            
            // For month view, position within the week column based on time of day
            $timePercent = ($dayStartMinute / 1440) * 100; // Position within day
            $durationPercent = ($duration / 1440) * 100; // Duration within day
            
            // Position the bar within the correct week column
            $leftPercent = ($weekIndex * $weekWidth) + ($timePercent * $weekWidth / 100);
            $widthPercent = ($durationPercent * $weekWidth / 100);
            
            // Ensure minimum width for visibility
            if ($widthPercent < 0.5) {
                $widthPercent = 0.5;
            }
            
            return [
                'left' => max(0, min(100, $leftPercent)),
                'width' => min(100 - $leftPercent, $widthPercent)
            ];
        }
    }
@endphp

<div class="overflow-hidden bg-white dark:bg-gray-900">
    <!-- Month Header -->
    <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
        <div class="text-center">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $currentDate->format('F Y') }}
            </div>
        </div>
    </div>

    <!-- Gantt Chart Container -->
    <div class="overflow-x-auto" style="max-height: 600px;">
        <div style="min-width: 1200px;">
            
            <!-- Week Header (X-Axis) -->
            <div class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
                <div class="flex">
                    <div class="text-center py-3 border-r border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700" style="width: 120px;">
                        <div class="text-xs font-medium text-gray-600 dark:text-gray-300">Day / Week</div>
                    </div>
                    <div class="flex-1 flex">
                        @foreach($weeks as $week)
                            <div class="flex-1 text-center py-3 border-r border-gray-300 dark:border-gray-600">
                                <div class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                    {{ $week['label'] }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $week['start']->format('M j') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Day Rows with Horizontal Bars -->
            @foreach($monthDays as $day)
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
                    $rowHeight = max(60, 20 + ($maxBars * 30)); // Smaller for month view: 30px per work order pair
                @endphp
                
                <div class="flex border-b border-gray-100 dark:border-gray-700 relative" style="height: {{ $rowHeight }}px;">
                    <!-- Day Label -->
                    <div class="bg-gray-50 dark:bg-gray-800 border-r border-gray-200 dark:border-gray-600 flex flex-col items-center justify-start pt-2" style="width: 120px;">
                        <div class="text-xs font-medium {{ $isToday ? 'text-blue-600 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300' }}">
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
                        <!-- Week Grid Lines -->
                        <div class="absolute inset-0 flex">
                            @foreach($weeks as $week)
                                <div class="flex-1 border-r border-gray-100 dark:border-gray-600">
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Current Time Indicator -->
                        @if($isToday)
                            @php
                                $currentTime = now()->setTimezone(config('app.timezone'));
                                
                                // Find which week today belongs to
                                $todayWeekIndex = null;
                                foreach ($weeks as $index => $week) {
                                    if ($day >= $week['start'] && $day <= $week['end']) {
                                        $todayWeekIndex = $index;
                                        break;
                                    }
                                }
                                
                                if ($todayWeekIndex !== null) {
                                    $totalWeeks = count($weeks);
                                    $weekWidth = 100 / $totalWeeks;
                                    $timePercent = ($currentTime->hour * 60 + $currentTime->minute) / 1440 * 100;
                                    $currentTimePercent = ($todayWeekIndex * $weekWidth) + ($timePercent * $weekWidth / 100);
                                } else {
                                    $currentTimePercent = 0;
                                }
                            @endphp
                            <div class="absolute top-0 bottom-0 w-0.5 bg-red-500 z-30" 
                                 style="left: {{ $currentTimePercent }}%;">
                                <div class="absolute -top-1 -left-1 w-2 h-2 bg-red-500 rounded-full"></div>
                            </div>
                        @endif
                        
                        <!-- Work Order Groups (Planned and Actual pairs) -->
                        @foreach($workOrderGroups as $woId => $group)
                            @php $groupIndex = $loop->index; @endphp
                            
                            <!-- Planned Bar (Top) -->
                            @if($group['planned'])
                                @php
                                    $bar = $group['planned'];
                                    $barPosition = calculateMonthBarPosition($bar['start'], $bar['end'], $day, $weeks);
                                    $topPosition = 4 + ($groupIndex * 30); // 30px spacing between work order groups
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
                                          height: 12px;
                                          min-width: 20px;"
                                   title="Planned: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ $bar['machine'] ?? 'Machine' }}">
                                    <div class="px-1 text-white text-xs font-medium leading-tight truncate" style="font-size: 8px; line-height: 12px;">
                                        WO {{ $bar['unique_id'] }}
                                    </div>
                                </a>
                            @endif
                            
                            <!-- Actual Bar or Start Flag (Bottom) -->
                            @if($group['actual'])
                                @php
                                    $bar = $group['actual'];
                                    $workOrder = \App\Models\WorkOrder::with(['workOrderLogs' => function($query) use ($day) {
                                        $query->whereDate('changed_at', $day->format('Y-m-d'))
                                              ->whereIn('status', ['Start', 'Completed', 'Hold'])
                                              ->orderBy('changed_at');
                                    }])->find($bar['work_order_id']);
                                    
                                    $currentStatus = $bar['status'];
                                    $topPosition = 18 + ($groupIndex * 30); // Below planned bar
                                @endphp
                                
                                @if($currentStatus === 'Start')
                                    {{-- Show Start Flag for "Start" status --}}
                                    @php
                                        $startLog = $workOrder->workOrderLogs->where('status', 'Start')->first();
                                        if ($startLog) {
                                            $startTime = \Carbon\Carbon::parse($startLog->changed_at)->setTimezone(config('app.timezone'));
                                            
                                            // Calculate position using month positioning logic
                                            $weekIndex = null;
                                            foreach ($weeks as $index => $week) {
                                                if ($day >= $week['start'] && $day <= $week['end']) {
                                                    $weekIndex = $index;
                                                    break;
                                                }
                                            }
                                            
                                            if ($weekIndex !== null) {
                                                $totalWeeks = count($weeks);
                                                $weekWidth = 100 / $totalWeeks;
                                                $dayStart = $day->copy()->startOfDay();
                                                $startMinutes = $dayStart->diffInMinutes($startTime, false);
                                                $timePercent = ($startMinutes / 1440) * 100;
                                                $leftPercent = ($weekIndex * $weekWidth) + ($timePercent * $weekWidth / 100);
                                            } else {
                                                $leftPercent = 0;
                                            }
                                        }
                                    @endphp
                                    
                                    @if($startLog)
                                        <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $bar['work_order_id']) }}" 
                                           target="_blank"
                                           class="absolute z-20 flex items-center justify-center transition-all hover:scale-125"
                                           style="left: {{ $leftPercent }}%; 
                                                  top: {{ $topPosition }}px; 
                                                  width: 16px;
                                                  height: 12px;"
                                           title="Started: {{ $bar['unique_id'] }} | Start Time: {{ $startTime->format('H:i') }} | Status: {{ $bar['status'] }}">
                                            <span style="font-size: 10px; color: #ef4444; text-shadow: 1px 1px 1px rgba(0,0,0,0.3);">🚩</span>
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
                                            $barPosition = calculateMonthBarPosition($actualStartTime->toDateTimeString(), $actualEndTime->toDateTimeString(), $day, $weeks);
                                        } elseif (in_array($currentStatus, ['Hold', 'Start']) && $startLog) {
                                            // For Hold/Start: Use actual start time to planned end time
                                            $actualStartTime = \Carbon\Carbon::parse($startLog->changed_at)->setTimezone(config('app.timezone'));
                                            $plannedEndTime = \Carbon\Carbon::parse($bar['end'])->setTimezone(config('app.timezone'));
                                            $barPosition = calculateMonthBarPosition($actualStartTime->toDateTimeString(), $plannedEndTime->toDateTimeString(), $day, $weeks);
                                        } else {
                                            // Fallback to planned times
                                            $barPosition = calculateMonthBarPosition($bar['start'], $bar['end'], $day, $weeks);
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
                                              height: 12px;
                                              min-width: 20px;"
                                       title="{{ ucfirst($currentStatus) }}: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ number_format($qtyProgress, 1) }}% Qty Complete">
                                        
                                        <!-- Progress Fill based on quantity -->
                                        <div class="absolute top-0 left-0 h-full transition-all duration-300"
                                             style="width: {{ $qtyProgress }}%; background-color: {{ $progressColor }};"></div>
                                        
                                        <!-- Text Content showing quantity percentage -->
                                        <div class="relative px-1 text-white text-xs font-medium leading-tight truncate z-10" style="font-size: 8px; line-height: 12px;">
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