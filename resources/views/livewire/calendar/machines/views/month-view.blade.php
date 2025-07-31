<!-- Month View -->
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
                            $actualDayEvents = [];
                            $isToday = false;
                            if ($dayInWeek) {
                                $isToday = $dayInWeek->isToday();
                                
                                // Get ALL planned events for this day (show ALL scheduled work orders in orange)
                                $dayEvents = collect($events)->filter(function($event) use ($dayInWeek) {
                                    return \Carbon\Carbon::parse($event['start'])->isSameDay($dayInWeek);
                                });
                                
                                // Get ONLY work orders that have actual execution logs for this day (subset for blue bars)
                                $actualDayEvents = collect($events)->filter(function($event) use ($dayInWeek) {
                                    $workOrderId = $event['work_order_id'];
                                    $workOrder = \App\Models\WorkOrder::with(['workOrderLogs' => function($query) use ($dayInWeek) {
                                        $query->whereDate('changed_at', $dayInWeek->format('Y-m-d'))
                                              ->whereIn('status', ['Start', 'Completed'])
                                              ->orderBy('changed_at');
                                    }])->find($workOrderId);
                                    
                                    // Only include if this specific work order has execution logs on this day
                                    return $workOrder && $workOrder->workOrderLogs->isNotEmpty() && 
                                           \Carbon\Carbon::parse($event['start'])->isSameDay($dayInWeek);
                                });
                            }
                        @endphp
                        
                        <div class="flex-1 border-r border-gray-200 dark:border-gray-600 last:border-r-0 p-2 relative {{ $isToday ? 'bg-blue-50 dark:bg-blue-900' : 'bg-white dark:bg-gray-900' }}">
                            @if($dayInWeek)
                                <!-- Date Number -->
                                <div class="absolute top-1 right-1">
                                    <span class="text-xs {{ $isToday ? 'bg-blue-600 dark:bg-blue-500 text-white w-5 h-5 rounded-full flex items-center justify-center' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $dayInWeek->format('j') }}
                                    </span>
                                </div>
                                
                                <!-- Work Order Events - Horizontal Width Split -->
                                <div class="mt-4 flex" style="height: 120px; border: 1px solid #f3f4f6;">
                                    <!-- LEFT HALF: PLANNED Work Orders (Orange) -->
                                    <div class="w-1/2 border-r-2 border-gray-300 dark:border-gray-600 relative p-1">
                                        @foreach($dayEvents->take(6) as $eventIndex => $event)
                                            <div class="mb-1 rounded-md cursor-pointer"
                                                 style="background-color: #fb923c; 
                                                        height: 12px; 
                                                        width: 95%;
                                                        opacity: 0.9;
                                                        border: 1px solid #ea580c;
                                                        box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                                                <a href="{{ url("/admin/" . auth()->user()->factory_id . "/work-orders/" . $event['work_order_id']) }}" 
                                                   target="_blank"
                                                   class="block w-full h-full hover:shadow-md transition-all hover:scale-105"
                                                   title="PLANNED: WO {{ $event['unique_id'] ?? $event['work_order_id'] }} | {{ \Carbon\Carbon::parse($event['start'])->format('H:i') }} - {{ \Carbon\Carbon::parse($event['end'])->format('H:i') }} | Operator: {{ $event['operator'] ?? 'Unassigned' }} | Part: {{ $event['subtitle'] ?? 'Unknown' }}">
                                                </a>
                                            </div>
                                        @endforeach
                                        
                                        @if($dayEvents->count() > 6)
                                            <div class="absolute bottom-1 left-1 text-xs font-medium text-orange-700 dark:text-orange-300 bg-orange-200 dark:bg-orange-800 px-1 rounded" style="font-size: 8px;">
                                                +{{ $dayEvents->count() - 6 }}
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- RIGHT HALF: ACTUAL Work Orders (Blue) -->
                                    <div class="w-1/2 relative p-1">
                                        @foreach($actualDayEvents->take(6) as $eventIndex => $event)
                                            @php
                                                $workOrderId = $event['work_order_id'];
                                                $workOrder = \App\Models\WorkOrder::with(['workOrderLogs' => function($query) use ($dayInWeek) {
                                                    $query->whereDate('changed_at', $dayInWeek->format('Y-m-d'))
                                                          ->whereIn('status', ['Start', 'Completed'])
                                                          ->orderBy('changed_at');
                                                }])->find($workOrderId);
                                                
                                                $startLog = $workOrder->workOrderLogs->where('status', 'Start')->first();
                                                $completedLog = $workOrder->workOrderLogs->where('status', 'Completed')->first();
                                                
                                                if ($completedLog) {
                                                    $actualBgColor = '#3b82f6'; // Blue for completed
                                                    $actualBorderColor = '#2563eb';
                                                    $statusText = 'ACTUAL';
                                                    $timeText = \Carbon\Carbon::parse($startLog->changed_at)->format('H:i') . ' - ' . \Carbon\Carbon::parse($completedLog->changed_at)->format('H:i');
                                                } else {
                                                    $actualBgColor = '#7dd3fc'; // Light blue for estimated
                                                    $actualBorderColor = '#0ea5e9';
                                                    $statusText = 'ESTIMATED';
                                                    $plannedStart = \Carbon\Carbon::parse($event['start']);
                                                    $plannedEnd = \Carbon\Carbon::parse($event['end']);
                                                    $plannedDuration = $plannedStart->diffInMinutes($plannedEnd);
                                                    $estimatedEnd = \Carbon\Carbon::parse($startLog->changed_at)->addMinutes($plannedDuration);
                                                    $timeText = \Carbon\Carbon::parse($startLog->changed_at)->format('H:i') . ' ~ ' . $estimatedEnd->format('H:i');
                                                }
                                            @endphp
                                            
                                            <div class="mb-1 rounded-md cursor-pointer {{ !$completedLog ? 'border-dashed' : '' }}"
                                                 style="background-color: {{ $actualBgColor }}; 
                                                        height: 12px; 
                                                        width: 95%;
                                                        border: 1px solid {{ $actualBorderColor }};
                                                        box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                                                <a href="{{ url("/admin/" . auth()->user()->factory_id . "/work-orders/" . $event['work_order_id']) }}" 
                                                   target="_blank"
                                                   class="block w-full h-full hover:shadow-md transition-all hover:scale-105"
                                                   title="{{ $statusText }}: WO {{ $event['unique_id'] ?? $event['work_order_id'] }} | {{ $timeText }} | Operator: {{ $event['operator'] ?? 'Unassigned' }}">
                                                </a>
                                            </div>
                                        @endforeach
                                        
                                        @if($actualDayEvents->count() > 6)
                                            <div class="absolute bottom-1 right-1 text-xs font-medium text-blue-700 dark:text-blue-300 bg-blue-200 dark:bg-blue-800 px-1 rounded" style="font-size: 8px;">
                                                +{{ $actualDayEvents->count() - 6 }}
                                            </div>
                                        @endif
                                    </div>
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
            <div>📅 {{ $currentDate->format('F Y') }} (Monday - Saturday view)</div>
            <div class="flex items-center space-x-4">
                @php
                    $totalEvents = collect($events)->count();
                    $runningEvents = collect($events)->where('status', 'Start')->count();
                    $scheduledEvents = $totalEvents - $runningEvents;
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
            </div>
        </div>
    </div>
</div>
