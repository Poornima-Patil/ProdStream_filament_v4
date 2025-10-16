<x-filament-widgets::widget>
    <x-filament::card>
        {{-- Header with mode toggle --}}
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold dark:text-white">{{ $this->getTitle() }}</h2>

            <x-filament::tabs>
                <x-filament::tabs.item
                    wire:click="setMode('dashboard')"
                    :active="$mode === 'dashboard'"
                    icon="heroicon-o-chart-bar">
                    Dashboard
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    wire:click="setMode('analytics')"
                    :active="$mode === 'analytics'"
                    icon="heroicon-o-chart-pie">
                    Analytics
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        {{-- Dashboard Mode Content --}}
        @if($mode === 'dashboard')
            <div wire:poll.60s>
                @isset($dashboardContent)
                    {{ $dashboardContent }}
                @else
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <p>Dashboard content not implemented</p>
                    </div>
                @endisset
            </div>
        @endif

        {{-- Analytics Mode Content --}}
        @if($mode === 'analytics')
            {{-- Analytics Filters --}}
            <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <form wire:submit.prevent="$refresh">
                    {{ $this->form }}

                    <div class="mt-4">
                        <x-filament::button type="submit" wire:loading.attr="disabled">
                            <svg wire:loading wire:target="$refresh" class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Apply Filters
                        </x-filament::button>
                    </div>
                </form>
            </div>

            {{-- Analytics Content --}}
            <div wire:loading.remove wire:target="$refresh">
                @isset($analyticsContent)
                    {{ $analyticsContent }}
                @else
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <p>Analytics content not implemented</p>
                    </div>
                @endisset
            </div>

            {{-- Loading State --}}
            <div wire:loading wire:target="$refresh" class="flex justify-center items-center py-12">
                <svg class="animate-spin h-12 w-12 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="ml-3 text-gray-500 dark:text-gray-400">Loading analytics...</span>
            </div>
        @endif
    </x-filament::card>
</x-filament-widgets::widget>
