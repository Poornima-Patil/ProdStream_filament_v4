<div>
    <!-- Trigger Button -->
    <button
        type="button"
        wire:click="openModal"
        @if(!$workOrder->usesBatchSystem() || !$workOrder->canStartNewBatch()) disabled @endif
        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white text-sm font-medium rounded-md transition-colors duration-200"
    >
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
        </svg>
        Start New Batch
    </button>

    <!-- Modal -->
    @if($showModal)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" wire:click="closeModal">
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6" wire:click.stop>

                    <!-- Header -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Start New Batch - {{ $workOrder->unique_id }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Work Order Group: {{ $workOrder->workOrderGroup?->name }}
                        </p>
                    </div>

                    <!-- Planned Quantity -->
                    <div class="mb-6">
                        <label for="plannedQuantity" class="block text-sm font-medium text-gray-700 mb-2">
                            Planned Quantity for this Batch
                        </label>
                        <input
                            type="number"
                            id="plannedQuantity"
                            wire:model="plannedQuantity"
                            min="1"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        >
                        @error('plannedQuantity')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Key Requirements Section -->
                    @if(!empty($requiredKeys))
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-900 mb-3">Required Keys</h4>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                            <p class="text-sm text-yellow-800">
                                This work order requires keys from predecessor work orders to start:
                            </p>
                            <ul class="mt-2 text-sm text-yellow-700">
                                @foreach($requiredKeys as $requirement)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ $requirement['quantity_needed'] }} key(s) from {{ $requirement['work_order_name'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <!-- Available Keys Selection -->
                        <h5 class="text-sm font-medium text-gray-700 mb-3">Select Keys to Consume:</h5>

                        @if($availableKeys->count() > 0)
                            <div class="space-y-4 max-h-60 overflow-y-auto border border-gray-200 rounded-md p-3">
                                @foreach($availableKeys as $workOrderId => $keys)
                                    @php
                                        $workOrderName = $keys->first()->workOrder->unique_id;
                                        $requiredFromThis = collect($requiredKeys)->firstWhere('work_order_id', $workOrderId);
                                    @endphp

                                    @if($requiredFromThis)
                                    <div class="border-b border-gray-100 pb-3 last:border-b-0">
                                        <h6 class="text-sm font-medium text-gray-800 mb-2">
                                            {{ $workOrderName }}
                                            <span class="text-xs text-gray-500">(Need {{ $requiredFromThis['quantity_needed'] }})</span>
                                        </h6>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @foreach($keys as $key)
                                                <label class="flex items-center p-2 border rounded-md cursor-pointer hover:bg-gray-50 {{ in_array($key->id, $selectedKeys) ? 'bg-blue-50 border-blue-300' : 'border-gray-200' }}">
                                                    <input
                                                        type="checkbox"
                                                        wire:click="toggleKey({{ $key->id }})"
                                                        {{ in_array($key->id, $selectedKeys) ? 'checked' : '' }}
                                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                    >
                                                    <div class="ml-3 flex-1">
                                                        <p class="text-xs font-mono text-gray-900">{{ $key->key_code }}</p>
                                                        <p class="text-xs text-gray-500">{{ $key->quantity_produced }} qty • {{ $key->generated_at->format('M j, H:i') }}</p>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="bg-red-50 border border-red-200 rounded-md p-3">
                                <p class="text-sm text-red-800">
                                    No keys available from required predecessor work orders.
                                    Complete predecessor work orders first to generate keys.
                                </p>
                            </div>
                        @endif

                        @error('selectedKeys')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    <!-- No Keys Required -->
                    @if($canStartWithoutKeys)
                    <div class="mb-6">
                        <div class="bg-green-50 border border-green-200 rounded-md p-3">
                            <p class="text-sm text-green-800">
                                @if($workOrder->is_dependency_root)
                                    This is a root work order and can start without keys.
                                @else
                                    No dependencies required for this work order.
                                @endif
                            </p>
                        </div>
                    </div>
                    @endif

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            wire:click="startBatch"
                            @if(!$canStartWithoutKeys && $availableKeys->count() === 0) disabled @endif
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M19 10a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Start Batch
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Wire Loading Indicator -->
    <div wire:loading wire:target="startBatch" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-900">Starting batch...</span>
        </div>
    </div>
</div>