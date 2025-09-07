@php
    $selectedMonth = \Carbon\Carbon::parse($this->currentDate);
    $monthStart = $selectedMonth->copy()->startOfMonth();
    $monthEnd = $selectedMonth->copy()->endOfMonth();
    
    // Get gantt data
    $plannedBars = $ganttData['planned_bars'] ?? [];
    $actualBars = $ganttData['actual_bars'] ?? [];
    $shiftBlocks = $ganttData['shift_blocks'] ?? [];
    
    // Create calendar grid (weeks and days)
    $firstDay = $monthStart->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
    $lastDay = $monthEnd->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
    
    $days = [];
    $current = $firstDay->copy();
    while ($current <= $lastDay) {
        $days[] = $current->copy();
        $current->addDay();
    }
    
    $weeks = array_chunk($days, 7);
    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    // Function to get bars for a specific day
    function getDayBars($day, $bars) {
        return collect($bars)->filter(function($bar) use ($day) {
            $barStart = \Carbon\Carbon::parse($bar['start']);
            $barEnd = \Carbon\Carbon::parse($bar['end']);
            return $barStart->isSameDay($day) || $barEnd->isSameDay($day) || 
                   ($barStart->lt($day->copy()->endOfDay()) && $barEnd->gt($day->copy()->startOfDay()));
        });
    }
@endphp

