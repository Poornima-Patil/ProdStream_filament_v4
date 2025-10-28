{{-- Machine Status - Analytics Mode --}}
{{-- Shows historical machine status distribution (Running/Setup/Hold/Scheduled/Idle) --}}

@php
    $primaryPeriod = $data['primary_period'] ?? null;
    $comparisonPeriod = $data['comparison_period'] ?? null;
    $comparisonAnalysis = $data['comparison_analysis'] ?? null;

    if (!$primaryPeriod) {
        echo '<div class="text-center py-8"><p class="text-gray-500 dark:text-gray-400">No analytics data available</p></div>';
        return;
    }

    $summary = $primaryPeriod['summary'] ?? [];
    $dailyBreakdown = $primaryPeriod['daily_breakdown'] ?? [];

    // Helper function to get color class based on status
    $getStatusColor = function($status) {
        return match($status) {
            'running' => 'text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20',
            'setup' => 'text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-900/20',
            'hold' => 'text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20',
            'scheduled' => 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20',
            'idle' => 'text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/20',
            default => 'text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/20',
        };
    };

    // Helper function for trend indicators
    $getTrendIcon = function($trend) {
        if ($trend === 'up') {
            return '<svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>';
        } else {
            return '<svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>';
        }
    };

    $statusOrder = ['running', 'setup', 'hold', 'scheduled', 'idle'];

    $statusStyles = [
        'running' => [
            'card' => 'bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800',
            'icon' => 'heroicon-o-play-circle',
            'icon_class' => 'text-green-600 dark:text-green-400',
            'wo_class' => 'text-green-700 dark:text-green-300',
        ],
        'hold' => [
            'card' => 'bg-yellow-50 dark:bg-yellow-900/20 border-2 border-yellow-200 dark:border-yellow-800',
            'icon' => 'heroicon-o-pause-circle',
            'icon_class' => 'text-yellow-600 dark:text-yellow-400',
            'wo_class' => 'text-yellow-700 dark:text-yellow-300',
        ],
        'setup' => [
            'card' => 'bg-violet-50 dark:bg-violet-900/20 border-2 border-violet-200 dark:border-violet-800',
            'icon' => 'heroicon-o-wrench-screwdriver',
            'icon_class' => 'text-violet-600 dark:text-violet-400',
            'wo_class' => 'text-violet-700 dark:text-violet-300',
        ],
        'scheduled' => [
            'card' => 'bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-800',
            'icon' => 'heroicon-o-clock',
            'icon_class' => 'text-blue-600 dark:text-blue-400',
            'wo_class' => 'text-blue-700 dark:text-blue-300',
        ],
        'idle' => [
            'card' => 'bg-gray-50 dark:bg-gray-900/20 border-2 border-gray-200 dark:border-gray-700',
            'icon' => 'heroicon-o-minus-circle',
            'icon_class' => 'text-gray-600 dark:text-gray-400',
            'wo_class' => 'text-gray-600 dark:text-gray-300',
        ],
    ];

    $buildMatrixMachines = function ($snapshot) use ($statusOrder) {
        return collect($statusOrder)->flatMap(function ($status) use ($snapshot) {
            return collect($snapshot[$status]['machines'] ?? [])->map(function ($machine) use ($status) {
                $machine['__status'] = $status;

                return $machine;
            });
        });
    };

    $primarySnapshot = $primaryPeriod['status_snapshot'] ?? [];
    $primaryMatrixMachines = $buildMatrixMachines($primarySnapshot);

    $comparisonSnapshot = $comparisonPeriod['status_snapshot'] ?? [];
    $comparisonMatrixMachines = $comparisonPeriod
        ? $buildMatrixMachines($comparisonSnapshot)
        : collect();

    $primarySnapshotDate = $primaryPeriod['snapshot_date'] ?? null;
    $comparisonSnapshotDate = $comparisonPeriod['snapshot_date'] ?? null;

    $primaryDonutData = [
        'labels' => ['Running', 'Setup', 'Hold', 'Scheduled', 'Idle'],
        'values' => [
            round($summary['avg_running_pct'] ?? 0, 1),
            round($summary['avg_setup_pct'] ?? 0, 1),
            round($summary['avg_hold_pct'] ?? 0, 1),
            round($summary['avg_scheduled_pct'] ?? 0, 1),
            round($summary['avg_idle_pct'] ?? 0, 1),
        ],
    ];

    $comparisonDonutData = $comparisonPeriod
        ? [
            'labels' => ['Running', 'Setup', 'Hold', 'Scheduled', 'Idle'],
            'values' => [
                round($comparisonPeriod['summary']['avg_running_pct'] ?? 0, 1),
                round($comparisonPeriod['summary']['avg_setup_pct'] ?? 0, 1),
                round($comparisonPeriod['summary']['avg_hold_pct'] ?? 0, 1),
                round($comparisonPeriod['summary']['avg_scheduled_pct'] ?? 0, 1),
                round($comparisonPeriod['summary']['avg_idle_pct'] ?? 0, 1),
            ],
        ]
        : null;

    $trendLabels = collect($dailyBreakdown)
        ->map(fn ($day) => \Carbon\Carbon::parse($day['date'])->format('M d'))
        ->toArray();

    $buildTrendSeries = function (array $breakdown) {
        $statuses = ['running', 'setup', 'hold', 'scheduled', 'idle'];

        $series = [];

        foreach ($statuses as $status) {
            $series[$status] = collect($breakdown)->map(function ($day) use ($status) {
                $total = $day['total_machines'] ?? 0;
                $value = $day[$status] ?? 0;

                return $total > 0
                    ? round(($value / $total) * 100, 1)
                    : 0;
            })->toArray();
        }

        return $series;
    };

    $primaryTrendSeries = $buildTrendSeries($dailyBreakdown);
    $comparisonTrendSeries = $comparisonPeriod
        ? $buildTrendSeries($comparisonPeriod['daily_breakdown'] ?? [])
        : null;
