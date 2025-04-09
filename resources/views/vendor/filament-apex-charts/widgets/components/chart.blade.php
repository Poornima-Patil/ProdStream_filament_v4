@props([
    'chartId',
    'chartOptions',
    'contentHeight',
    'pollingInterval',
    'loadingIndicator',
    'deferLoading',
    'readyToLoad',
    'darkMode',
    'extraJsOptions',
])

<div {!! $deferLoading ? ' wire:init="loadWidget" ' : '' !!} class="flex items-center justify-center filament-apex-charts-chart"
    style="{{ $contentHeight ? 'height: ' . $contentHeight . 'px;' : '' }}">
    @if ($readyToLoad)
        <div wire:ignore class="w-full filament-apex-charts-chart-container">
            <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"></script>
            <div class="filament-apex-charts-chart-object" x-ref="{{ $chartId }}" id="{{ $chartId }}">
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var options = @json($chartOptions);
                    var chart = new ApexCharts(document.querySelector('#{{ $chartId }}'), options);
                    chart.render();
                });
            </script>

            <div {!! $pollingInterval ? 'wire:poll.' . $pollingInterval . '="updateOptions"' : '' !!} x-data="{}" x-init="$watch('dropdownOpen', value => $wire.dropdownOpen = value)">
            </div>
        </div>
    @else
        <div class="filament-apex-charts-chart-loading-indicator m-auto">
            @if ($loadingIndicator)
                {!! $loadingIndicator !!}
            @else
                <x-filament::loading-indicator class="h-7 w-7 text-gray-500 dark:text-gray-400" wire:loading.delay />
            @endif
        </div>
    @endif
</div>
