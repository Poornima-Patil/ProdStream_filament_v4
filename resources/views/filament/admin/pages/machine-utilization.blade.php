{{-- Machine Utilization Rate - Dashboard Mode --}}
{{-- Shows TODAY's utilization data only --}}
{{-- Two types: Scheduled Utilization (factory view) and Active Utilization (operator view) --}}

@php
    $summary = $data['summary'] ?? [];
    $machines = $data['machines'] ?? [];
    $scheduledUtilRate = $summary['scheduled_utilization_rate'] ?? 0;
    $activeUtilRate = $summary['active_utilization_rate'] ?? 0;
    $totalMachines = $summary['total_machines'] ?? 0;
    $machinesWithWork = $summary['machines_with_work'] ?? 0;
    $machinesIdle = $summary['machines_idle'] ?? 0;
    $totalScheduledHours = $summary['total_scheduled_hours'] ?? 0;
    $totalActiveHours = $summary['total_active_hours'] ?? 0;
    $totalHoldHours = $summary['total_hold_hours'] ?? 0;
    $date = $summary['date'] ?? now()->format('Y-m-d');

    // Helper function to get color class based on utilization percentage
    $getUtilizationColor = function($rate) {
        if ($rate >= 70) return 'text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20';
        if ($rate >= 40) return 'text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20';
        return 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20';
    };
@endphp

{{-- Factory-Wide Summary Section --}}
<div class="mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Factory-Wide Utilization - {{ \Carbon\Carbon::parse($date)->format('F d, Y') }}
        </h3>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            Data for TODAY only
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Scheduled Utilization Card --}}
        <x-filament::card>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Scheduled Utilization
                    </h4>
                    <x-heroicon-o-calendar class="w-5 h-5 text-blue-500" />
                </div>
                <div class="flex items-baseline gap-2">
                    <div class="text-3xl font-bold {{ $getUtilizationColor($scheduledUtilRate) }} px-3 py-1 rounded">
                        {{ number_format($scheduledUtilRate, 1) }}%
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Factory View (includes hold time)
                </p>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ number_format($totalScheduledHours, 1) }}h scheduled
                </p>
            </div>
        </x-filament::card>

        {{-- Active Utilization Card --}}
        <x-filament::card>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Active Utilization
                    </h4>
                    <x-heroicon-o-play class="w-5 h-5 text-green-500" />
                </div>
                <div class="flex items-baseline gap-2">
                    <div class="text-3xl font-bold {{ $getUtilizationColor($activeUtilRate) }} px-3 py-1 rounded">
                        {{ number_format($activeUtilRate, 1) }}%
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Operator View (excludes hold time)
                </p>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ number_format($totalActiveHours, 1) }}h active
                </p>
            </div>
        </x-filament::card>

        {{-- Active Machines Card --}}
        <x-filament::card>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Active Machines
                    </h4>
                    <x-heroicon-o-cog class="w-5 h-5 text-green-500" />
                </div>
                <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                    {{ $machinesWithWork }}
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    of {{ $totalMachines }} machines
                </p>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $totalMachines > 0 ? ($machinesWithWork / $totalMachines) * 100 : 0 }}%"></div>
                </div>
            </div>
        </x-filament::card>

        {{-- Idle Machines Card --}}
        <x-filament::card>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Idle Machines
                    </h4>
                    <x-heroicon-o-pause class="w-5 h-5 text-gray-500" />
                </div>
                <div class="text-3xl font-bold text-gray-600 dark:text-gray-400">
                    {{ $machinesIdle }}
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    of {{ $totalMachines }} machines
                </p>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div class="bg-gray-500 h-2 rounded-full" style="width: {{ $totalMachines > 0 ? ($machinesIdle / $totalMachines) * 100 : 0 }}%"></div>
                </div>
            </div>
        </x-filament::card>
    </div>

    {{-- Utilization Gap Indicator --}}
    @if($scheduledUtilRate > 0 && $activeUtilRate > 0)
        @php
            $gap = $scheduledUtilRate - $activeUtilRate;
        @endphp
        <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <div class="flex items-start gap-3">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                <div>
                    <p class="text-sm font-medium text-blue-900 dark:text-blue-200">
                        Utilization Gap Analysis
                    </p>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                        <span class="font-semibold">{{ number_format($gap, 1) }}%</span> gap between scheduled and active utilization
                        ({{ number_format($totalHoldHours, 1) }} hours in hold/delay)
                    </p>
                    @if($gap > 10)
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                            ⚠️ High gap indicates significant hold time - investigate causes
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Per-Machine Breakdown Table --}}
<div>
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        Per-Machine Breakdown
    </h3>

    <x-filament::card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Machine
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Scheduled Util %
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Active Util %
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Scheduled Hours
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Active Hours
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Hold Hours
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Idle Hours
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Work Orders
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($machines as $machine)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            {{-- Machine Name --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $machine['name'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $machine['asset_id'] }}
                                    </div>
                                </div>
                            </td>

                            {{-- Scheduled Utilization % --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getUtilizationColor($machine['scheduled_utilization']) }}">
                                    {{ number_format($machine['scheduled_utilization'], 1) }}%
                                </span>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-2">
                                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ min($machine['scheduled_utilization'], 100) }}%"></div>
                                </div>
                            </td>

                            {{-- Active Utilization % --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $getUtilizationColor($machine['active_utilization']) }}">
                                    {{ number_format($machine['active_utilization'], 1) }}%
                                </span>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-2">
                                    <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ min($machine['active_utilization'], 100) }}%"></div>
                                </div>
                            </td>

                            {{-- Scheduled Hours --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-900 dark:text-white">
                                {{ number_format($machine['scheduled_hours'], 1) }}h
                            </td>

                            {{-- Active Hours --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-900 dark:text-white">
                                {{ number_format($machine['active_hours'], 1) }}h
                            </td>

                            {{-- Hold Hours --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="text-sm {{ $machine['hold_hours'] > 0 ? 'text-yellow-600 dark:text-yellow-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ number_format($machine['hold_hours'], 1) }}h
                                </span>
                            </td>

                            {{-- Idle Hours --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="text-sm {{ $machine['idle_hours'] > 0 ? 'text-gray-600 dark:text-gray-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ number_format($machine['idle_hours'], 1) }}h
                                </span>
                            </td>

                            {{-- Work Orders Count --}}
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                    {{ $machine['work_order_count'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No machine data available for today
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Legend --}}
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Understanding Utilization Metrics:</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-400">
                <div class="flex items-start gap-2">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" />
                    <div>
                        <span class="font-semibold">Scheduled Utilization:</span> Percentage of available time with work orders scheduled (includes hold periods). Factory/management perspective.
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" />
                    <div>
                        <span class="font-semibold">Active Utilization:</span> Percentage of available time machines were actively running (excludes hold periods). Operator/production perspective.
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-yellow-500 mt-0.5 flex-shrink-0" />
                    <div>
                        <span class="font-semibold">Hold Hours:</span> Time machines were assigned work but paused due to issues (material shortage, quality checks, etc.).
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0" />
                    <div>
                        <span class="font-semibold">Idle Hours:</span> Time machines had no work scheduled at all.
                    </div>
                </div>
            </div>
            <div class="mt-3 p-2 bg-gray-50 dark:bg-gray-800 rounded text-xs text-gray-600 dark:text-gray-400">
                <span class="font-semibold">Key Insight:</span> Active Utilization ≤ Scheduled Utilization. The gap reveals hold/delay time that impacts productivity.
            </div>
        </div>
    </x-filament::card>
</div>
