@php
    $selectedDay = \Carbon\Carbon::parse($this->currentDate)->setTimezone(config('app.timezone'));
    $dayStart = $selectedDay->copy()->startOfDay();
    $dayEnd = $selectedDay->copy()->endOfDay();
    
    // Get gantt data
    $allPlannedBars = $ganttData['planned_bars'] ?? [];
    $allActualBars = $ganttData['actual_bars'] ?? [];
    
    // Filter bars for this specific day (including multi-day work orders)
    $plannedBars = collect($allPlannedBars)->filter(function($bar) use ($selectedDay) {
        $barStart = \Carbon\Carbon::parse($bar['start'])->setTimezone(config('app.timezone'));
        $barEnd = \Carbon\Carbon::parse($bar['end'])->setTimezone(config('app.timezone'));
        
        // Work order spans this day if:
        // 1. It starts on this day, OR
        // 2. It ends on this day, OR  
        // 3. This day is between start and end dates
        return $barStart->isSameDay($selectedDay) || 
               $barEnd->isSameDay($selectedDay) || 
               ($selectedDay->gt($barStart->startOfDay()) && $selectedDay->lt($barEnd->endOfDay()));
    })->values()->toArray();
    
    $actualBars = collect($allActualBars)->filter(function($bar) use ($selectedDay) {
        $barStart = \Carbon\Carbon::parse($bar['start'])->setTimezone(config('app.timezone'));
        $barEnd = \Carbon\Carbon::parse($bar['end'])->setTimezone(config('app.timezone'));
        
        // Work order spans this day if:
        // 1. It starts on this day, OR
        // 2. It ends on this day, OR  
        // 3. This day is between start and end dates
        return $barStart->isSameDay($selectedDay) || 
               $barEnd->isSameDay($selectedDay) || 
               ($selectedDay->gt($barStart->startOfDay()) && $selectedDay->lt($barEnd->endOfDay()));
    })->values()->toArray();
    $shiftBlocks = $ganttData['shift_blocks'] ?? [];
    
    // Create hourly intervals for precise day view
    $hourlyIntervals = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $hourlyIntervals[] = [
            'hour' => $hour,
            'label' => sprintf('%02d:00', $hour)
        ];
    }
    
    // Function to calculate precise position within the day
    function calculateDayBarPosition($startTime, $endTime, $dayStart, $dayEnd) {
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
        if ($widthPercent < 0.5) {
            $widthPercent = 0.5;
        }
        
        return [
            'left' => max(0, min(100, $leftPercent)),
            'width' => min(100 - $leftPercent, $widthPercent)
        ];
    }
    
    // Calculate unique work orders for proper row height (50px per work order pair)
    $plannedWorkOrders = collect($plannedBars)->pluck('work_order_id')->unique();
    $actualWorkOrders = collect($actualBars)->pluck('work_order_id')->unique();
    $uniqueWorkOrders = $plannedWorkOrders->merge($actualWorkOrders)->unique()->count();
    $rowHeight = max(120, 60 + ($uniqueWorkOrders * 50));
@endphp

