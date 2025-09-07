<!-- Operator Day View -->
<div class="overflow-hidden bg-white dark:bg-gray-900">
    @php
        $currentDate = \Carbon\Carbon::parse($this->currentDate);
        $dayEvents = collect($events)->filter(function($event) use ($currentDate) {
            // Skip shift blocks for work order display
            if ($event['status'] === 'shift') {
                return false;
            }
            return \Carbon\Carbon::parse($event['start'])->isSameDay($currentDate);
        });
        
        // Get the operator's shift for visual reference
        $operatorShift = $operator->shift;
        
        // Create 2-hour interval grid for full 24 hours (00:00 to 22:00, 12 intervals)
        $timeIntervals = [];
        for ($hour = 0; $hour < 24; $hour += 2) {
            $timeIntervals[] = $hour;
        }
        
        // Function to determine if time slot is within operator's shift
        function isWithinOperatorShift($hour, $operatorShift) {
            if (!$operatorShift) return false;
            
            $currentTime = sprintf('%02d:00:00', $hour);
            $startTime = $operatorShift->start_time;
            $endTime = $operatorShift->end_time;
            
            // Handle shifts that cross midnight
            if ($endTime < $startTime) {
                return ($currentTime >= $startTime || $currentTime < $endTime);
            } else {
                return ($currentTime >= $startTime && $currentTime < $endTime);
            }
        }
        
        // Check if we have shift events for this day
        $shiftEvents = collect($events)->filter(function($event) use ($currentDate) {
            if ($event['status'] !== 'shift') {
                return false;
            }
            return \Carbon\Carbon::parse($event['start'])->isSameDay($currentDate);
        });
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
            @if($operatorShift)
                <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                    Shift: {{ $operatorShift->name }} ({{ $operatorShift->start_time }} - {{ $operatorShift->end_time }})
                </div>
            @endif
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
                    // Filter events for this time slot
                    $timeSlotEvents = $dayEvents->filter(function($event) use ($hour) {
                        $eventStart = \Carbon\Carbon::parse($event['start']);
                        
                        // Convert to minutes for more precise comparison
                        $startMinute = $eventStart->hour * 60 + $eventStart->minute;
                        $slotStartMinute = $hour * 60;
                        $slotEndMinute = ($hour + 2) * 60;
                        
                        // Only show work order in the time slot where it STARTS
                        return $startMinute >= $slotStartMinute && $startMinute < $slotEndMinute;
                    });
                    
                    $isCurrentHour = $currentDate->isToday() && now()->hour >= $hour && now()->hour < $hour + 2;
                    
                    // Check if this time slot is within the operator's shift
                    $isShiftHour = isWithinOperatorShift($hour, $operatorShift);
                    
                    // Check if this time slot overlaps with any shift events
                    $timeSlotShift = $shiftEvents->filter(function($event) use ($hour, $currentDate) {
                        $eventStart = \Carbon\Carbon::parse($event['start']);
                        $eventEnd = \Carbon\Carbon::parse($event['end']);
                        
                        // Check if shift overlaps with this time slot
                        $slotStart = $currentDate->copy()->setHour($hour)->setMinute(0);
                        $slotEnd = $slotStart->copy()->addHours(2);
                        
                        return $eventStart < $slotEnd && $eventEnd > $slotStart;
                    })->first();
                    
                    // Background color for shift hours vs non-shift hours
                    if ($timeSlotShift || $isShiftHour) {
                        $bgColor = 'bg-blue-50 dark:bg-blue-900'; // Light blue for shift hours
                        $shiftInfo = "Shift Hours ({$operatorShift->name})";
                    } else {
                        $bgColor = 'bg-gray-100 dark:bg-gray-700'; // Gray for non-shift hours
                        $shiftInfo = 'Off Shift';
                    }
                @endphp
                
                <div class="flex border-b border-gray-100 dark:border-gray-700" style="height: 80px;">
                    <!-- Time Label (Y-Axis) -->
                    <div class="bg-gray-50 dark:bg-gray-800 border-r border-gray-200 dark:border-gray-600 flex items-center justify-center" style="width: 100px;">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 text-center">
                            {{ sprintf('%02d:00', $hour) }}
                            @if($isShiftHour)
                                <div class="text-xs text-blue-600 dark:text-blue-400">●</div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Date Column with Shift Background -->
                    <div class="flex-1 border-r border-gray-200 dark:border-gray-600 relative {{ $bgColor }}" 
                         title="{{ $shiftInfo }}">
                        
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
                        
                        <!-- Work Order Events -->
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
                                
                                // Use full width for operator view
                                $leftPercent = 2;
                                $widthPercent = 96;
                                
                                // Determine color based on status and shift conflict
                                $isShiftConflict = isset($event['shift_conflict']) && $event['shift_conflict'];
                                if ($isShiftConflict) {
                                    $eventBgColor = '#dc2626'; // Red for shift conflicts
                                    $eventBorderColor = '#b91c1c';
                                    $statusPrefix = '⚠️ CONFLICT';
                                } elseif ($event['status'] === 'Start') {
                                    $eventBgColor = '#ef4444'; // Red for running
                                    $eventBorderColor = '#dc2626';
                                    $statusPrefix = '🔴 RUNNING';
                                } else {
                                    $eventBgColor = '#f97316'; // Orange for planned
                                    $eventBorderColor = '#ea580c';
                                    $statusPrefix = '⏰ PLANNED';
                                }
                            @endphp
                            
                            <a href="{{ url("/admin/" . auth()->user()->factory_id . "/work-orders/" . $event['work_order_id']) }}" 
                               target="_blank"
                               class="absolute rounded shadow-sm border cursor-pointer transition-all hover:shadow-lg block"
                               style="background-color: {{ $eventBgColor }}; 
                                      border-color: {{ $eventBorderColor }};
                                      top: {{ $topPercent }}%; 
                                      height: {{ $heightPercent }}%; 
                                      left: {{ $leftPercent }}%;
                                      width: {{ $widthPercent }}%;
                                      margin: 1px;
                                      z-index: 30;"
                               title="{{ $statusPrefix }}: WO {{ $event['unique_id'] ?? $event['work_order_id'] }} | {{ $eventStart->format('H:i') }} - {{ $eventEnd->format('H:i') }} | Machine: {{ $event['machine'] ?? 'No Machine' }} | {{ $event['subtitle'] }}">
                                
                                <!-- Work Order Content -->
                                <div class="px-2 py-1 h-full flex flex-col justify-center text-xs overflow-hidden">
                                    <div class="font-medium truncate text-white">
                                        {{ $event['title'] }}
                                    </div>
                                    <div class="text-xs text-white opacity-90">
                                        {{ $eventStart->format('H:i') }} - {{ $eventEnd->format('H:i') }}
                                        @if($isShiftConflict)
                                            <span class="block">⚠️ Outside Shift</span>
                                        @endif
                                    </div>
                                    @if(isset($event['machine']) && $event['machine'] !== 'No Machine')
                                        <div class="text-xs text-white opacity-75 truncate">
                                            {{ $event['machine'] }}
                                        </div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                        
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>