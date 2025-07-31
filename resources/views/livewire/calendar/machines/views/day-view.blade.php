<!-- Day View -->
<div class="overflow-hidden bg-white dark:bg-gray-900">
    @php
        $currentDate = \Carbon\Carbon::parse($this->currentDate);
        $dayEvents = collect($events)->filter(function($event) use ($currentDate) {
            return \Carbon\Carbon::parse($event['start'])->isSameDay($currentDate);
        });
        
        // Get factory shifts ordered by start time
        $factoryId = auth()->user()->factory_id;
        $shifts = \App\Models\Shift::where('factory_id', $factoryId)
                    ->orderBy('start_time')
                    ->get();
        
        // Create 2-hour interval grid for full 24 hours (00:00 to 22:00, 12 intervals)
        $timeIntervals = [];
        for ($hour = 0; $hour < 24; $hour += 2) {
            $timeIntervals[] = $hour;
        }
        
        // Function to determine which shift a time slot belongs to
        function getShiftForHour($hour, $shifts) {
            $currentTime = sprintf('%02d:00:00', $hour);
            
            foreach($shifts as $index => $shift) {
                $startTime = $shift->start_time;
                $endTime = $shift->end_time;
                
                // Handle shifts that cross midnight
                if ($endTime < $startTime) {
                    // Shift crosses midnight
                    if ($currentTime >= $startTime || $currentTime < $endTime) {
                        return ['shift' => $shift, 'index' => $index];
                    }
                } else {
                    // Normal shift within same day
                    if ($currentTime >= $startTime && $currentTime < $endTime) {
                        return ['shift' => $shift, 'index' => $index];
                    }
                }
            }
            
            // If no shift found, mark as "Rest of Day"
            return ['shift' => null, 'index' => -1];
        }
        
        // Define alternating background colors for shifts with dark mode support
        $shiftColors = [
            'bg-gray-100 dark:bg-gray-700',     // Light gray / Dark gray (1st shift)
            'bg-gray-300 dark:bg-gray-600',     // Darker gray / Medium gray (2nd shift)  
            'bg-gray-100 dark:bg-gray-700',     // Light gray / Dark gray again (3rd shift)
            'bg-gray-300 dark:bg-gray-600',     // Darker gray / Medium gray again (4th shift)
        ];
        $restDayColor = 'bg-amber-50 dark:bg-amber-900'; // Light amber / Dark amber for "rest of day"
    @endphp
    
    <!-- Day Header -->
    <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
        <div class="text-center">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $currentDate->format('l') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ $currentDate->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Timeline Container -->
    <div class="bg-white dark:bg-gray-900 overflow-y-auto" style="max-height: 600px;">
        <div style="min-width: 400px;">
            
            <!-- Date Header (X-Axis) -->
            <div class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
                <div class="flex">
                    <div class="text-center py-3 border-r border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700" style="width: 100px;">
                        <div class="text-xs font-medium text-gray-600 dark:text-gray-300">Time</div>
                    </div>
                    <div class="flex-1 text-center py-3 border-r border-gray-300 dark:border-gray-600">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ $currentDate->format('M j') }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $currentDate->format('l') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time Rows (Y-Axis) -->
            @foreach($timeIntervals as $hour)
                @php
                    // Filter events for this time slot (planned times)
                    $timeSlotEvents = $dayEvents->filter(function($event) use ($hour) {
                        $eventStart = \Carbon\Carbon::parse($event['start']);
                        
                        // Convert to minutes for more precise comparison
                        $startMinute = $eventStart->hour * 60 + $eventStart->minute;
                        $slotStartMinute = $hour * 60;
                        $slotEndMinute = ($hour + 2) * 60;
                        
                        // Only show work order in the time slot where it STARTS
                        // This prevents duplicate display across multiple time slots
                        return $startMinute >= $slotStartMinute && $startMinute < $slotEndMinute;
                    });
                    
                    // Also check for actual execution times from work order logs
                    $actualTimeSlotEvents = $dayEvents->filter(function($event) use ($hour, $currentDate) {
                        // Check if this work order has start/completion logs for today
                        $workOrderId = $event['work_order_id'];
                        $workOrder = \App\Models\WorkOrder::with(['workOrderLogs' => function($query) use ($currentDate) {
                            $query->whereDate('changed_at', $currentDate->format('Y-m-d'))
                                  ->whereIn('status', ['Start', 'Completed'])
                                  ->orderBy('changed_at');
                        }])->find($workOrderId);
                        
                        if (!$workOrder || $workOrder->workOrderLogs->isEmpty()) {
                            return false;
                        }
                        
                        $startLog = $workOrder->workOrderLogs->where('status', 'Start')->first();
                        if (!$startLog) {
                            return false;
                        }
                        
                        $actualStartTime = \Carbon\Carbon::parse($startLog->changed_at);
                        $startMinute = $actualStartTime->hour * 60 + $actualStartTime->minute;
                        $slotStartMinute = $hour * 60;
                        $slotEndMinute = ($hour + 2) * 60;
                        
                        // Check if actual start time falls in this slot
                        return $startMinute >= $slotStartMinute && $startMinute < $slotEndMinute;
                    });
                    
                    $isCurrentHour = $currentDate->isToday() && now()->hour >= $hour && now()->hour < $hour + 2;
                    
                    // Get shift information for this hour
                    $shiftInfo = getShiftForHour($hour, $shifts);
                    $shift = $shiftInfo['shift'];
                    $shiftIndex = $shiftInfo['index'];
                    
                    // Determine background color based on shift
                    if ($shift) {
                        $bgColor = $shiftColors[$shiftIndex % count($shiftColors)];
                        $shiftName = $shift->name;
                        $shiftTime = date('H:i', strtotime($shift->start_time)) . ' - ' . date('H:i', strtotime($shift->end_time));
                    } else {
                        $bgColor = $restDayColor;
                        $shiftName = 'Rest of Day';
                        $shiftTime = '';
                    }
                @endphp
                
                <div class="flex border-b border-gray-100 dark:border-gray-700" style="height: 80px;">
                    <!-- Time Label (Y-Axis) -->
                    <div class="bg-gray-50 dark:bg-gray-800 border-r border-gray-200 dark:border-gray-600 flex items-center justify-center" style="width: 100px;">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 text-center">
                            {{ sprintf('%02d:00', $hour) }}
                        </div>
                    </div>
                    
                    <!-- Date Column with Shift Background -->
                    <div class="flex-1 border-r border-gray-200 dark:border-gray-600 relative {{ $bgColor }}" 
                         title="@if($shift){{ $shiftName }} ({{ $shiftTime }})@else{{ $shiftName }}@endif">
                        
                        <!-- Current Time Indicator -->
                        @if($isCurrentHour)
                            @php
                                $currentMinute = now()->minute;
                                $currentPosition = ($currentMinute / 60) * 100;
                            @endphp
                            <div class="absolute left-0 right-0 bg-red-500 z-20" 
                                 style="top: {{ $currentPosition }}%; height: 2px;">
                                <div class="absolute left-2 -top-2 w-4 h-4 bg-red-500 rounded-full"></div>
                            </div>
                            <!-- Current hour overlay with transparency -->
                            <div class="absolute inset-0 bg-blue-200 dark:bg-blue-800 opacity-20 z-10"></div>
                        @endif
                        
                        
                        <!-- PLANNED Work Order Events (First Half - Left Side) -->
                        @foreach($timeSlotEvents as $eventIndex => $event)
                            @php
                                $eventStart = \Carbon\Carbon::parse($event['start']);
                                $eventEnd = \Carbon\Carbon::parse($event['end']);
                                
                                // Calculate vertical position and height
                                $startMinute = $eventStart->hour * 60 + $eventStart->minute;
                                $endMinute = $eventEnd->hour * 60 + $eventEnd->minute;
                                $slotStartMinute = $hour * 60;
                                
                                // Calculate position from start of this slot
                                $topPercent = (($startMinute - $slotStartMinute) / 120) * 100;
                                
                                // Calculate total duration in minutes and convert to percentage of 2-hour slot
                                $durationMinutes = $endMinute - $startMinute;
                                $heightPercent = ($durationMinutes / 120) * 100;
                                
                                // Ensure minimum height for visibility
                                if ($heightPercent < 8) {
                                    $heightPercent = 8;
                                }
                                
                                // Position in LEFT HALF for planned times
                                $leftPercent = 1; // Start at left edge
                                $widthPercent = 48; // Use left half width (48% to leave small gap)
                                
                                // Use original position and height (no scaling needed for horizontal split)
                                $scaledTopPercent = $topPercent; 
                                $scaledHeightPercent = $heightPercent;
                            @endphp
                            
                            <a href="{{ url("/admin/" . auth()->user()->factory_id . "/work-orders/" . $event['work_order_id']) }}" 
                               target="_blank"
                               class="absolute rounded shadow-sm border cursor-pointer transition-all hover:shadow-lg block"
                               style="background-color: #fb923c; 
                                      border-color: #ea580c;
                                      top: {{ $scaledTopPercent }}%; 
                                      height: {{ $scaledHeightPercent }}%; 
                                      left: {{ $leftPercent }}%;
                                      width: {{ $widthPercent }}%;
                                      margin: 1px;
                                      z-index: 10;
                                      opacity: 0.8;"
                               title="PLANNED: WO {{ $event['unique_id'] ?? $event['work_order_id'] }} | {{ $eventStart->format('H:i') }} - {{ $eventEnd->format('H:i') }} | Operator: {{ $event['operator'] ?? 'Unassigned' }} | Part: {{ $event['subtitle'] ?? 'Unknown' }}">
                                
                                <!-- Work Order Content -->
                                <div class="px-1 py-1 h-full flex flex-col justify-center text-xs overflow-hidden">
                                    <div class="font-medium truncate text-white">
                                        P: {{ $event['title'] }}
                                    </div>
                                    <div class="text-xs text-white opacity-90">
                                        {{ $eventStart->format('H:i') }} - {{ $eventEnd->format('H:i') }}
                                    </div>
                                </div>
                            </a>
                        @endforeach
                        
                        <!-- ACTUAL Work Order Events (Second Half - Right Side) -->
                        @foreach($actualTimeSlotEvents as $eventIndex => $event)
                            @php
                                $workOrderId = $event['work_order_id'];
                                $workOrder = \App\Models\WorkOrder::with(['workOrderLogs' => function($query) use ($currentDate) {
                                    $query->whereDate('changed_at', $currentDate->format('Y-m-d'))
                                          ->whereIn('status', ['Start', 'Completed'])
                                          ->orderBy('changed_at');
                                }])->find($workOrderId);
                                
                                $startLog = $workOrder->workOrderLogs->where('status', 'Start')->first();
                                $completedLog = $workOrder->workOrderLogs->where('status', 'Completed')->first();
                                
                                if (!$startLog) continue;
                                
                                $actualStartTime = \Carbon\Carbon::parse($startLog->changed_at);
                                $startMinute = $actualStartTime->hour * 60 + $actualStartTime->minute;
                                $slotStartMinute = $hour * 60;
                                
                                // Calculate position from start of this slot
                                $topPercent = (($startMinute - $slotStartMinute) / 120) * 100;
                                
                                if ($completedLog) {
                                    // Work order is completed - use actual times
                                    $actualEndTime = \Carbon\Carbon::parse($completedLog->changed_at);
                                    $endMinute = $actualEndTime->hour * 60 + $actualEndTime->minute;
                                    $durationMinutes = $endMinute - $startMinute;
                                    $heightPercent = ($durationMinutes / 120) * 100;
                                    $isEstimated = false;
                                    $actualBgColor = '#3b82f6'; // Blue for completed
                                    $actualBorderColor = '#2563eb'; // Darker blue border
                                    $statusText = 'ACTUAL';
                                    $estimatedEndTime = $actualEndTime; // For consistent tooltip
                                } else {
                                    // Work order is started but not completed - estimate based on planned duration
                                    $plannedStart = \Carbon\Carbon::parse($event['start']);
                                    $plannedEnd = \Carbon\Carbon::parse($event['end']);
                                    $plannedDuration = $plannedStart->diffInMinutes($plannedEnd);
                                    $estimatedEndTime = $actualStartTime->copy()->addMinutes($plannedDuration);
                                    $endMinute = $estimatedEndTime->hour * 60 + $estimatedEndTime->minute;
                                    $durationMinutes = $endMinute - $startMinute;
                                    $heightPercent = ($durationMinutes / 120) * 100;
                                    $isEstimated = true;
                                    $actualBgColor = '#7dd3fc'; // Light blue for estimated
                                    $actualBorderColor = '#0ea5e9'; // Sky blue border
                                    $statusText = 'ESTIMATED';
                                }
                                
                                // Ensure minimum height for visibility
                                if ($heightPercent < 8) {
                                    $heightPercent = 8;
                                }
                                
                                // Position in RIGHT HALF for actual times
                                $leftPercent = 51; // Start at right half (51% to leave small gap)
                                $widthPercent = 48; // Use right half width
                                
                                // Use original position and height (no scaling needed for horizontal split)
                                $scaledTopPercent = $topPercent;
                                $scaledHeightPercent = $heightPercent;
                            @endphp
                            
                            <a href="{{ url("/admin/" . auth()->user()->factory_id . "/work-orders/" . $event['work_order_id']) }}" 
                               target="_blank"
                               class="absolute rounded shadow-sm border cursor-pointer transition-all hover:shadow-lg block {{ $isEstimated ? 'border-dashed' : '' }}"
                               style="background-color: {{ $actualBgColor }}; 
                                      border-color: {{ $actualBorderColor }};
                                      top: {{ $scaledTopPercent }}%; 
                                      height: {{ $scaledHeightPercent }}%; 
                                      left: {{ $leftPercent }}%;
                                      width: {{ $widthPercent }}%;
                                      margin: 1px;
                                      z-index: 15;"
                               title="{{ $statusText }}: WO {{ $event['unique_id'] ?? $event['work_order_id'] }} | Started: {{ $actualStartTime->format('H:i') }} {{ $completedLog ? '| Completed: ' . \Carbon\Carbon::parse($completedLog->changed_at)->format('H:i') : '| Estimated End: ' . $estimatedEndTime->format('H:i') }} | Operator: {{ $event['operator'] ?? 'Unassigned' }}">
                                
                                <!-- Work Order Content -->
                                <div class="px-2 py-1 text-white h-full flex flex-col justify-center text-xs">
                                    <div class="font-medium truncate text-white">
                                        {{ $isEstimated ? 'EST' : 'ACT' }}: {{ $event['title'] }}
                                    </div>
                                    <div class="text-xs text-white opacity-90">
                                        {{ $actualStartTime->format('H:i') }}{{ $completedLog ? '-' . \Carbon\Carbon::parse($completedLog->changed_at)->format('H:i') : '~' . $estimatedEndTime->format('H:i') }}
                                    </div>
                                </div>
                            </a>
                        @endforeach
                        
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>