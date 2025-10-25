@php
    $primaryPeriod = $data['primary_period'] ?? null;

    if (! $primaryPeriod) {
        echo '<div class="text-center py-8 text-sm text-gray-500 dark:text-gray-400">No analytics data available for the selected period.</div>';

        return;
    }

    $summary = $primaryPeriod['summary'] ?? [];
    $dailyBreakdown = $primaryPeriod['daily_breakdown'] ?? [];
    $machineBreakdown = $primaryPeriod['machine_breakdown'] ?? [];
    $workOrderBreakdown = $primaryPeriod['work_order_breakdown'] ?? [];
    $comparisonPeriod = $data['comparison_period'] ?? null;
    $comparisonAnalysis = $data['comparison_analysis'] ?? null;
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
        @if($comparisonPeriod)
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Comparing with {{ $comparisonPeriod['label'] ?? '' }}
            </div>
        @endif
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Scrap Qty
            </h4>
            <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                {{ number_format($summary['total_scrap_qty'] ?? 0) }}
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Combined defective units captured in this period
            </p>
        </div>
    </x-filament::card>

    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Produced Qty
            </h4>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ number_format($summary['total_produced_qty'] ?? 0) }}
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Ok + scrap quantities logged across all work orders
            </p>
        </div>
    </x-filament::card>

    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Avg Defect Rate
            </h4>
            <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                {{ number_format($summary['avg_defect_rate'] ?? 0, 2) }}%
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Weighted by total produced volume
            </p>
        </div>
    </x-filament::card>

    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Worst Defect Rate
            </h4>
            <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                {{ number_format($summary['worst_defect_rate'] ?? 0, 2) }}%
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Highest work-order defect rate in the window
            </p>
        </div>
    </x-filament::card>

    <x-filament::card>
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                WOs With Scrap
            </h4>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ number_format($summary['work_orders_with_scrap'] ?? 0) }}
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Unique work orders with any scrapped units
            </p>
        </div>
    </x-filament::card>
</div>

{{-- Comparison Analysis --}}
@if($comparisonAnalysis)
    <x-filament::card class="mb-6">
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Period Comparison
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($comparisonAnalysis as $metric => $values)
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                            {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $metric)) }}
                        </p>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($values['current'] ?? 0, $metric === 'avg_defect_rate' ? 2 : 0) }}{{ $metric === 'avg_defect_rate' ? '%' : '' }}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                vs {{ number_format($values['previous'] ?? 0, $metric === 'avg_defect_rate' ? 2 : 0) }}{{ $metric === 'avg_defect_rate' ? '%' : '' }}
                            </span>
                        </div>
                        <div class="mt-2 text-sm {{ ($values['status'] ?? 'neutral') === 'improved' ? 'text-green-600 dark:text-green-400' : (($values['status'] ?? 'neutral') === 'declined' ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400') }}">
                            {{ number_format($values['difference'] ?? 0, $metric === 'avg_defect_rate' ? 2 : 0) }}{{ $metric === 'avg_defect_rate' ? '%' : '' }}
                            ({{ number_format($values['percentage_change'] ?? 0, 2) }}%)
                            —
                            {{ ucfirst($values['status'] ?? 'neutral') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::card>
@endif

{{-- Daily Breakdown --}}
<x-filament::card class="mb-6">
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Daily Defect Rate Breakdown
        </h3>

        @if(!empty($dailyBreakdown))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WOs With Scrap</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scrap Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ok Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produced Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Defect Rate</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Worst WO</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($dailyBreakdown as $day)
                            <tr class="hover:bg-red-50 dark:hover:bg-red-900/10">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ \Carbon\Carbon::parse($day['date'])->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['defective_work_orders'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['scrap_qty'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['ok_qty'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ number_format($day['produced_qty'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400 font-medium">
                                    {{ number_format($day['defect_rate'] ?? 0, 2) }}%
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    @if(!empty($day['worst_work_order']))
                                        <div class="font-mono font-medium text-primary-600 dark:text-primary-400">
                                            <a href="{{ \App\Filament\Admin\Resources\WorkOrderResource::getUrl('view', ['record' => $day['worst_work_order']['work_order_id']]) }}"
                                               wire:navigate>
                                                {{ $day['worst_work_order']['work_order_number'] ?? 'N/A' }}
                                            </a>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $day['worst_work_order']['machine_name'] ?? 'Unknown' }}
                                            @if(!empty($day['worst_work_order']['machine_asset_id']))
                                                ({{ $day['worst_work_order']['machine_asset_id'] }})
                                            @endif
                                        </div>
                                        <div class="text-xs text-red-600 dark:text-red-400 font-medium">
                                            {{ number_format($day['worst_work_order']['defect_rate'] ?? 0, 2) }}% · {{ number_format($day['worst_work_order']['scrap_qty'] ?? 0) }} scrap
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">No scrap recorded</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                No defect activity captured for this period.
            </div>
        @endif
    </div>
</x-filament::card>

{{-- Machine Breakdown --}}
<x-filament::card class="mb-6">
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Defect Rate by Machine
        </h3>

        @if(!empty($machineBreakdown))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scrap Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ok Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produced Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Defect Rate</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WOs With Scrap</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($machineBreakdown as $machine)
                            <tr class="hover:bg-red-50 dark:hover:bg-red-900/10">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    <div class="font-medium">{{ $machine['machine_name'] ?? 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $machine['asset_id'] ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ number_format($machine['scrap_qty'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ number_format($machine['ok_qty'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ number_format($machine['produced_qty'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400 font-medium">
                                    {{ number_format($machine['defect_rate'] ?? 0, 2) }}%
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">
                                    {{ number_format($machine['defective_work_orders'] ?? 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                No machines reported scrap for the selected period.
            </div>
        @endif
    </div>
</x-filament::card>

{{-- Work Order Breakdown --}}
<x-filament::card>
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Work Orders with Highest Defect Rates
        </h3>

        @if(!empty($workOrderBreakdown))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Work Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scrap Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ok Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produced Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Defect Rate</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Last Scrap</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($workOrderBreakdown as $wo)
                            <tr class="hover:bg-red-50 dark:hover:bg-red-900/10">
                                <td class="px-4 py-3 text-sm font-mono font-medium text-primary-600 dark:text-primary-400">
                                    <a href="{{ \App\Filament\Admin\Resources\WorkOrderResource::getUrl('view', ['record' => $wo['work_order_id']]) }}"
                                       wire:navigate>
                                        {{ $wo['work_order_number'] ?? 'N/A' }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    <div class="font-medium">{{ $wo['machine_name'] ?? 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $wo['machine_asset_id'] ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ number_format($wo['scrap_qty'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ number_format($wo['ok_qty'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ number_format($wo['produced_qty'] ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400 font-medium">
                                    {{ number_format($wo['defect_rate'] ?? 0, 2) }}%
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    @if(!empty($wo['last_scrap_at']))
                                        <div>{{ \Carbon\Carbon::parse($wo['last_scrap_at'])->format('M d, H:i') }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($wo['last_scrap_at'])->diffForHumans() }}
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">No scrap recorded</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                No work orders recorded scrap during this period.
            </div>
        @endif
    </div>
</x-filament::card>