@endphp

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">KPI Analytics Dashboard - ProdStream</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Historical machine status performance based on the selected time period.
        </p>
    </div>
    <button
        type="button"
        wire:click="toggleAnalyticsSection('overview')"
        aria-expanded="{{ $analyticsOverviewExpanded ? 'true' : 'false' }}"
        aria-controls="kpi-analytics-overview"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
    >
        <span>{{ $analyticsOverviewExpanded ? 'Collapse' : 'Expand' }}</span>
        <x-dynamic-component
            :component="$analyticsOverviewExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
            class="w-5 h-5"
        />
    </button>
</div>

<div id="kpi-analytics-overview">
@if($analyticsOverviewExpanded)

{{-- Period Header --}}
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $primaryPeriod['label'] }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $primaryPeriod['start_date'] }} to {{ $primaryPeriod['end_date'] }}
                ({{ $summary['days_analyzed'] }} days analyzed)
            </p>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    {{-- Running Machines Card --}}
    <x-filament::card>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Avg Running
                </h4>
                <x-heroicon-o-play-circle class="w-5 h-5 text-green-500" />
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                    {{ number_format($summary['avg_running'], 1) }}
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    / {{ $summary['total_machines'] }}
                </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format($summary['avg_running_pct'], 1) }}% of machines
            </p>

            @if($comparisonAnalysis && isset($comparisonAnalysis['running']))
                <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                    {!! $getTrendIcon($comparisonAnalysis['running']['trend']) !!}
                    <span class="{{ $comparisonAnalysis['running']['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $comparisonAnalysis['running']['difference'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['running']['difference'], 1) }}
                        ({{ $comparisonAnalysis['running']['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['running']['percentage_change'], 1) }}%)
                    </span>
                </div>
            @endif
        </div>
    </x-filament::card>

    {{-- Hold Machines Card --}}
    <x-filament::card>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Avg On Hold
                </h4>
                <x-heroicon-o-pause-circle class="w-5 h-5 text-yellow-500" />
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                    {{ number_format($summary['avg_hold'], 1) }}
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    / {{ $summary['total_machines'] }}
                </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format($summary['avg_hold_pct'], 1) }}% of machines
            </p>

            @if($comparisonAnalysis && isset($comparisonAnalysis['hold']))
                <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                    {!! $getTrendIcon($comparisonAnalysis['hold']['trend']) !!}
                    <span class="{{ $comparisonAnalysis['hold']['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $comparisonAnalysis['hold']['difference'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['hold']['difference'], 1) }}
                        ({{ $comparisonAnalysis['hold']['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['hold']['percentage_change'], 1) }}%)
                    </span>
                </div>
            @endif
        </div>
    </x-filament::card>

    {{-- Setup Machines Card --}}
    <x-filament::card>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Avg Setup
                </h4>
                <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-violet-500" />
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-bold text-violet-600 dark:text-violet-400">
                    {{ number_format($summary['avg_setup'] ?? 0, 1) }}
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    / {{ $summary['total_machines'] }}
                </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format($summary['avg_setup_pct'] ?? 0, 1) }}% of machines
            </p>

            @if($comparisonAnalysis && isset($comparisonAnalysis['setup']))
                <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                    {!! $getTrendIcon($comparisonAnalysis['setup']['trend']) !!}
                    <span class="{{ $comparisonAnalysis['setup']['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $comparisonAnalysis['setup']['difference'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['setup']['difference'], 1) }}
                        ({{ $comparisonAnalysis['setup']['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['setup']['percentage_change'], 1) }}%)
                    </span>
                </div>
            @endif
        </div>
    </x-filament::card>

    {{-- Scheduled Machines Card --}}
    <x-filament::card>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Avg Scheduled
                </h4>
                <x-heroicon-o-calendar class="w-5 h-5 text-blue-500" />
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                    {{ number_format($summary['avg_scheduled'], 1) }}
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    / {{ $summary['total_machines'] }}
                </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format($summary['avg_scheduled_pct'], 1) }}% of machines
            </p>

            @if($comparisonAnalysis && isset($comparisonAnalysis['scheduled']))
                <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                    {!! $getTrendIcon($comparisonAnalysis['scheduled']['trend']) !!}
                    <span class="text-gray-600 dark:text-gray-400">
                        {{ $comparisonAnalysis['scheduled']['difference'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['scheduled']['difference'], 1) }}
                        ({{ $comparisonAnalysis['scheduled']['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['scheduled']['percentage_change'], 1) }}%)
                    </span>
                </div>
            @endif
        </div>
    </x-filament::card>

    {{-- Idle Machines Card --}}
    <x-filament::card>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Avg Idle
                </h4>
                <x-heroicon-o-minus-circle class="w-5 h-5 text-gray-500" />
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-bold text-gray-600 dark:text-gray-400">
                    {{ number_format($summary['avg_idle'], 1) }}
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    / {{ $summary['total_machines'] }}
                </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format($summary['avg_idle_pct'], 1) }}% of machines
            </p>

            @if($comparisonAnalysis && isset($comparisonAnalysis['idle']))
                <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                    {!! $getTrendIcon($comparisonAnalysis['idle']['trend']) !!}
                    <span class="{{ $comparisonAnalysis['idle']['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $comparisonAnalysis['idle']['difference'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['idle']['difference'], 1) }}
                        ({{ $comparisonAnalysis['idle']['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis['idle']['percentage_change'], 1) }}%)
                    </span>
                </div>
            @endif
        </div>
    </x-filament::card>
</div>

{{-- Distribution Visualizations --}}
<div class="space-y-6 mb-6">
    {{-- Donut Charts --}}
    <div class="grid grid-cols-1 gap-6 {{ $comparisonPeriod ? 'md:grid-cols-2' : '' }}">
        <x-filament::card>
            <button
                type="button"
                wire:click="toggleAnalyticsSection('donut')"
                aria-expanded="{{ $analyticsDonutExpanded ? 'true' : 'false' }}"
                aria-controls="analytics-donut-primary"
                class="w-full flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700 text-left hover:text-gray-900 dark:hover:text-white transition-colors"
            >
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Machine Status Breakdown – {{ $primaryPeriod['label'] }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Average share of machines by status ({{ $primaryPeriod['start_date'] }} → {{ $primaryPeriod['end_date'] }})
                    </p>
                </div>
                <x-dynamic-component
                    :component="$analyticsDonutExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                    class="w-5 h-5 text-gray-500 dark:text-gray-400"
                />
            </button>

            @if($analyticsDonutExpanded)
                <div
                    id="analytics-donut-primary"
                    class="mt-6 h-80"
                    wire:key="analytics-donut-primary-{{ md5(json_encode($primaryDonutData) . $primaryPeriod['label']) }}"
                    x-data="machineStatusAnalyticsDonut({
                        chartId: 'primary',
                        labels: @js($primaryDonutData['labels']),
                        data: @js($primaryDonutData['values']),
                    })"
                >
                    <canvas x-ref="canvas" class="w-full h-full" wire:ignore></canvas>
                </div>
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    Based on {{ $summary['days_analyzed'] }} day average ({{ $summary['total_machines'] }} machines analysed).
                </p>
            @endif
        </x-filament::card>

        @if($comparisonPeriod)
            <x-filament::card>
                <button
                    type="button"
                    wire:click="toggleAnalyticsSection('donutComparison')"
                    aria-expanded="{{ $analyticsDonutComparisonExpanded ? 'true' : 'false' }}"
                    aria-controls="analytics-donut-comparison"
                    class="w-full flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700 text-left hover:text-gray-900 dark:hover:text-white transition-colors"
                >
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Machine Status Breakdown – {{ $comparisonPeriod['label'] }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Average share of machines by status ({{ $comparisonPeriod['start_date'] }} → {{ $comparisonPeriod['end_date'] }})
                        </p>
                    </div>
                    <x-dynamic-component
                        :component="$analyticsDonutComparisonExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                        class="w-5 h-5 text-gray-500 dark:text-gray-400"
                    />
                </button>

                @if($analyticsDonutComparisonExpanded)
                    <div
                        id="analytics-donut-comparison"
                        class="mt-6 h-80"
                        wire:key="analytics-donut-comparison-{{ md5(json_encode($comparisonDonutData) . $comparisonPeriod['label']) }}"
                        x-data="machineStatusAnalyticsDonut({
                            chartId: 'comparison',
                            labels: @js($comparisonDonutData['labels']),
                            data: @js($comparisonDonutData['values']),
                        })"
                    >
                        <canvas x-ref="canvas" class="w-full h-full" wire:ignore></canvas>
                    </div>
                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Comparison window normalised across {{ $summary['days_analyzed'] }} days for side-by-side review.
                    </p>
                @endif
            </x-filament::card>
        @endif
    </div>

    {{-- Machine Matrices --}}
    <div class="space-y-6">
        <x-filament::card>
            <button
                type="button"
                wire:click="toggleAnalyticsSection('matrix')"
                aria-expanded="{{ $analyticsMatrixExpanded ? 'true' : 'false' }}"
                aria-controls="analytics-matrix-primary"
                class="w-full flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700 text-left hover:text-gray-900 dark:hover:text-white transition-colors"
            >
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Machine Status Matrix – {{ $primaryPeriod['label'] }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Snapshot captured on {{ $primarySnapshotDate ? \Carbon\Carbon::parse($primarySnapshotDate)->format('M d, Y') : 'N/A' }} using selected analytics filters.
                    </p>
                </div>
                <span class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ $primaryPeriod['snapshot_total_machines'] ?? $summary['total_machines'] ?? 0 }} Machines
                    <x-dynamic-component
                        :component="$analyticsMatrixExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                        class="w-5 h-5 text-gray-500 dark:text-gray-400"
                    />
                </span>
            </button>

            @if($analyticsMatrixExpanded)
                <div id="analytics-matrix-primary" class="mt-6">
                    @if($primaryMatrixMachines->isEmpty())
                        <div class="py-8 text-center border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No machine snapshot data available for this period.
                            </p>
                        </div>
                    @else
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach($primaryMatrixMachines as $machine)
                                @php
                                    $statusKey = $machine['__status'] ?? 'idle';
                                    $styles = $statusStyles[$statusKey] ?? $statusStyles['idle'];
                                    $woNumber = $machine['wo_number'] ?? null;
                                    $woDisplay = $woNumber ? \Illuminate\Support\Str::limit($woNumber, 14) : null;
                                @endphp
                                <a
                                    href="{{ \App\Filament\Admin\Resources\MachineResource::getUrl('view', ['record' => $machine['id']]) }}"
                                    wire:navigate
                                    wire:key="analytics-matrix-primary-{{ $statusKey }}-{{ $machine['id'] }}"
                                    class="{{ $styles['card'] }} rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer"
                                >
                                    <div class="flex flex-col items-center text-center space-y-2">
                                        <x-dynamic-component :component="$styles['icon']" class="w-8 h-8 {{ $styles['icon_class'] }}" />
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $machine['name'] ?? 'Unnamed Machine' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $machine['asset_id'] ?? 'N/A' }}
                                        </div>
                                        @if($woNumber)
                                            <div class="text-xs font-mono {{ $styles['wo_class'] }}" title="{{ $woNumber }}">
                                                {{ $woDisplay }}
                                            </div>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </x-filament::card>

        @if($comparisonPeriod)
            <x-filament::card>
                <button
                    type="button"
                    wire:click="toggleAnalyticsSection('matrixComparison')"
                    aria-expanded="{{ $analyticsMatrixComparisonExpanded ? 'true' : 'false' }}"
                    aria-controls="analytics-matrix-comparison"
                    class="w-full flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700 text-left hover:text-gray-900 dark:hover:text-white transition-colors"
                >
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Machine Status Matrix – {{ $comparisonPeriod['label'] }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Snapshot captured on {{ $comparisonSnapshotDate ? \Carbon\Carbon::parse($comparisonSnapshotDate)->format('M d, Y') : 'N/A' }} for the comparison window.
                        </p>
                    </div>
                    <span class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ $comparisonPeriod['snapshot_total_machines'] ?? ($comparisonPeriod['summary']['total_machines'] ?? 0) }} Machines
                        <x-dynamic-component
                            :component="$analyticsMatrixComparisonExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                            class="w-5 h-5 text-gray-500 dark:text-gray-400"
                        />
                    </span>
                </button>

                @if($analyticsMatrixComparisonExpanded)
                    <div id="analytics-matrix-comparison" class="mt-6">
                        @if($comparisonMatrixMachines->isEmpty())
                            <div class="py-8 text-center border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    No machine snapshot data available for the comparison period.
                                </p>
                            </div>
                        @else
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                @foreach($comparisonMatrixMachines as $machine)
                                    @php
                                        $statusKey = $machine['__status'] ?? 'idle';
                                        $styles = $statusStyles[$statusKey] ?? $statusStyles['idle'];
                                        $woNumber = $machine['wo_number'] ?? null;
                                        $woDisplay = $woNumber ? \Illuminate\Support\Str::limit($woNumber, 14) : null;
                                    @endphp
                                    <a
                                        href="{{ \App\Filament\Admin\Resources\MachineResource::getUrl('view', ['record' => $machine['id']]) }}"
                                        wire:navigate
                                        wire:key="analytics-matrix-comparison-{{ $statusKey }}-{{ $machine['id'] }}"
                                        class="{{ $styles['card'] }} rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer"
                                    >
                                        <div class="flex flex-col items-center text-center space-y-2">
                                            <x-dynamic-component :component="$styles['icon']" class="w-8 h-8 {{ $styles['icon_class'] }}" />
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $machine['name'] ?? 'Unnamed Machine' }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $machine['asset_id'] ?? 'N/A' }}
                                            </div>
                                            @if($woNumber)
                                                <div class="text-xs font-mono {{ $styles['wo_class'] }}" title="{{ $woNumber }}">
                                                    {{ $woDisplay }}
                                                </div>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </x-filament::card>
        @endif
    </div>

    {{-- Trend Line --}}
    <x-filament::card>
        <button
            type="button"
            wire:click="toggleAnalyticsSection('trend')"
            aria-expanded="{{ $analyticsTrendExpanded ? 'true' : 'false' }}"
            aria-controls="analytics-trend-panel"
            class="w-full flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700 text-left hover:text-gray-900 dark:hover:text-white transition-colors"
        >
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Status Trend Over Time
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Daily percentage of machines in each status across the selected period{{ $comparisonPeriod ? ' with comparison overlay' : '' }}.
                </p>
            </div>
            <x-dynamic-component
                :component="$analyticsTrendExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                class="w-5 h-5 text-gray-500 dark:text-gray-400"
            />
        </button>

        @if($analyticsTrendExpanded)
            <div
                id="analytics-trend-panel"
                class="mt-6 h-96"
                wire:key="analytics-trend-{{ md5(json_encode([$trendLabels, $primaryTrendSeries, $comparisonTrendSeries])) }}"
                x-data="machineStatusAnalyticsTrend({
                    labels: @js($trendLabels),
                    primarySeries: @js($primaryTrendSeries),
                    comparisonSeries: @js($comparisonTrendSeries),
                    comparisonEnabled: {{ $comparisonPeriod ? 'true' : 'false' }},
                })"
            >
                <canvas x-ref="canvas" class="w-full h-full" wire:ignore></canvas>
            </div>
        @endif
    </x-filament::card>
</div>

{{-- Daily Breakdown Table --}}
<x-filament::card class="mb-6">
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Daily Status Distribution
        </h3>

        @php
            $paginatedBreakdown = $this->getPaginatedDailyBreakdown($dailyBreakdown);
        @endphp

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Running
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Setup
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Hold
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Scheduled
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Idle
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Visual Distribution
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($paginatedBreakdown['data'] as $day)
                        @php
                            $total = $day['total_machines'];
            $running = $day['running'] ?? 0;
            $setup = $day['setup'] ?? 0;
            $hold = $day['hold'] ?? 0;
            $scheduled = $day['scheduled'] ?? 0;
            $idle = $day['idle'] ?? 0;
            $runningPct = $total > 0 ? ($running / $total) * 100 : 0;
            $setupPct = $total > 0 ? ($setup / $total) * 100 : 0;
            $holdPct = $total > 0 ? ($hold / $total) * 100 : 0;
            $scheduledPct = $total > 0 ? ($scheduled / $total) * 100 : 0;
            $idlePct = $total > 0 ? ($idle / $total) * 100 : 0;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            {{-- Date --}}
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($day['date'])->format('M d, Y') }}
                            </td>

                            {{-- Running --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getStatusColor('running') }}">
                                    {{ $running }}
                                </span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($runningPct, 0) }}%
                                </div>
                            </td>

                            {{-- Setup --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getStatusColor('setup') }}">
                                    {{ $setup }}
                                </span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($setupPct, 0) }}%
                                </div>
                            </td>

                            {{-- Hold --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getStatusColor('hold') }}">
                                    {{ $hold }}
                                </span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($holdPct, 0) }}%
                                </div>
                            </td>

                            {{-- Scheduled --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getStatusColor('scheduled') }}">
                                    {{ $scheduled }}
                                </span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($scheduledPct, 0) }}%
                                </div>
                            </td>

                            {{-- Idle --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getStatusColor('idle') }}">
                                    {{ $idle }}
                                </span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($idlePct, 0) }}%
                                </div>
                            </td>

                            {{-- Visual Distribution --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex w-full h-6 rounded-full overflow-hidden border border-gray-300 dark:border-gray-600">
                                    @if($runningPct > 0)
                                        <div class="bg-green-500 border-r border-white dark:border-gray-700" style="width: {{ $runningPct }}%" title="Running: {{ number_format($runningPct, 1) }}%"></div>
                                    @endif
                                    @if($setupPct > 0)
                                        <div class="bg-violet-500 border-r border-white dark:border-gray-700" style="width: {{ $setupPct }}%" title="Setup: {{ number_format($setupPct, 1) }}%"></div>
                                    @endif
                                    @if($holdPct > 0)
                                        <div class="bg-yellow-500 border-r border-white dark:border-gray-700" style="width: {{ $holdPct }}%" title="Hold: {{ number_format($holdPct, 1) }}%"></div>
                                    @endif
                                    @if($scheduledPct > 0)
                                        <div class="bg-blue-500 border-r border-white dark:border-gray-700" style="width: {{ $scheduledPct }}%" title="Scheduled: {{ number_format($scheduledPct, 1) }}%"></div>
                                    @endif
                                    @if($idlePct > 0)
                                        <div class="bg-gray-400" style="width: {{ $idlePct }}%" title="Idle: {{ number_format($idlePct, 1) }}%"></div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No data available for the selected period
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Controls --}}
        @if($paginatedBreakdown['total_pages'] > 1)
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                <div class="flex-1 flex justify-between sm:hidden">
                    @if($paginatedBreakdown['current_page'] > 1)
                        <button
                            wire:click="gotoDailyBreakdownPage({{ $paginatedBreakdown['current_page'] - 1 }})"
                            class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                            Previous
                        </button>
                    @endif
                    @if($paginatedBreakdown['current_page'] < $paginatedBreakdown['total_pages'])
                        <button
                            wire:click="gotoDailyBreakdownPage({{ $paginatedBreakdown['current_page'] + 1 }})"
                            class="ml-3 relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                            Next
                        </button>
                    @endif
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Showing
                            <span class="font-medium">{{ $paginatedBreakdown['from'] }}</span>
                            to
                            <span class="font-medium">{{ $paginatedBreakdown['to'] }}</span>
                            of
                            <span class="font-medium">{{ $paginatedBreakdown['total'] }}</span>
                            days
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            {{-- Previous Button --}}
                            @if($paginatedBreakdown['current_page'] > 1)
                                <button
                                    wire:click="gotoDailyBreakdownPage({{ $paginatedBreakdown['current_page'] - 1 }})"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <x-heroicon-o-chevron-left class="h-5 w-5" />
                                </button>
                            @else
                                <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 text-sm font-medium text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                    <x-heroicon-o-chevron-left class="h-5 w-5" />
                                </span>
                            @endif

                            {{-- Page Numbers --}}
                            @php
                                $start = max(1, $paginatedBreakdown['current_page'] - 2);
                                $end = min($paginatedBreakdown['total_pages'], $paginatedBreakdown['current_page'] + 2);
                            @endphp

                            @if($start > 1)
                                <button
                                    wire:click="gotoDailyBreakdownPage(1)"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    1
                                </button>
                                @if($start > 2)
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        ...
                                    </span>
                                @endif
                            @endif

                            @for($page = $start; $page <= $end; $page++)
                                @if($page === $paginatedBreakdown['current_page'])
                                    <span class="relative inline-flex items-center px-4 py-2 border border-primary-500 bg-primary-50 dark:bg-primary-900/50 text-sm font-medium text-primary-600 dark:text-primary-400 z-10">
                                        {{ $page }}
                                    </span>
                                @else
                                    <button
                                        wire:click="gotoDailyBreakdownPage({{ $page }})"
                                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        {{ $page }}
                                    </button>
                                @endif
                            @endfor

                            @if($end < $paginatedBreakdown['total_pages'])
                                @if($end < $paginatedBreakdown['total_pages'] - 1)
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        ...
                                    </span>
                                @endif
                                <button
                                    wire:click="gotoDailyBreakdownPage({{ $paginatedBreakdown['total_pages'] }})"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    {{ $paginatedBreakdown['total_pages'] }}
                                </button>
                            @endif

                            {{-- Next Button --}}
                            @if($paginatedBreakdown['current_page'] < $paginatedBreakdown['total_pages'])
                                <button
                                    wire:click="gotoDailyBreakdownPage({{ $paginatedBreakdown['current_page'] + 1 }})"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <x-heroicon-o-chevron-right class="h-5 w-5" />
                                </button>
                            @else
                                <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 text-sm font-medium text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                    <x-heroicon-o-chevron-right class="h-5 w-5" />
                                </span>
                            @endif
                        </nav>
                    </div>
                </div>
            </div>
        @endif

        {{-- Legend --}}
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Understanding Machine Status:</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-400">
                <div class="flex items-start gap-2">
                    <div class="w-4 h-4 bg-green-500 rounded mt-0.5"></div>
                    <div>
                        <span class="font-semibold">Running:</span> Machines actively running work orders (status = 'Start')
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <div class="w-4 h-4 bg-violet-500 rounded mt-0.5"></div>
                    <div>
                        <span class="font-semibold">Setup:</span> Machines preparing for production (status = 'Setup')
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <div class="w-4 h-4 bg-yellow-500 rounded mt-0.5"></div>
                    <div>
                        <span class="font-semibold">Hold:</span> Machines with work orders on hold (status = 'Hold')
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <div class="w-4 h-4 bg-blue-500 rounded mt-0.5"></div>
                    <div>
                        <span class="font-semibold">Scheduled:</span> Machines with assigned work orders (status = 'Assigned')
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <div class="w-4 h-4 bg-gray-400 rounded mt-0.5"></div>
                    <div>
                        <span class="font-semibold">Idle:</span> Machines with no work orders scheduled
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament::card>

{{-- Comparison Period (if enabled) --}}
@if($comparisonPeriod)
    <x-filament::card>
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Comparison: {{ $comparisonPeriod['label'] }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $comparisonPeriod['start_date'] }} to {{ $comparisonPeriod['end_date'] }}
            </p>

            @php
                $compSummary = $comparisonPeriod['summary'] ?? [];
            @endphp

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Running</div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ number_format($compSummary['avg_running'] ?? 0, 1) }}
                    </div>
                </div>
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Setup</div>
                    <div class="text-2xl font-bold text-violet-600 dark:text-violet-400">
                        {{ number_format($compSummary['avg_setup'] ?? 0, 1) }}
                    </div>
                </div>
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Hold</div>
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                        {{ number_format($compSummary['avg_hold'] ?? 0, 1) }}
                    </div>
                </div>
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Scheduled</div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ number_format($compSummary['avg_scheduled'] ?? 0, 1) }}
                    </div>
                </div>
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Idle</div>
                    <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">
                        {{ number_format($compSummary['avg_idle'] ?? 0, 1) }}
                    </div>
                </div>
            </div>
        </div>
    </x-filament::card>
@endif
@endif
</div>
