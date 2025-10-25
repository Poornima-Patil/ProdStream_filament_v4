{{-- Work Order Status - Analytics Mode --}}
{{-- Shows historical work order status distribution (Assigned/Setup/Start/Hold/Completed/Closed) --}}

@php
    $woData = $this->getWorkOrderStatusData();
    $primaryPeriod = $woData['primary_period'] ?? null;
    $comparisonPeriod = $woData['comparison_period'] ?? null;
    $comparisonAnalysis = $woData['comparison_analysis'] ?? null;
    $statusDistribution = $woData['status_distribution'] ?? [];

    if (!$primaryPeriod) {
        echo '<div class="text-center py-8"><p class="text-gray-500 dark:text-gray-400">No analytics data available</p></div>';
        return;
    }

    $summary = $primaryPeriod['summary'] ?? [];

    // Helper function to get color classes based on status
    $getStatusColor = function($status) {
        return match($status) {
            'assigned' => ['bg' => 'bg-blue-50 dark:bg-blue-900/20', 'text' => 'text-blue-600 dark:text-blue-400', 'border' => 'border-blue-200 dark:border-blue-800'],
            'setup' => ['bg' => 'bg-violet-50 dark:bg-violet-900/20', 'text' => 'text-violet-600 dark:text-violet-400', 'border' => 'border-violet-200 dark:border-violet-800'],
            'start' => ['bg' => 'bg-green-50 dark:bg-green-900/20', 'text' => 'text-green-600 dark:text-green-400', 'border' => 'border-green-200 dark:border-green-800'],
            'hold' => ['bg' => 'bg-yellow-50 dark:bg-yellow-900/20', 'text' => 'text-yellow-600 dark:text-yellow-400', 'border' => 'border-yellow-200 dark:border-yellow-800'],
            'completed' => ['bg' => 'bg-purple-50 dark:bg-purple-900/20', 'text' => 'text-purple-600 dark:text-purple-400', 'border' => 'border-purple-200 dark:border-purple-800'],
            'closed' => ['bg' => 'bg-gray-50 dark:bg-gray-900/20', 'text' => 'text-gray-600 dark:text-gray-400', 'border' => 'border-gray-200 dark:border-gray-700'],
            default => ['bg' => 'bg-gray-50 dark:bg-gray-900/20', 'text' => 'text-gray-600 dark:text-gray-400', 'border' => 'border-gray-200 dark:border-gray-700'],
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

    // Get icon component for status
    $getStatusIcon = function($status) {
        return match($status) {
            'assigned' => 'heroicon-o-clipboard-document-list',
            'setup' => 'heroicon-o-wrench-screwdriver',
            'start' => 'heroicon-o-play-circle',
            'hold' => 'heroicon-o-pause-circle',
            'completed' => 'heroicon-o-check-circle',
            'closed' => 'heroicon-o-lock-closed',
            default => 'heroicon-o-document',
        };
    };

    // Get status label
    $getStatusLabel = function($status) {
        return match($status) {
            'assigned' => 'Assigned',
            'setup' => 'Setup',
            'start' => 'Running',
            'hold' => 'On Hold',
            'completed' => 'Completed',
            'closed' => 'Closed',
            default => ucfirst($status),
        };
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
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Work Orders</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ number_format($woData['total_work_orders'] ?? 0) }}
            </div>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    @foreach(['assigned', 'setup', 'start', 'hold', 'completed', 'closed'] as $status)
        @php
            $colors = $getStatusColor($status);
            $count = $statusDistribution[$status]['count'] ?? 0;
            $percentage = $summary[$status . '_pct'] ?? 0;
        @endphp

        <x-filament::card>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        {{ $getStatusLabel($status) }}
                    </h4>
                    <x-dynamic-component :component="$getStatusIcon($status)" class="w-5 h-5 {{ $colors['text'] }}" />
                </div>
                <div class="flex items-baseline gap-2">
                    <div class="text-3xl font-bold {{ $colors['text'] }}">
                        {{ number_format($count) }}
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($percentage, 1) }}% of total
                </p>

                {{-- Comparison Indicator --}}
                @if($comparisonAnalysis && isset($comparisonAnalysis[$status]))
                    <div class="flex items-center gap-1 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                        {!! $getTrendIcon($comparisonAnalysis[$status]['trend']) !!}
                        <span class="{{ $comparisonAnalysis[$status]['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : ($comparisonAnalysis[$status]['status'] === 'declined' ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400') }}">
                            {{ $comparisonAnalysis[$status]['difference'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis[$status]['difference']) }}
                            ({{ $comparisonAnalysis[$status]['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comparisonAnalysis[$status]['percentage_change'], 1) }}%)
                        </span>
                    </div>
                @endif
            </div>
        </x-filament::card>
    @endforeach
</div>

{{-- Important Note about Analytics Mode --}}
<x-filament::card class="mb-6">
    <div class="flex items-start gap-3">
        <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" />
        <div class="text-sm text-gray-600 dark:text-gray-400">
            <strong class="text-gray-900 dark:text-white">Analytics Mode:</strong> Work orders are counted if they had the specified status during the selected period.
            A single work order may appear in multiple status categories if it transitioned through different states.
            <span class="text-xs text-gray-500 dark:text-gray-500 block mt-1">
                Example: A work order that was "Assigned" then "Start" then "Completed" during the period appears in all three categories.
            </span>
        </div>
    </div>
</x-filament::card>

{{-- Work Order Tables by Status --}}
<div class="space-y-6">
    @foreach(['hold', 'start', 'setup', 'assigned', 'completed', 'closed'] as $status)
        @if(!empty($statusDistribution[$status]['work_orders']))
            @php
                $colors = $getStatusColor($status);
                $workOrders = $statusDistribution[$status]['work_orders'];
                $pagination = $this->getPaginatedWorkOrders($workOrders, $status);
                $isExpanded = $this->{"wo" . ucfirst($status) . "Expanded"};
            @endphp

            <x-filament::card>
                <button
                    wire:click="toggleWOSection('{{ $status }}')"
                    class="w-full {{ $colors['bg'] }} px-4 py-3 border-b {{ $colors['border'] }} hover:opacity-80 transition-opacity rounded-t-lg">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold {{ str_replace('dark:text', 'dark:text', str_replace('text-', 'text-', $colors['text'])) }} flex items-center gap-2">
                            <x-dynamic-component :component="$getStatusIcon($status)" class="w-5 h-5" />
                            {{ $getStatusLabel($status) }} Work Orders ({{ count($workOrders) }})
                        </h3>
                        <x-dynamic-component
                            :component="$isExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                            class="w-5 h-5 {{ $colors['text'] }}" />
                    </div>
                </button>

                @if($isExpanded)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO Number</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qty (Produced/Target)</th>
                                    @if($status === 'hold')
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Hold Reason</th>
                                    @elseif($status === 'start')
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Progress</th>
                                    @elseif($status === 'setup')
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Setup Duration</th>
                                    @elseif($status === 'assigned')
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scheduled</th>
                                    @elseif(in_array($status, ['completed', 'closed']))
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status Changed</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($pagination['data'] as $wo)
                                    <tr class="hover:{{ $colors['bg'] }}">
                                        <td class="px-4 py-3">
                                            <a href="{{ \App\Filament\Admin\Resources\WorkOrderResource::getUrl('view', ['record' => $wo['id']]) }}"
                                               class="text-sm font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                               wire:navigate>
                                                {{ $wo['wo_number'] }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-sm text-gray-900 dark:text-white">
                                                {{ $wo['machine_name'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $wo['machine_asset_id'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                            {{ $wo['part_number'] }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                            {{ $wo['operator'] }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                            {{ number_format($wo['qty_produced'] ?? 0) }} / {{ number_format($wo['qty_target'] ?? 0) }}
                                        </td>
                                        @if($status === 'hold')
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded">
                                                    {{ $wo['hold_reason'] ?? 'N/A' }}
                                                </span>
                                            </td>
                                        @elseif($status === 'start')
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                                        <div class="bg-green-500 h-2 rounded-full" style="width: {{ $wo['progress_percentage'] ?? 0 }}%"></div>
                                                    </div>
                                                    <span class="text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                                        {{ number_format($wo['progress_percentage'] ?? 0, 0) }}%
                                                    </span>
                                                </div>
                                            </td>
                                        @elseif($status === 'setup')
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                {{ $wo['setup_duration'] ?? 'N/A' }}
                                            </td>
                                        @elseif($status === 'assigned')
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                {{ $wo['scheduled_start'] ?? 'N/A' }}
                                            </td>
                                        @elseif($status === 'completed')
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                {{ isset($wo['status_changed_at']) ? \Carbon\Carbon::parse($wo['status_changed_at'])->format('M d, H:i') : 'N/A' }}
                                            </td>
                                        @elseif($status === 'closed')
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                {{ isset($wo['status_changed_at']) ? \Carbon\Carbon::parse($wo['status_changed_at'])->format('M d, H:i') : 'N/A' }}
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    <x-machine-table-pagination :pagination="$pagination" :status="$status" wireMethod="gotoWOPage" />
                @endif
            </x-filament::card>
        @endif
    @endforeach
</div>

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
                {{-- Total Work Orders Comparison --}}
                @if(isset($comparisonAnalysis['total']))
                    @php $comp = $comparisonAnalysis['total']; @endphp
                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase mb-2">Total Work Orders</div>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($comp['current']) }} vs {{ number_format($comp['previous']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Current vs Previous
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 {{ $comp['trend'] === 'up' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {!! $getTrendIcon($comp['trend']) !!}
                                    <span class="text-sm font-medium">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference']) }}
                                    </span>
                                </div>
                                <div class="text-xs {{ $comp['trend'] === 'up' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Start (Running) Comparison --}}
                @if(isset($comparisonAnalysis['start']))
                    @php $comp = $comparisonAnalysis['start']; @endphp
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-green-900 dark:text-green-100 uppercase mb-2">Running Work Orders</div>
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

                {{-- Hold Comparison --}}
                @if(isset($comparisonAnalysis['hold']))
                    @php $comp = $comparisonAnalysis['hold']; @endphp
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-yellow-900 dark:text-yellow-100 uppercase mb-2">Work Orders On Hold</div>
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

                {{-- Completed Comparison --}}
                @if(isset($comparisonAnalysis['completed']))
                    @php $comp = $comparisonAnalysis['completed']; @endphp
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-purple-900 dark:text-purple-100 uppercase mb-2">Completed Work Orders</div>
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

                {{-- Closed Comparison --}}
                @if(isset($comparisonAnalysis['closed']))
                    @php $comp = $comparisonAnalysis['closed']; @endphp
                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-900 dark:text-gray-100 uppercase mb-2">Closed Work Orders</div>
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

                {{-- Setup Comparison --}}
                @if(isset($comparisonAnalysis['setup']))
                    @php $comp = $comparisonAnalysis['setup']; @endphp
                    <div class="bg-violet-50 dark:bg-violet-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-violet-900 dark:text-violet-100 uppercase mb-2">Setup Work Orders</div>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($comp['current']) }} vs {{ number_format($comp['previous']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Current vs Previous
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                    {!! $getTrendIcon($comp['trend']) !!}
                                    <span class="text-sm font-medium">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference']) }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                    {{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ number_format($comp['percentage_change'], 1) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Assigned Comparison --}}
                @if(isset($comparisonAnalysis['assigned']))
                    @php $comp = $comparisonAnalysis['assigned']; @endphp
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                        <div class="text-xs font-medium text-blue-900 dark:text-blue-100 uppercase mb-2">Assigned Work Orders</div>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($comp['current']) }} vs {{ number_format($comp['previous']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Current vs Previous
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                    {!! $getTrendIcon($comp['trend']) !!}
                                    <span class="text-sm font-medium">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference']) }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
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