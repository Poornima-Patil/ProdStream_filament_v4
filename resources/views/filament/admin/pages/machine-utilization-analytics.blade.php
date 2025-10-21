{{-- Machine Utilization Rate - Analytics Mode --}}
{{-- Shows historical machine utilization metrics from kpi_machine_daily table --}}

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
            </p>
        </div>
        <div class="text-right">
            <div class="text-sm text-gray-600 dark:text-gray-400">Machines Analyzed</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ number_format($summary['machines_analyzed'] ?? 0) }}
            </div>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    {{-- Scheduled Utilization Card --}}
    <x-filament::card>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Scheduled Utilization
                </h4>
                <x-heroicon-o-clock class="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                    {{ number_format($summary['avg_scheduled_utilization'] ?? 0, 1) }}%
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Average across {{ $summary['days_analyzed'] ?? 0 }} days
            </p>

            {{-- Comparison Indicator --}}
            @if($comparisonAnalysis && isset($comparisonAnalysis['scheduled_utilization']))
                @php $comp = $comparisonAnalysis['scheduled_utilization']; @endphp
                <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                    {!! $getTrendIcon($comp['trend']) !!}
                    <span class="{{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : ($comp['status'] === 'declined' ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400') }}">
                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 2) }}%
                        ({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%)
                    </span>
                </div>
            @endif
        </div>
    </x-filament::card>

    {{-- Active Utilization Card --}}
    <x-filament::card>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Active Utilization
                </h4>
                <x-heroicon-o-bolt class="w-5 h-5 text-green-600 dark:text-green-400" />
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                    {{ number_format($summary['avg_active_utilization'] ?? 0, 1) }}%
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                When machines are running
            </p>

            {{-- Comparison Indicator --}}
            @if($comparisonAnalysis && isset($comparisonAnalysis['active_utilization']))
                @php $comp = $comparisonAnalysis['active_utilization']; @endphp
                <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                    {!! $getTrendIcon($comp['trend']) !!}
                    <span class="{{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : ($comp['status'] === 'declined' ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400') }}">
                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 2) }}%
                        ({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%)
                    </span>
                </div>
            @endif
        </div>
    </x-filament::card>

    {{-- Uptime Hours Card --}}
    <x-filament::card>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Total Uptime
                </h4>
                <x-heroicon-o-arrow-trending-up class="w-5 h-5 text-purple-600 dark:text-purple-400" />
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-bold text-purple-600 dark:text-purple-400">
                    {{ number_format($summary['total_uptime_hours'] ?? 0, 1) }}
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-400">hrs</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Productive machine hours
            </p>

            {{-- Comparison Indicator --}}
            @if($comparisonAnalysis && isset($comparisonAnalysis['uptime_hours']))
                @php $comp = $comparisonAnalysis['uptime_hours']; @endphp
                <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                    {!! $getTrendIcon($comp['trend']) !!}
                    <span class="{{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : ($comp['status'] === 'declined' ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400') }}">
                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 1) }} hrs
                        ({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%)
                    </span>
                </div>
            @endif
        </div>
    </x-filament::card>

    {{-- Downtime Hours Card --}}
    <x-filament::card>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Total Downtime
                </h4>
                <x-heroicon-o-arrow-trending-down class="w-5 h-5 text-red-600 dark:text-red-400" />
            </div>
            <div class="flex items-baseline gap-2">
                <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                    {{ number_format($summary['total_downtime_hours'] ?? 0, 1) }}
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-400">hrs</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Non-productive machine hours
            </p>

            {{-- Comparison Indicator --}}
            @if($comparisonAnalysis && isset($comparisonAnalysis['downtime_hours']))
                @php $comp = $comparisonAnalysis['downtime_hours']; @endphp
                <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                    {!! $getTrendIcon($comp['trend']) !!}
                    <span class="{{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : ($comp['status'] === 'declined' ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400') }}">
                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 1) }} hrs
                        ({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%)
                    </span>
                </div>
            @endif
        </div>
    </x-filament::card>
</div>

{{-- Daily Breakdown Table --}}
@if(!empty($dailyBreakdown))
    <x-filament::card class="mb-6">
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Daily Breakdown</h3>

            @php
                $pagination = $this->getPaginatedDailyBreakdown($dailyBreakdown);
            @endphp

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scheduled Util %</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active Util %</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Uptime (hrs)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Downtime (hrs)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Units Produced</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WOs Completed</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machines</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($pagination['data'] as $day)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ \Carbon\Carbon::parse($day['date'])->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    <span class="font-medium text-blue-600 dark:text-blue-400">
                                        {{ number_format($day['avg_utilization_rate'] ?? 0, 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    <span class="font-medium text-green-600 dark:text-green-400">
                                        {{ number_format($day['avg_active_utilization_rate'] ?? 0, 1) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['uptime_hours'] ?? 0, 1) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['downtime_hours'] ?? 0, 1) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['units_produced'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['work_orders_completed'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ number_format($day['machines_tracked'] ?? 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($pagination['total_pages'] > 1)
                <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        Showing <span class="font-medium">{{ $pagination['from'] }}</span> to <span class="font-medium">{{ $pagination['to'] }}</span> of <span class="font-medium">{{ $pagination['total'] }}</span> days
                    </div>
                    <div class="flex gap-2">
                        @if($pagination['current_page'] > 1)
                            <button wire:click="gotoDailyBreakdownPage({{ $pagination['current_page'] - 1 }})" class="px-3 py-1 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
                                Previous
                            </button>
                        @endif

                        @for($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++)
                            <button wire:click="gotoDailyBreakdownPage({{ $i }})" class="px-3 py-1 text-sm {{ $i === $pagination['current_page'] ? 'bg-primary-600 text-white' : 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600' }} rounded hover:bg-opacity-80">
                                {{ $i }}
                            </button>
                        @endfor

                        @if($pagination['current_page'] < $pagination['total_pages'])
                            <button wire:click="gotoDailyBreakdownPage({{ $pagination['current_page'] + 1 }})" class="px-3 py-1 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
                                Next
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </x-filament::card>
@endif

{{-- Comparison Period Summary (if enabled) --}}
@if($comparisonPeriod && $comparisonAnalysis)
    <x-filament::card class="mt-6">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Comparison Analysis
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    vs {{ $comparisonPeriod['label'] }} ({{ $comparisonPeriod['start_date'] }} - {{ $comparisonPeriod['end_date'] }})
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Scheduled Utilization Comparison --}}
                @if(isset($comparisonAnalysis['scheduled_utilization']))
                    @php $comp = $comparisonAnalysis['scheduled_utilization']; @endphp
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-blue-900 dark:text-blue-100 uppercase mb-2">Scheduled Utilization</div>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($comp['current'], 1) }}% vs {{ number_format($comp['previous'], 1) }}%
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $comp['status'] === 'improved' ? '✓ Improved' : '⚠ Declined' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {!! $getTrendIcon($comp['trend']) !!}
                                    <span class="text-sm font-medium">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 2) }}%
                                    </span>
                                </div>
                                <div class="text-xs {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Active Utilization Comparison --}}
                @if(isset($comparisonAnalysis['active_utilization']))
                    @php $comp = $comparisonAnalysis['active_utilization']; @endphp
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-green-900 dark:text-green-100 uppercase mb-2">Active Utilization</div>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($comp['current'], 1) }}% vs {{ number_format($comp['previous'], 1) }}%
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $comp['status'] === 'improved' ? '✓ Improved' : '⚠ Declined' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {!! $getTrendIcon($comp['trend']) !!}
                                    <span class="text-sm font-medium">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 2) }}%
                                    </span>
                                </div>
                                <div class="text-xs {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Uptime Hours Comparison --}}
                @if(isset($comparisonAnalysis['uptime_hours']))
                    @php $comp = $comparisonAnalysis['uptime_hours']; @endphp
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-purple-900 dark:text-purple-100 uppercase mb-2">Uptime Hours</div>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($comp['current'], 1) }} vs {{ number_format($comp['previous'], 1) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $comp['status'] === 'improved' ? '✓ Improved' : '⚠ Declined' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {!! $getTrendIcon($comp['trend']) !!}
                                    <span class="text-sm font-medium">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 1) }}
                                    </span>
                                </div>
                                <div class="text-xs {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Downtime Hours Comparison --}}
                @if(isset($comparisonAnalysis['downtime_hours']))
                    @php $comp = $comparisonAnalysis['downtime_hours']; @endphp
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-red-900 dark:text-red-100 uppercase mb-2">Downtime Hours</div>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($comp['current'], 1) }} vs {{ number_format($comp['previous'], 1) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $comp['status'] === 'improved' ? '✓ Improved' : '⚠ Declined' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {!! $getTrendIcon($comp['trend']) !!}
                                    <span class="text-sm font-medium">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 1) }}
                                    </span>
                                </div>
                                <div class="text-xs {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Units Produced Comparison --}}
                @if(isset($comparisonAnalysis['units_produced']))
                    @php $comp = $comparisonAnalysis['units_produced']; @endphp
                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-900 dark:text-gray-100 uppercase mb-2">Units Produced</div>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($comp['current']) }} vs {{ number_format($comp['previous']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $comp['status'] === 'improved' ? '✓ Improved' : '⚠ Declined' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {!! $getTrendIcon($comp['trend']) !!}
                                    <span class="text-sm font-medium">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference']) }}
                                    </span>
                                </div>
                                <div class="text-xs {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Work Orders Completed Comparison --}}
                @if(isset($comparisonAnalysis['work_orders_completed']))
                    @php $comp = $comparisonAnalysis['work_orders_completed']; @endphp
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-yellow-900 dark:text-yellow-100 uppercase mb-2">Work Orders Completed</div>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($comp['current']) }} vs {{ number_format($comp['previous']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $comp['status'] === 'improved' ? '✓ Improved' : '⚠ Declined' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {!! $getTrendIcon($comp['trend']) !!}
                                    <span class="text-sm font-medium">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference']) }}
                                    </span>
                                </div>
                                <div class="text-xs {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </x-filament::card>
@endif