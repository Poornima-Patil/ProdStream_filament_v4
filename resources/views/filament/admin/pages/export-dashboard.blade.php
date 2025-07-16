<x-filament::page>
    <form wire:submit.prevent="downloadCsv" class="space-y-4">
        {{-- Renders the form --}}
        {{ $this->form }}

        {{-- Submit Button --}}
        <x-filament::button type="submit">
            Download Excel
        </x-filament::button>
    </form>
</x-filament::page>
