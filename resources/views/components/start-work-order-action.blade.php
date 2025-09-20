<div class="space-y-3">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center text-blue-800 font-medium mb-2">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
            </svg>
            Batch Keys Required
        </div>
        <p class="text-blue-700 text-sm mb-3">
            This work order requires keys from predecessor work orders to start.
            Click the button below to select keys and start the work order.
        </p>
        <div>
            @livewire('batch-start-modal', ['workOrder' => $workOrder])
        </div>
    </div>
</div>