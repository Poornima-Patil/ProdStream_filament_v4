<div class="space-y-6">
    <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
        <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">
            Event Details
        </h3>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Event Type:</span>
                <div class="flex items-center gap-2 mt-1">
                    @php
                        $icon = match($record->event_type) {
                            'dependency_satisfied' => 'heroicon-o-arrow-right-circle',
                            'status_change' => 'heroicon-o-arrow-path',
                            'work_order_triggered' => 'heroicon-o-play',
                            default => 'heroicon-o-information-circle',
                        };
                        $color = match($record->event_type) {
                            'dependency_satisfied' => 'text-green-600',
                            'status_change' => 'text-yellow-600',
                            'work_order_triggered' => 'text-blue-600',
                            default => 'text-gray-600',
                        };
                    @endphp
                    <x-dynamic-component :component="$icon" class="w-5 h-5 {{ $color }}" />
                    <span class="text-sm capitalize">{{ str_replace('_', ' ', $record->event_type) }}</span>
                </div>
            </div>

            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Timestamp:</span>
                <p class="text-sm text-gray-900 dark:text-gray-100 mt-1">
                    {{ $record->created_at->format('M j, Y g:i:s A') }}
                </p>
            </div>
        </div>
    </div>

    <div class="border rounded-lg p-4">
        <h4 class="text-md font-semibold mb-2 text-gray-900 dark:text-gray-100">Event Description</h4>
        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $record->event_description }}</p>
    </div>

    @if($record->triggered_work_order_id || $record->triggering_work_order_id)
    <div class="border rounded-lg p-4">
        <h4 class="text-md font-semibold mb-3 text-gray-900 dark:text-gray-100">Work Orders Involved</h4>

        <div class="grid grid-cols-2 gap-4">
            @if($record->triggering_work_order_id)
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Triggering Work Order:</span>
                <p class="text-sm text-gray-900 dark:text-gray-100 mt-1 font-mono">
                    {{ $record->triggeringWorkOrder?->unique_id ?? 'N/A' }}
                </p>
            </div>
            @endif

            @if($record->triggered_work_order_id)
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Triggered Work Order:</span>
                <p class="text-sm text-gray-900 dark:text-gray-100 mt-1 font-mono">
                    {{ $record->triggeredWorkOrder?->unique_id ?? 'N/A' }}
                </p>
            </div>
            @endif
        </div>
    </div>
    @endif

    @if($record->previous_status || $record->new_status)
    <div class="border rounded-lg p-4">
        <h4 class="text-md font-semibold mb-3 text-gray-900 dark:text-gray-100">Status Change</h4>

        <div class="flex items-center justify-center space-x-4">
            @if($record->previous_status)
            <span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-sm">
                {{ $record->previous_status }}
            </span>
            @endif

            @if($record->previous_status && $record->new_status)
            <x-heroicon-o-arrow-right class="w-5 h-5 text-gray-500" />
            @endif

            @if($record->new_status)
            <span class="px-3 py-1 bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200 rounded-full text-sm">
                {{ $record->new_status }}
            </span>
            @endif
        </div>
    </div>
    @endif

    @if($record->metadata)
    <div class="border rounded-lg p-4">
        <h4 class="text-md font-semibold mb-3 text-gray-900 dark:text-gray-100">Additional Details</h4>

        @if(isset($record->metadata['triggering_work_orders']) && count($record->metadata['triggering_work_orders']) > 0)
        <div class="mb-4">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Dependencies satisfied by:</span>
            <div class="mt-2 space-y-2">
                @foreach($record->metadata['triggering_work_orders'] as $trigger)
                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 p-2 rounded">
                    <span class="text-sm font-mono">{{ $trigger['predecessor_unique_id'] ?? 'N/A' }}</span>
                    <div class="text-xs text-gray-500 space-x-2">
                        <span>{{ ucfirst($trigger['dependency_type'] ?? 'unknown') }}</span>
                        @if(isset($trigger['required_quantity']))
                        <span>Qty: {{ $trigger['required_quantity'] }}</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if(isset($record->metadata['dependency_count']))
        <div class="text-sm">
            <span class="font-medium text-gray-500 dark:text-gray-400">Dependencies Count:</span>
            <span class="ml-2">{{ $record->metadata['dependency_count'] }}</span>
        </div>
        @endif
    </div>
    @endif

    @if($record->user)
    <div class="border rounded-lg p-4">
        <h4 class="text-md font-semibold mb-2 text-gray-900 dark:text-gray-100">User</h4>
        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $record->user->name }}</p>
    </div>
    @endif
</div>