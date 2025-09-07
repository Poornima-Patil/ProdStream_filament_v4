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
@endphp

<div class="overflow-hidden bg-white dark:bg-gray-900">
    <!-- Week Header -->
    <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
        <div class="text-center">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Week of {{ $weekStart->format('M j') }} - {{ $weekStart->copy()->addDays(5)->format('M j, Y') }}
            </div>
            @if($operator->shift)
                <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                    Shift: {{ $operator->shift->name }} ({{ $operator->shift->start_time }} - {{ $operator->shift->end_time }})
                </div>
            @endif
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
                    
                    // Get shift blocks that overlap with this day (handles midnight-crossing shifts)
                    $dayShiftBlocks = collect($shiftBlocks)->filter(function($block) use ($day) {
                        $blockStart = \Carbon\Carbon::parse($block['start'])->setTimezone(config('app.timezone'));
                        $blockEnd = \Carbon\Carbon::parse($block['end'])->setTimezone(config('app.timezone'));
                        $dayStart = $day->copy()->startOfDay();
                        $dayEnd = $day->copy()->endOfDay();
                        
                        // Check if shift block overlaps with this day
                        return $blockStart <= $dayEnd && $blockEnd >= $dayStart;
                    });
                    
                    $maxBars = max($dayPlannedBars->count(), $dayActualBars->count());
                    $rowHeight = max(100, 40 + ($maxBars * 26)); // Base height + bar spacing
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
                        <!-- Shift Background Blocks -->
                        @foreach($dayShiftBlocks as $shiftBlock)
                            @php
                                $shiftPosition = calculateBarPosition($shiftBlock['start'], $shiftBlock['end'], $dayStart, $dayEnd);
                            @endphp
                            <div class="absolute top-0 bottom-0 border opacity-75"
                                 style="left: {{ $shiftPosition['left'] }}%; 
                                        width: {{ $shiftPosition['width'] }}%;
                                        background-color: {{ $shiftBlock['backgroundColor'] ?? '#e5f3ff' }};
                                        border-color: {{ $shiftBlock['borderColor'] ?? '#3b82f6' }};"
                             title="Shift: {{ $shiftBlock['shift_name'] }}">
                            </div>
                        @endforeach
                        
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
                        
                        <!-- Planned Bars (Top Row) -->
                        @foreach($dayPlannedBars as $index => $bar)
                            @php
                                $barPosition = calculateBarPosition($bar['start'], $bar['end'], $dayStart, $dayEnd);
                                $topPosition = 8 + ($index * 26);
                                $isShiftConflict = $bar['shift_conflict'] ?? false;
                                $barColor = $isShiftConflict ? '#dc2626' : $bar['backgroundColor'];
                                $borderColor = $isShiftConflict ? '#b91c1c' : $bar['borderColor'];
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
                               title="Planned: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ $bar['machine'] }}">
                                <div class="px-2 py-0.5 text-white text-xs font-medium leading-tight truncate">
                                    WO {{ $bar['unique_id'] }}
                                    @if($isShiftConflict)
                                        <span class="text-xs">⚠️</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                        
                        <!-- Actual Bars (Bottom Row) -->
                        @foreach($dayActualBars as $index => $bar)
                            @php
                                $barPosition = calculateBarPosition($bar['start'], $bar['end'], $dayStart, $dayEnd);
                                $topPosition = 32 + ($index * 26); // Start after planned bars
                                $progress = $bar['progress'] ?? 0;
                                $progressColor = $bar['progressColor'] ?? '#6b7280';
                            @endphp
                            <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $bar['work_order_id']) }}" 
                               target="_blank"
                               class="absolute rounded shadow-sm border transition-all hover:shadow-lg z-20 overflow-hidden"
                               style="background-color: {{ $bar['backgroundColor'] }}; 
                                      border-color: {{ $bar['borderColor'] }};
                                      left: {{ $barPosition['left'] }}%; 
                                      width: {{ $barPosition['width'] }}%;
                                      top: {{ $topPosition }}px; 
                                      height: 20px;
                                      min-width: 30px;"
                               title="Actual: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ $bar['status'] }} | {{ $progress }}% Complete">
                                
                                <!-- Progress Fill -->
                                <div class="absolute top-0 left-0 h-full transition-all duration-300"
                                     style="width: {{ $progress }}%; background-color: {{ $progressColor }};"></div>
                                
                                <!-- Text Content -->
                                <div class="relative px-2 py-0.5 text-gray-700 dark:text-gray-900 text-xs font-medium leading-tight truncate z-10 mix-blend-difference">
                                    {{ $progress > 0 ? $progress . '%' : 'WO ' . $bar['unique_id'] }}
                                    @if($bar['is_running'])
                                        <span class="text-xs">🔴</span>
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