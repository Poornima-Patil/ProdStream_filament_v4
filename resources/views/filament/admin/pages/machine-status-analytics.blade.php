{{-- Machine Status - Analytics Mode --}}
{{-- Shows historical machine status distribution (Running/Hold/Scheduled/Idle) --}}

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
@endphp

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
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
                            $runningPct = $total > 0 ? ($day['running'] / $total) * 100 : 0;
                            $holdPct = $total > 0 ? ($day['hold'] / $total) * 100 : 0;
                            $scheduledPct = $total > 0 ? ($day['scheduled'] / $total) * 100 : 0;
                            $idlePct = $total > 0 ? ($day['idle'] / $total) * 100 : 0;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            {{-- Date --}}
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($day['date'])->format('M d, Y') }}
                            </td>

                            {{-- Running --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getStatusColor('running') }}">
                                    {{ $day['running'] }}
                                </span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($runningPct, 0) }}%
                                </div>
                            </td>

                            {{-- Hold --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getStatusColor('hold') }}">
                                    {{ $day['hold'] }}
                                </span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($holdPct, 0) }}%
                                </div>
                            </td>

                            {{-- Scheduled --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getStatusColor('scheduled') }}">
                                    {{ $day['scheduled'] }}
                                </span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ number_format($scheduledPct, 0) }}%
                                </div>
                            </td>

                            {{-- Idle --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getStatusColor('idle') }}">
                                    {{ $day['idle'] }}
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
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
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

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Running</div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ number_format($compSummary['avg_running'] ?? 0, 1) }}
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