<div class="overflow-hidden bg-white dark:bg-gray-900">
    <!-- Day Header -->
    <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
        <div class="text-center">
            <div class="text-lg font-medium text-gray-700 dark:text-gray-300">
                {{ $selectedDay->format('l, F j, Y') }}
                @if($selectedDay->isToday())
                    <span class="text-blue-600 dark:text-blue-400 text-sm font-semibold ml-2">TODAY</span>
                @endif
            </div>
            @if($operator->shift)
                <div class="text-sm text-blue-600 dark:text-blue-400 mt-1">
                    Shift: {{ $operator->shift->name }} ({{ $operator->shift->start_time }} - {{ $operator->shift->end_time }})
                </div>
            @endif
        </div>
    </div>

    <!-- Day Gantt Chart -->
    <div class="overflow-x-auto" style="max-height: 600px;">
        <div style="min-width: 1200px;">
            
            <!-- Hourly Time Header -->
            <div class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
                <div class="flex">
                    <div class="text-center py-3 border-r border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700" style="width: 100px;">
                        <div class="text-xs font-medium text-gray-600 dark:text-gray-300">Work Orders</div>
                    </div>
                    <div class="flex-1 flex">
                        @foreach($hourlyIntervals as $interval)
                            <div class="flex-1 text-center py-2 border-r border-gray-200 dark:border-gray-600 {{ $interval['hour'] % 2 === 0 ? 'bg-gray-50 dark:bg-gray-800' : 'bg-white dark:bg-gray-900' }}">
                                <div class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                    {{ $interval['label'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Single Row with All Bars -->
            <div class="flex relative" style="height: {{ $rowHeight }}px;">
                <!-- Row Label -->
                <div class="bg-gray-50 dark:bg-gray-800 border-r border-gray-200 dark:border-gray-600 flex flex-col items-center justify-start pt-4" style="width: 100px;">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $selectedDay->format('M j') }}
                    </div>
                    @if(count($plannedBars) > 0 || count($actualBars) > 0)
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ max(count($plannedBars), count($actualBars)) }} WO{{ max(count($plannedBars), count($actualBars)) !== 1 ? 's' : '' }}
                        </div>
                    @endif
                </div>
                
                <!-- Timeline Area -->
                <div class="flex-1 relative">
                    <!-- Shift Background Blocks -->
                    @foreach($shiftBlocks as $shiftBlock)
                        @php
                            $shiftPosition = calculateDayBarPosition($shiftBlock['start'], $shiftBlock['end'], $dayStart, $dayEnd);
                        @endphp
                        <div class="absolute top-0 bottom-0 border opacity-75"
                             style="left: {{ $shiftPosition['left'] }}%; 
                                    width: {{ $shiftPosition['width'] }}%;
                                    background-color: {{ $shiftBlock['backgroundColor'] ?? '#e5f3ff' }};
                                    border-color: {{ $shiftBlock['borderColor'] ?? '#3b82f6' }};"
                             title="Shift: {{ $shiftBlock['shift_name'] }}">
                        </div>
                    @endforeach
                    
                    <!-- Hourly Grid Lines -->
                    <div class="absolute inset-0 flex">
                        @foreach($hourlyIntervals as $interval)
                            <div class="flex-1 border-r border-gray-100 dark:border-gray-600 {{ $interval['hour'] % 6 === 0 ? 'border-r-gray-300 dark:border-r-gray-500' : '' }}">
                                <!-- Sub-hour markers (15-minute intervals) -->
                                <div class="h-full flex">
                                    <div class="flex-1 border-r border-gray-50 dark:border-gray-700"></div>
                                    <div class="flex-1 border-r border-gray-50 dark:border-gray-700"></div>
                                    <div class="flex-1 border-r border-gray-50 dark:border-gray-700"></div>
                                    <div class="flex-1"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <!-- Current Time Indicator -->
                    @if($selectedDay->isToday())
                        @php
                            $currentTime = now()->setTimezone(config('app.timezone'));
                            $currentTimePercent = ($currentTime->hour * 60 + $currentTime->minute) / 1440 * 100;
                        @endphp
                        <div class="absolute top-0 bottom-0 w-0.5 bg-red-500 z-40" 
                             style="left: {{ $currentTimePercent }}%;">
                            <div class="absolute -top-2 -left-2 w-4 h-4 bg-red-500 rounded-full"></div>
                            <div class="absolute top-2 left-2 bg-red-500 text-white text-xs px-1 rounded whitespace-nowrap">
                                {{ $currentTime->format('H:i') }}
                            </div>
                        </div>
                    @endif
                    
                    @php
                        // Group planned and actual bars by work order for proper stacking
                        $groupedPlannedBars = collect($plannedBars)->groupBy('work_order_id');
                        $groupedActualBars = collect($actualBars)->groupBy('work_order_id');
                        $allWorkOrderIds = $groupedPlannedBars->keys()->merge($groupedActualBars->keys())->unique();
                        $rowIndex = 0;
                    @endphp
                    
                    <!-- Render bars grouped by work order for proper stacking -->
                    @foreach($allWorkOrderIds as $workOrderId)
                        @php
                            $plannedBarsForWO = $groupedPlannedBars->get($workOrderId, collect());
                            $actualBarsForWO = $groupedActualBars->get($workOrderId, collect());
                        @endphp
                        
                        <!-- Planned Bars for this Work Order (Top Row) -->
                        @foreach($plannedBarsForWO as $plannedIndex => $bar)
                            @php
                                $barStart = \Carbon\Carbon::parse($bar['start'])->setTimezone(config('app.timezone'));
                                $barEnd = \Carbon\Carbon::parse($bar['end'])->setTimezone(config('app.timezone'));
                                $barPosition = calculateDayBarPosition($bar['start'], $bar['end'], $dayStart, $dayEnd);
                                $topPosition = 10 + ($rowIndex * 50) + ($plannedIndex * 24); // Planned bars at top
                                $isShiftConflict = $bar['shift_conflict'] ?? false;
                                $barColor = $isShiftConflict ? '#dc2626' : $bar['backgroundColor'];
                                $borderColor = $isShiftConflict ? '#b91c1c' : $bar['borderColor'];
                                
                                // Check if work order extends beyond current day
                                $startsEarlier = $barStart->lt($dayStart);
                                $endsLater = $barEnd->gt($dayEnd);
                                $isMultiDay = !$barStart->isSameDay($barEnd);
                            @endphp
                            <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $bar['work_order_id']) }}" 
                               target="_blank"
                               class="absolute rounded shadow-sm border transition-all hover:shadow-lg z-30 text-[10px] font-semibold"
                               style="background-color: {{ $barColor }}; 
                                      border-color: {{ $borderColor }};
                                      left: {{ $barPosition['left'] }}%; 
                                      width: {{ $barPosition['width'] }}%;
                                      top: {{ $topPosition }}px; 
                                      height: 20px;
                                      line-height: 20px;
                                      min-width: 40px;"
                               title="📅 Planned: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('M d, H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ $bar['machine'] }} | {{ $bar['subtitle'] }}">
                                <div class="px-2 py-0.5 text-white text-xs font-semibold leading-tight truncate">
                                    WO {{ $bar['unique_id'] }}
                                    @if($isShiftConflict)
                                        <span class="text-xs">⚠️</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                        
                        <!-- Actual Bars for this Work Order (Bottom Row) -->
                        @foreach($actualBarsForWO as $actualIndex => $bar)
                            @php
                                $barPosition = calculateDayBarPosition($bar['start'], $bar['end'], $dayStart, $dayEnd);
                                $topPosition = 10 + ($rowIndex * 50) + 26 + ($actualIndex * 24); // Actual bars below planned (26px offset)
                                $progress = $bar['progress'] ?? 0;
                                $progressColor = $bar['progressColor'] ?? '#6b7280';
                            @endphp
                            <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $bar['work_order_id']) }}" 
                               target="_blank"
                               class="absolute rounded shadow-sm border transition-all hover:shadow-lg z-30 overflow-hidden text-[10px] font-semibold"
                               style="background-color: {{ $bar['backgroundColor'] }}; 
                                      border-color: {{ $bar['borderColor'] }};
                                      left: {{ $barPosition['left'] }}%; 
                                      width: {{ $barPosition['width'] }}%;
                                      top: {{ $topPosition }}px; 
                                      height: 20px;
                                      line-height: 20px;
                                      min-width: 40px;"
                               title="⚡ Actual: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('M d, H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ ucfirst($bar['status']) }} | {{ $progress }}% Complete{{ $bar['is_running'] ? ' (Running)' : '' }}">
                                
                                <!-- Progress Fill -->
                                <div class="absolute top-0 left-0 h-full transition-all duration-300"
                                     style="width: {{ $progress }}%; background-color: {{ $progressColor }};"></div>
                                
                                <!-- Text Content -->
                                <div class="relative px-2 py-0.5 text-gray-700 dark:text-gray-900 text-xs font-semibold leading-tight truncate z-10 mix-blend-difference">
                                    {{ $progress > 0 ? $progress . '%' : 'WO ' . $bar['unique_id'] }}
                                    @if($bar['is_running'])
                                        <span class="text-xs">🔴</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                        
                        @php $rowIndex++; @endphp
                    @endforeach
                    
                    <!-- No Work Orders Message -->
                    @if(count($plannedBars) === 0 && count($actualBars) === 0)
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-gray-500 dark:text-gray-400 text-center">
                                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-sm">No work orders scheduled for this day</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>