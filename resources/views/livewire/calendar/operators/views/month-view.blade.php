<!-- Operator Month View -->
<div class="overflow-hidden bg-white dark:bg-gray-900">
    @php
        $currentDate = \Carbon\Carbon::parse($this->currentDate);
        $monthStart = $currentDate->copy()->startOfMonth();
        $monthEnd = $currentDate->copy()->endOfMonth();
        
        // Get all weeks in the month
        $weeks = [];
        $current = $monthStart->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $weekNumber = 1;
        
        while($current <= $monthEnd->copy()->endOfWeek(\Carbon\Carbon::MONDAY)) {
            $weekData = [
                'number' => $weekNumber,
                'start' => $current->copy(),
                'days' => []
            ];
            
            // Only get Monday to Saturday (skip Sunday)
            for($i = 0; $i < 6; $i++) {
                $day = $current->copy()->addDays($i);
                if ($day >= $monthStart && $day <= $monthEnd) {
                    $weekData['days'][] = $day;
                }
            }
            
            if (!empty($weekData['days'])) {
                $weeks[] = $weekData;
            }
            
            $current->addWeek();
            $weekNumber++;
        }
        
        // Days of week (Monday to Saturday)
        $weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    @endphp
    
    <!-- Month Header -->
    <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
        <div class="text-center">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ $currentDate->format('F Y') }}
            </div>
            @if($operator->shift)
                <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                    Shift: {{ $operator->shift->name }} ({{ $operator->shift->start_time }} - {{ $operator->shift->end_time }})
                </div>
            @endif
        </div>
    </div>

    <!-- Month Grid Container -->
    <div class="bg-white dark:bg-gray-900 overflow-x-auto">
        <div style="min-width: 800px;">
            
            <!-- Header Row: Week Labels (X-Axis) -->
            <div class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
                <div class="flex">
                    <div class="w-20 text-center py-3 border-r border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700">
                        <div class="text-xs font-medium text-gray-600 dark:text-gray-300">Days / Weeks</div>
                    </div>
                    @foreach($weeks as $week)
                        <div class="flex-1 text-center py-3 border-r border-gray-300 dark:border-gray-600 last:border-r-0">
                            <div class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                Week {{ $week['number'] }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $week['start']->format('M j') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Day Rows (Y-Axis) -->
            @foreach($weekDays as $dayIndex => $dayName)
                <div class="flex border-b border-gray-100 dark:border-gray-700" style="height: 160px;">
                    <!-- Day Label (Y-Axis) -->
                    <div class="w-20 bg-gray-50 dark:bg-gray-800 border-r border-gray-200 dark:border-gray-600 flex items-center justify-center">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $dayName }}
                        </div>
                    </div>
                    
                    <!-- Week Columns -->
                    @foreach($weeks as $week)
                        @php
                            // Find the day in this week that matches our current day of week
                            $dayInWeek = null;
                            foreach($week['days'] as $day) {
                                if ($day->dayOfWeek === ($dayIndex + 1)) { // Monday=1, Tuesday=2, etc.
                                    $dayInWeek = $day;
                                    break;
                                }
                            }
                            
                            $dayEvents = [];
                            $shiftEvents = [];
                            $workOrderEvents = [];
                            $isToday = false;
                            
                            if ($dayInWeek) {
                                $isToday = $dayInWeek->isToday();
                                
                                // Filter all events for this day
                                $allDayEvents = collect($events)->filter(function($event) use ($dayInWeek) {
                                    return \Carbon\Carbon::parse($event['start'])->isSameDay($dayInWeek);
                                })->toArray();
                                
                                // Separate shift events from work order events
                                $shiftEvents = array_filter($allDayEvents, function($event) {
                                    return $event['status'] === 'shift';
                                });
                                
                                $workOrderEvents = array_filter($allDayEvents, function($event) {
                                    return $event['status'] !== 'shift';
                                });
                            }
                        @endphp
                        
                        <div class="flex-1 border-r border-gray-200 dark:border-gray-600 last:border-r-0 p-2 relative {{ $isToday ? 'bg-blue-50 dark:bg-blue-900' : (!empty($shiftEvents) ? 'bg-blue-25 dark:bg-blue-950' : 'bg-white dark:bg-gray-900') }}">
                            @if($dayInWeek)
                                <!-- Date Number -->
                                <div class="absolute top-1 right-1">
                                    <span class="text-xs {{ $isToday ? 'bg-blue-600 dark:bg-blue-500 text-white w-5 h-5 rounded-full flex items-center justify-center' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $dayInWeek->format('j') }}
                                    </span>
                                </div>
                                
                                <!-- Shift Indicator -->
                                @if(!empty($shiftEvents))
                                    <div class="absolute top-1 left-1">
                                        <span class="text-xs bg-blue-200 dark:bg-blue-800 text-blue-800 dark:text-blue-200 px-1 rounded" title="{{ $operator->shift->name }} shift">
                                            S
                                        </span>
                                    </div>
                                @endif
                                
                                <!-- Work Order Events -->
                                <div class="mt-6" style="height: 110px;">
                                    @foreach(array_slice($workOrderEvents, 0, 8) as $eventIndex => $event)
                                        @php
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
                                            
                                            $eventStart = \Carbon\Carbon::parse($event['start']);
                                            $eventEnd = \Carbon\Carbon::parse($event['end']);
                                        @endphp
                                        
                                        <div class="mb-1 rounded-md cursor-pointer {{ $isShiftConflict ? 'animate-pulse' : '' }}"
                                             style="background-color: {{ $eventBgColor }}; 
                                                    height: 12px; 
                                                    width: 95%;
                                                    border: 1px solid {{ $eventBorderColor }};
                                                    box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                                            <a href="{{ url("/admin/" . auth()->user()->factory_id . "/work-orders/" . $event['work_order_id']) }}" 
                                               target="_blank"
                                               class="block w-full h-full hover:shadow-md transition-all hover:scale-105"
                                               title="{{ $statusPrefix }}: WO {{ $event['unique_id'] ?? $event['work_order_id'] }} | {{ $eventStart->format('H:i') }} - {{ $eventEnd->format('H:i') }} | Machine: {{ $event['machine'] ?? 'No Machine' }} | {{ $event['subtitle'] }}{{ $isShiftConflict ? ' | ⚠️ OUTSIDE SHIFT HOURS' : '' }}">
                                            </a>
                                        </div>
                                    @endforeach
                                    
                                    @if(count($workOrderEvents) > 8)
                                        <div class="absolute bottom-1 left-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 px-1 rounded" style="font-size: 8px;">
                                            +{{ count($workOrderEvents) - 8 }}
                                        </div>
                                    @endif
                                    
                                    <!-- Shift Conflict Indicator -->
                                    @php
                                        $conflictCount = count(array_filter($workOrderEvents, fn($event) => isset($event['shift_conflict']) && $event['shift_conflict']));
                                    @endphp
                                    @if($conflictCount > 0)
                                        <div class="absolute bottom-1 right-1 text-xs font-medium text-red-700 dark:text-red-300 bg-red-200 dark:bg-red-800 px-1 rounded" style="font-size: 8px;" title="Work orders with shift conflicts">
                                            ⚠️{{ $conflictCount }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                <!-- Empty cell for days not in this month -->
                                <div class="h-full bg-gray-100 dark:bg-gray-800 opacity-50"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- Month Summary -->
    <div class="bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600 p-3">
        <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
            <div>📅 {{ $currentDate->format('F Y') }} (Monday - Saturday view) - {{ $operator->user?->getFilamentName() ?? 'Unknown Operator' }}</div>
            <div class="flex items-center space-x-4">
                @php
                    $workOrderEvents = collect($events)->filter(fn($event) => $event['status'] !== 'shift');
                    $totalEvents = $workOrderEvents->count();
                    $runningEvents = $workOrderEvents->where('status', 'Start')->count();
                    $scheduledEvents = $totalEvents - $runningEvents;
                    $conflictEvents = $workOrderEvents->filter(fn($event) => isset($event['shift_conflict']) && $event['shift_conflict'])->count();
                @endphp
                <div>Total WOs: {{ $totalEvents }}</div>
                <div class="flex items-center space-x-1">
                    <div class="w-3 h-3 bg-red-500 rounded"></div>
                    <span>Running: {{ $runningEvents }}</span>
                </div>
                <div class="flex items-center space-x-1">
                    <div class="w-3 h-3 bg-orange-500 rounded"></div>
                    <span>Scheduled: {{ $scheduledEvents }}</span>
                </div>
                @if($conflictEvents > 0)
                    <div class="flex items-center space-x-1">
                        <div class="w-3 h-3 bg-red-600 rounded"></div>
                        <span>⚠️ Conflicts: {{ $conflictEvents }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>