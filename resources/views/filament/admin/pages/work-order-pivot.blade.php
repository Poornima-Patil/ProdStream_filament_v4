<x-filament::page>
    <form wire:submit.prevent="applyFilters">
        <p class="text-lg mb-4 font-bold"> Select the date for Pivot </p>
        <div class="mb-4">
            <input type="date" wire:model="startDate" class="form-input" />
            <input type="date" wire:model="endDate" class="form-input" />
        </div>
        <x-filament::button type="submit">Generate Pivot</x-filament::button>
    </form>
    {{-- Show raw JSON (debug) --}}
   {{-- <div class="mt-4">
        <h3 class="font-bold mb-2">Work Orders Preview ({{ $pivotData->count() }} records)</h3>
        <pre class="text-xs bg-gray-100 p-4 rounded overflow-x-auto">{{ json_encode($pivotData, JSON_PRETTY_PRINT) }}</pre>
    </div> --}}
    <pre id="pivot-data-json" class="hidden">{{ json_encode($pivotData->values()->all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}</pre>
    <div class="mt-6 border rounded p-4 bg-white" wire:ignore>
        <div id="pivot-table"></div>
    </div>
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- jQuery UI -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <!-- PivotTable UI -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.css" />

    <script>
        $(function() {
            const clearPivotLocalStorage = () => {
                Object.keys(localStorage)
                    .filter(key => key.startsWith('pivotUI'))
                    .forEach(key => localStorage.removeItem(key));
            };

            const renderPivot = () => {
                const data = JSON.parse(document.getElementById('pivot-data-json').textContent);
                clearPivotLocalStorage();
                $('#pivot-table').empty();
                $('#pivot-table').pivotUI(data, {
                    rows: ['Work Order No'],
                    cols: ['Machine'],
                    aggregatorName: 'Count',
                    renderers: $.pivotUtilities.renderers,
                });
            };

            renderPivot();

            document.addEventListener('livewire:load', () => {
                Livewire.hook('message.processed', (message, component) => {
                    setTimeout(renderPivot, 100);
                });
            });
        });
    </script>
</x-filament::page>
