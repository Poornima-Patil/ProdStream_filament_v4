<x-filament-widgets::widget>
    <x-filament::section>
        @if($operator)
            <x-slot name="heading">
                👤 Operator Schedule: {{ $operator->user?->getFilamentName() ?? 'Unknown' }}
                @if($operator->shift)
                    <span class="text-sm font-normal text-gray-600 dark:text-gray-400">
                        ({{ $operator->shift->name }}: {{ $operator->shift->start_time }} - {{ $operator->shift->end_time }})
                    </span>
                @endif
            </x-slot>

            <div class="space-y-4">
                <!-- Operator Selector -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="min-w-0">
                            <label for="operator-select" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Select Operator:
                            </label>
                            <select 
                                id="operator-select"
                                wire:change="selectOperator($event.target.value)"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Choose an operator...</option>
                                @foreach($operators as $operatorId => $operatorName)
                                    <option value="{{ $operatorId }}" {{ $operator && $operator->id == $operatorId ? 'selected' : '' }}>
                                        {{ $operatorName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- View Type Selector -->
                    <div class="flex items-center space-x-2 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                        <button wire:click="changeView('day')" 
                            class="px-3 py-1 text-sm font-medium rounded-md transition-colors {{ $viewType === 'day' ? 'bg-white dark:bg-gray-600 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                            Day
                        </button>
                        <button wire:click="changeView('week')" 
                            class="px-3 py-1 text-sm font-medium rounded-md transition-colors {{ $viewType === 'week' ? 'bg-white dark:bg-gray-600 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                            Week
                        </button>
                        <button wire:click="changeView('month')" 
                            class="px-3 py-1 text-sm font-medium rounded-md transition-colors {{ $viewType === 'month' ? 'bg-white dark:bg-gray-600 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                            Month
                        </button>
                    </div>
                </div>

                <!-- Calendar Component -->
                @livewire('calendar.operators.operator-schedule-calendar', ['operator' => $operator, 'viewType' => $viewType], key($operator->id . '-' . $viewType))
            </div>
        @else
            <x-slot name="heading">
                👤 Operator Schedule
            </x-slot>

            <div class="text-center py-8">
                @if($operators->isEmpty())
                    <div class="text-gray-500 dark:text-gray-400">
                        <svg class="w-6 h-6 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-1">No Operators Available</h4>
                        <p class="text-gray-600 dark:text-gray-400">There are no operators configured for this factory.</p>
                    </div>
                @else
                    <div class="text-gray-500 dark:text-gray-400">
                        <svg class="w-6 h-6 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-1">Select an Operator</h4>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">Choose an operator from the dropdown above to view their schedule.</p>
                        
                        <!-- Quick operator selector -->
                        <div class="max-w-xs mx-auto">
                            <select 
                                wire:change="selectOperator($event.target.value)"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Choose an operator...</option>
                                @foreach($operators as $operatorId => $operatorName)
                                    <option value="{{ $operatorId }}">{{ $operatorName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>