<div class="overflow-hidden bg-white dark:bg-gray-900">
    <!-- Month Header -->
    <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
        <div class="text-center">
            <div class="text-lg font-medium text-gray-700 dark:text-gray-300">
                {{ $selectedMonth->format('F Y') }}
            </div>
            @if($operator->shift)
                <div class="text-sm text-blue-600 dark:text-blue-400 mt-1">
                    Shift: {{ $operator->shift->name }} ({{ $operator->shift->start_time }} - {{ $operator->shift->end_time }})
                </div>
            @endif
        </div>
    </div>

    <!-- Month Calendar Grid -->
    <div class="overflow-x-auto">
        <div style="min-width: 800px;">
            
            <!-- Days of Week Header -->
            <div class="grid grid-cols-8 bg-gray-100 dark:bg-gray-800">
                <div class="bg-indigo-600 dark:bg-indigo-900 text-white text-xs font-semibold py-3 px-2 text-center border border-gray-200 dark:border-gray-700">
                    Week
                </div>
                @foreach($dayNames as $dayName)
                    <div class="bg-indigo-100 dark:bg-indigo-800 text-indigo-900 dark:text-white text-xs font-semibold py-3 px-2 border border-gray-200 dark:border-gray-700 text-center">
                        {{ $dayName }}
                    </div>
                @endforeach
            </div>

            <!-- Calendar Weeks with Gantt Bars -->
            @foreach($weeks as $weekIndex => $week)
                @php
                    $weekNumber = $week[0]->format('W');
                    
                    // Calculate spanning bars for this week
                    $weekPlannedBars = [];
                    $weekActualBars = [];
                    
                    foreach ($plannedBars as $bar) {
                        $barStart = \Carbon\Carbon::parse($bar['start']);
                        $barEnd = \Carbon\Carbon::parse($bar['end']);
                        
                        // Check if bar overlaps with this week
                        $weekStart = $week[0]->copy()->startOfDay();
                        $weekEnd = $week[count($week) - 1]->copy()->endOfDay();
                        
                        if ($barStart <= $weekEnd && $barEnd >= $weekStart) {
                            // Calculate which days this bar spans
                            $startDayIndex = null;
                            $endDayIndex = null;
                            
                            for ($i = 0; $i < count($week); $i++) {
                                $dayStart = $week[$i]->copy()->startOfDay();
                                $dayEnd = $week[$i]->copy()->endOfDay();
                                
                                if ($barStart <= $dayEnd && $barEnd >= $dayStart) {
                                    if ($startDayIndex === null) $startDayIndex = $i;
                                    $endDayIndex = $i;
                                }
                            }
                            
                            if ($startDayIndex !== null && $endDayIndex !== null) {
                                $weekPlannedBars[] = array_merge($bar, [
                                    'startDayIndex' => $startDayIndex,
                                    'endDayIndex' => $endDayIndex,
                                    'spanDays' => $endDayIndex - $startDayIndex + 1,
                                ]);
                            }
                        }
                    }
                    
                    foreach ($actualBars as $bar) {
                        $barStart = \Carbon\Carbon::parse($bar['start']);
                        $barEnd = \Carbon\Carbon::parse($bar['end']);
                        
                        // Check if bar overlaps with this week
                        $weekStart = $week[0]->copy()->startOfDay();
                        $weekEnd = $week[count($week) - 1]->copy()->endOfDay();
                        
                        if ($barStart <= $weekEnd && $barEnd >= $weekStart) {
                            // Calculate which days this bar spans
                            $startDayIndex = null;
                            $endDayIndex = null;
                            
                            for ($i = 0; $i < count($week); $i++) {
                                $dayStart = $week[$i]->copy()->startOfDay();
                                $dayEnd = $week[$i]->copy()->endOfDay();
                                
                                if ($barStart <= $dayEnd && $barEnd >= $dayStart) {
                                    if ($startDayIndex === null) $startDayIndex = $i;
                                    $endDayIndex = $i;
                                }
                            }
                            
                            if ($startDayIndex !== null && $endDayIndex !== null) {
                                $weekActualBars[] = array_merge($bar, [
                                    'startDayIndex' => $startDayIndex,
                                    'endDayIndex' => $endDayIndex,
                                    'spanDays' => $endDayIndex - $startDayIndex + 1,
                                ]);
                            }
                        }
                    }
                    
                    // Calculate unique work orders for proper row height (44px per work order pair)
                    $plannedWorkOrders = collect($weekPlannedBars)->pluck('work_order_id')->unique();
                    $actualWorkOrders = collect($weekActualBars)->pluck('work_order_id')->unique();
                    $uniqueWorkOrders = $plannedWorkOrders->merge($actualWorkOrders)->unique()->count();
                    $weekHeight = max(80, 50 + ($uniqueWorkOrders * 44));
                @endphp
                
                <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700 relative" 
                     style="min-height: {{ $weekHeight }}px;">
                    
                    <!-- Week Number -->
                    <div class="flex items-center justify-center bg-indigo-200 dark:bg-indigo-800 text-indigo-900 dark:text-white text-xs font-bold border-r border-gray-200 dark:border-gray-700">
                        {{ $weekNumber }}
                    </div>
                    
                    <!-- Day Cells with Day Numbers -->
                    @foreach($week as $dayIndex => $day)
                        @php
                            $isToday = $day->isToday();
                            $isCurrentMonth = $day->month === $selectedMonth->month;
                            $dayPlannedBars = getDayBars($day, $plannedBars);
                            $dayActualBars = getDayBars($day, $actualBars);
                        @endphp
                        <div class="relative border-r border-gray-200 dark:border-gray-700 last:border-r-0 {{ !$isCurrentMonth ? 'bg-gray-50 dark:bg-gray-800' : 'bg-white dark:bg-gray-900' }}">
                            <!-- Day Number -->
                            <div class="absolute top-1 left-1 text-xs font-semibold {{ $isToday ? 'text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-900 rounded px-1' : ($isCurrentMonth ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500') }}">
                                {{ $day->format('j') }}
                            </div>
                            
                            <!-- Shift Background for workdays -->
                            @if(!$day->isWeekend() && $isCurrentMonth)
                                @php
                                    // Get shift blocks that overlap with this day (handles midnight-crossing shifts)
                                    $dayShiftBlocks = collect($shiftBlocks)->filter(function($block) use ($day) {
                                        $blockStart = \Carbon\Carbon::parse($block['start'])->setTimezone(config('app.timezone'));
                                        $blockEnd = \Carbon\Carbon::parse($block['end'])->setTimezone(config('app.timezone'));
                                        $dayStart = $day->copy()->startOfDay();
                                        $dayEnd = $day->copy()->endOfDay();
                                        
                                        // Check if shift block overlaps with this day
                                        return $blockStart <= $dayEnd && $blockEnd >= $dayStart;
                                    });
                                @endphp
                                @if($dayShiftBlocks->count() > 0)
                                    @php $firstShiftBlock = $dayShiftBlocks->first(); @endphp
                                    <div class="absolute inset-0 opacity-30" 
                                         style="background-color: {{ $firstShiftBlock['backgroundColor'] ?? '#e5f3ff' }};"></div>
                                @endif
                            @endif
                            
                            <!-- Small indicators for work orders in this day -->
                            @if($dayPlannedBars->count() > 0 || $dayActualBars->count() > 0)
                                <div class="absolute bottom-1 right-1 flex flex-col space-y-1">
                                    @if($dayPlannedBars->count() > 0)
                                        <div class="w-2 h-2 bg-blue-500 rounded-full" title="{{ $dayPlannedBars->count() }} planned work order{{ $dayPlannedBars->count() !== 1 ? 's' : '' }}"></div>
                                    @endif
                                    @if($dayActualBars->count() > 0)
                                        <div class="w-2 h-2 bg-gray-400 rounded-full" title="{{ $dayActualBars->count() }} actual work order{{ $dayActualBars->count() !== 1 ? 's' : '' }}"></div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <!-- Spanning Bars Container -->
                    <div class="absolute inset-0 pointer-events-none" style="left: calc(100% / 8); right: 0;">
                        @php 
                            // Group planned and actual bars by work order for proper stacking
                            $groupedPlannedBars = collect($weekPlannedBars)->groupBy('work_order_id');
                            $groupedActualBars = collect($weekActualBars)->groupBy('work_order_id');
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
                                    $weekDaysCount = count($week);
                                    $startPercent = ($bar['startDayIndex'] / $weekDaysCount) * 100;
                                    $widthPercent = ($bar['spanDays'] / $weekDaysCount) * 100;
                                    $topPosition = 25 + ($rowIndex * 44) + ($plannedIndex * 20); // Planned bars at top
                                    $isShiftConflict = $bar['shift_conflict'] ?? false;
                                    $barColor = $isShiftConflict ? '#dc2626' : $bar['backgroundColor'];
                                    $borderColor = $isShiftConflict ? '#b91c1c' : $bar['borderColor'];
                                @endphp
                                <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $bar['work_order_id']) }}" 
                                   target="_blank"
                                   class="absolute rounded shadow-sm border transition-all hover:shadow-lg pointer-events-auto text-[10px] font-semibold"
                                   style="background-color: {{ $barColor }}; 
                                          border-color: {{ $borderColor }};
                                          left: {{ $startPercent }}%; 
                                          width: {{ $widthPercent }}%;
                                          top: {{ $topPosition }}px; 
                                          height: 20px;
                                          line-height: 20px;
                                          z-index: 20;"
                                   title="📅 Planned: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('M d, H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ $bar['machine'] }}">
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
                                    $weekDaysCount = count($week);
                                    $startPercent = ($bar['startDayIndex'] / $weekDaysCount) * 100;
                                    $widthPercent = ($bar['spanDays'] / $weekDaysCount) * 100;
                                    $topPosition = 25 + ($rowIndex * 44) + 22 + ($actualIndex * 20); // Actual bars below planned (22px offset)
                                    $progress = $bar['progress'] ?? 0;
                                    $progressColor = $bar['progressColor'] ?? '#6b7280';
                                @endphp
                                <a href="{{ url('/admin/' . auth()->user()->factory_id . '/work-orders/' . $bar['work_order_id']) }}" 
                                   target="_blank"
                                   class="absolute rounded shadow-sm border transition-all hover:shadow-lg pointer-events-auto overflow-hidden text-[10px] font-semibold"
                                   style="background-color: {{ $bar['backgroundColor'] }}; 
                                          border-color: {{ $bar['borderColor'] }};
                                          left: {{ $startPercent }}%; 
                                          width: {{ $widthPercent }}%;
                                          top: {{ $topPosition }}px; 
                                          height: 20px;
                                          line-height: 20px;
                                          z-index: 20;"
                                   title="⚡ Actual: {{ $bar['unique_id'] }} | {{ \Carbon\Carbon::parse($bar['start'])->format('M d, H:i') }} - {{ \Carbon\Carbon::parse($bar['end'])->format('H:i') }} | {{ ucfirst($bar['status']) }} | {{ $progress }}% Complete">
                                    
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
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>