@php
    $primaryPeriod = $data['primary_period'] ?? null;

    if (! $primaryPeriod) {
        echo '<div class="text-center py-8 text-sm text-gray-500 dark:text-gray-400">No analytics data available for the selected period.</div>';

        return;
    }

    $summary = $primaryPeriod['summary'] ?? [];
    $dailyBreakdown = $primaryPeriod['daily_breakdown'] ?? [];
    $machineBreakdown = $primaryPeriod['machine_breakdown'] ?? [];
    $comparisonPeriod = $data['comparison_period'] ?? null;
    $comparisonAnalysis = $data['comparison_analysis'] ?? null;

    $formatMinutes = static function (?int $minutes): string {
        if ($minutes === null) {
            return 'N/A';
        }

        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return trim(($hours ? $hours . 'h' : '') . ($mins ? ' ' . $mins . 'm' : ''));
    };
@endphp

{{-- Period Header --}}
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $primaryPeriod['label'] ?? 'Selected Period' }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $primaryPeriod['start_date'] ?? '' }} to {{ $primaryPeriod['end_date'] ?? '' }}
                @if(!empty($summary['days_analyzed']))
                    ({{ $summary['days_analyzed'] }} days analysed)
                @endif
            </p>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Setup Time
            </h4>
            <div class="text-3xl font-bold text-violet-600 dark:text-violet-400">
                {{ number_format($summary['total_setup_time'] ?? 0, 2) }} h
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format($summary['total_setup_minutes'] ?? 0) }} minutes across {{ number_format($summary['total_setups'] ?? 0) }} setups
            </p>
        </div>
    </x-filament::card>

    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Avg Setup Duration
            </h4>
            <div class="text-3xl font-bold text-violet-600 dark:text-violet-400">
                {{ $formatMinutes(isset($summary['avg_setup_duration']) ? (int) round($summary['avg_setup_duration']) : null) }}
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Average per setup during this period
            </p>
        </div>
    </x-filament::card>

    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Avg Daily Setup Time
            </h4>
            <div class="text-3xl font-bold text-violet-600 dark:text-violet-400">
                {{ number_format($summary['avg_daily_setup_time'] ?? 0, 2) }} h
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format(($summary['avg_setup_percentage'] ?? 0), 1) }}% of an 8h shift
            </p>
        </div>
    </x-filament::card>

    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Longest Setup
            </h4>
            <div class="text-3xl font-bold text-violet-600 dark:text-violet-400">
                {{ $formatMinutes(isset($summary['max_setup_duration']) ? (int) $summary['max_setup_duration'] : null) }}
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Minimum {{ $formatMinutes(isset($summary['min_setup_duration']) ? (int) $summary['min_setup_duration'] : null) }}
            </p>
        </div>
    </x-filament::card>

    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Machines With Setups
            </h4>
            <div class="text-3xl font-bold text-violet-600 dark:text-violet-400">
                {{ number_format($summary['machines_with_setups'] ?? 0) }}
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Total setups analysed in this period
            </p>
        </div>
    </x-filament::card>
</div>

{{-- Daily Breakdown Table --}}
<x-filament::card class="mb-6">
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Daily Setup Time Breakdown
        </h3>

        @if(!empty($dailyBreakdown))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Setups</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total (min)</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total (h)</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Average</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Min</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Max</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($dailyBreakdown as $day)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ \Carbon\Carbon::parse($day['date'])->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ $day['total_setups'] ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['total_setup_time'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['total_setup_time_hours'] ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ $formatMinutes(isset($day['avg_setup_time']) ? (int) round($day['avg_setup_time']) : null) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ $formatMinutes(isset($day['min_setup_time']) ? (int) $day['min_setup_time'] : null) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ $formatMinutes(isset($day['max_setup_time']) ? (int) $day['max_setup_time'] : null) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                No setup activity recorded during this period.
            </div>
        @endif
    </div>
</x-filament::card>

{{-- Machine Breakdown --}}
<x-filament::card class="mb-6">
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Setup Time by Machine
            </h3>
        </div>

        @if(!empty($machineBreakdown))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Asset ID</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Setups</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total (h)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Average</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($machineBreakdown as $machine)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $machine['machine_name'] ?? 'Unknown Machine' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ $machine['asset_id'] ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                    {{ number_format($machine['total_setups'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                    {{ number_format($machine['total_setup_time_hours'] ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                    {{ $formatMinutes(isset($machine['avg_setup_time']) ? (int) round($machine['avg_setup_time']) : null) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                No setup events recorded for any machine.
            </div>
        @endif
    </div>
</x-filament::card>

{{-- Comparison Period --}}
@if($comparisonPeriod)
    <x-filament::card>
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Comparison: {{ $comparisonPeriod['label'] ?? 'Previous Period' }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $comparisonPeriod['start_date'] ?? '' }} to {{ $comparisonPeriod['end_date'] ?? '' }}
            </p>

            @if($comparisonAnalysis)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach([
                        'total_setup_time' => ['label' => 'Total Setup Time (h)', 'unit' => 'h'],
                        'avg_daily_setup_time' => ['label' => 'Avg Daily Setup (h)', 'unit' => 'h'],
                        'avg_setup_duration' => ['label' => 'Avg Setup Duration', 'unit' => 'min'],
                        'total_setups' => ['label' => 'Total Setups', 'unit' => ''],
                    ] as $key => $meta)
                        @php
                            $metric = $comparisonAnalysis[$key] ?? null;
                        @endphp
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                            <h4 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">
                                {{ $meta['label'] }}
                            </h4>
                            @if($metric)
                                <div class="text-lg font-bold {{ ($metric['status'] ?? '') === 'improved' ? 'text-green-600 dark:text-green-400' : (($metric['status'] ?? '') === 'declined' ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white') }}">
                                    {{ number_format($metric['current'] ?? 0, 2) }}{{ $meta['unit'] }}
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ ($metric['difference'] ?? 0) > 0 ? '+' : '' }}{{ number_format($metric['difference'] ?? 0, 2) }}{{ $meta['unit'] }}
                                    ({{ ($metric['percentage_change'] ?? 0) > 0 ? '+' : '' }}{{ number_format($metric['percentage_change'] ?? 0, 2) }}%)
                                </p>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">No comparison data</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Comparison data not available for the selected options.
                </div>
            @endif
        </div>
    </x-filament::card>
@endif
