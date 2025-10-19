@php
    try {
        $data = $this->getProductionScheduleData();

        // Check if this is analytics mode with comparison
        $hasComparison = isset($data['comparison_period']) && isset($data['comparison_analysis']);

        // Extract data based on structure
        if ($hasComparison) {
            $summary = $data['primary_period']['summary'] ?? [];
            $comparisonAnalysis = $data['comparison_analysis'] ?? [];
        } else {
            $summary = $data['summary'] ?? [];
        }
    } catch (\Exception $e) {
        \Log::error('Production Schedule Analytics Error: ' . $e->getMessage());
        $data = [];
        $summary = [
            'scheduled_today' => 0,
            'on_time_rate' => 0,
            'on_time_count' => 0,
            'early_count' => 0,
            'late_count' => 0,
            'avg_delay_minutes' => 0,
        ];
        $hasComparison = false;
    }
@endphp

<x-filament::card>
    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold">Production Schedule Adherence - Analytics</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Historical analysis of on-time completion rates and schedule adherence
                </p>
            </div>
        </div>

        {{-- Summary Metrics Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Total Completions --}}
            <x-filament::card>
                <div class="space-y-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Total Completions
                            </h3>
                        </div>
                        <x-heroicon-o-clipboard-document-check class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex items-baseline gap-2">
                        <div class="text-4xl font-bold text-blue-600 dark:text-blue-400">
                            {{ $summary['scheduled_today'] ?? $summary['total_completions'] ?? 0 }}
                        </div>
                        @if($hasComparison && isset($comparisonAnalysis['total_completions']))
                            @php
                                $comparison = $comparisonAnalysis['total_completions'];
                                $isPositive = $comparison['difference'] > 0;
                            @endphp
                            <div class="flex items-center gap-1 text-sm font-medium {{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                @if($isPositive)
                                    <x-heroicon-s-arrow-up class="w-4 h-4" />
                                @else
                                    <x-heroicon-s-arrow-down class="w-4 h-4" />
                                @endif
                                <span>{{ abs($comparison['percentage_change']) }}%</span>
                            </div>
                        @endif
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        Work orders completed in selected period
                    </div>
                </div>
            </x-filament::card>

            {{-- On-Time Rate --}}
            <x-filament::card>
                <div class="space-y-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                On-Time Rate
                            </h3>
                        </div>
                        <x-heroicon-o-check-circle class="w-8 h-8 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="flex items-baseline gap-2">
                        <div class="text-4xl font-bold text-green-600 dark:text-green-400">
                            {{ number_format($summary['on_time_rate'] ?? 0, 1) }}%
                        </div>
                        @if($hasComparison && isset($comparisonAnalysis['on_time_rate']))
                            @php
                                $comparison = $comparisonAnalysis['on_time_rate'];
                                $isPositive = $comparison['difference'] > 0;
                            @endphp
                            <div class="flex items-center gap-1 text-sm font-medium {{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                @if($isPositive)
                                    <x-heroicon-s-arrow-up class="w-4 h-4" />
                                @else
                                    <x-heroicon-s-arrow-down class="w-4 h-4" />
                                @endif
                                <span>{{ abs($comparison['percentage_change']) }}%</span>
                            </div>
                        @endif
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        {{ $summary['on_time_count'] ?? 0 }} of {{ $summary['scheduled_today'] ?? $summary['total_completions'] ?? 0 }} completed on time
                    </div>
                </div>
            </x-filament::card>

            {{-- Average Delay --}}
            <x-filament::card>
                <div class="space-y-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Avg Delay (Late WOs)
                            </h3>
                        </div>
                        <x-heroicon-o-clock class="w-8 h-8 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="flex items-baseline gap-2">
                        <div class="text-4xl font-bold text-red-600 dark:text-red-400">
                            {{ $summary['avg_delay_minutes'] ?? 0 }} min
                        </div>
                        @if($hasComparison && isset($comparisonAnalysis['avg_delay_minutes']))
                            @php
                                $comparison = $comparisonAnalysis['avg_delay_minutes'];
                                // For delay, less is better, so invert the color logic
                                $isPositive = $comparison['difference'] < 0;
                            @endphp
                            <div class="flex items-center gap-1 text-sm font-medium {{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                @if($comparison['difference'] > 0)
                                    <x-heroicon-s-arrow-up class="w-4 h-4" />
                                @else
                                    <x-heroicon-s-arrow-down class="w-4 h-4" />
                                @endif
                                <span>{{ abs($comparison['percentage_change']) }}%</span>
                            </div>
                        @endif
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        {{ $summary['late_count'] ?? 0 }} work orders late
                    </div>
                </div>
            </x-filament::card>

            {{-- On-Time Count --}}
            <x-filament::card>
                <div class="space-y-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                On-Time Count
                            </h3>
                        </div>
                        <x-heroicon-o-document-check class="w-8 h-8 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="flex items-baseline gap-2">
                        <div class="text-4xl font-bold text-green-600 dark:text-green-400">
                            {{ $summary['on_time_count'] ?? 0 }}
                        </div>
                        @if($hasComparison && isset($comparisonAnalysis['on_time_count']))
                            @php
                                $comparison = $comparisonAnalysis['on_time_count'];
                                $isPositive = $comparison['difference'] > 0;
                            @endphp
                            <div class="flex items-center gap-1 text-sm font-medium {{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                @if($isPositive)
                                    <x-heroicon-s-arrow-up class="w-4 h-4" />
                                @else
                                    <x-heroicon-s-arrow-down class="w-4 h-4" />
                                @endif
                                <span>{{ abs($comparison['percentage_change']) }}%</span>
                            </div>
                        @endif
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        Work orders completed within ±15 min
                    </div>
                </div>
            </x-filament::card>
        </div>

        {{-- Completion Status Breakdown --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- On Time --}}
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-800 dark:text-green-300">On Time</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <p class="text-2xl font-bold text-green-900 dark:text-green-200">{{ $summary['on_time_count'] ?? 0 }}</p>
                            @if($hasComparison && isset($comparisonAnalysis['on_time_count']))
                                @php
                                    $comparison = $comparisonAnalysis['on_time_count'];
                                    $isPositive = $comparison['difference'] > 0;
                                @endphp
                                <div class="flex items-center gap-0.5 text-xs font-medium {{ $isPositive ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                    @if($isPositive)
                                        <x-heroicon-s-arrow-up class="w-3 h-3" />
                                    @else
                                        <x-heroicon-s-arrow-down class="w-3 h-3" />
                                    @endif
                                    <span>{{ abs($comparison['percentage_change']) }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>

            {{-- Early --}}
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-800 dark:text-blue-300">Early</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <p class="text-2xl font-bold text-blue-900 dark:text-blue-200">{{ $summary['early_count'] ?? 0 }}</p>
                            @if($hasComparison && isset($comparisonAnalysis['early_count']))
                                @php
                                    $comparison = $comparisonAnalysis['early_count'];
                                    $isPositive = $comparison['difference'] > 0;
                                @endphp
                                <div class="flex items-center gap-0.5 text-xs font-medium {{ $isPositive ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                    @if($isPositive)
                                        <x-heroicon-s-arrow-up class="w-3 h-3" />
                                    @else
                                        <x-heroicon-s-arrow-down class="w-3 h-3" />
                                    @endif
                                    <span>{{ abs($comparison['percentage_change']) }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>

            {{-- Late --}}
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-red-800 dark:text-red-300">Late</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <p class="text-2xl font-bold text-red-900 dark:text-red-200">{{ $summary['late_count'] ?? 0 }}</p>
                            @if($hasComparison && isset($comparisonAnalysis['late_count']))
                                @php
                                    $comparison = $comparisonAnalysis['late_count'];
                                    // For late count, less is better, so invert the color logic
                                    $isPositive = $comparison['difference'] < 0;
                                @endphp
                                <div class="flex items-center gap-0.5 text-xs font-medium {{ $isPositive ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                    @if($comparison['difference'] > 0)
                                        <x-heroicon-s-arrow-up class="w-3 h-3" />
                                    @else
                                        <x-heroicon-s-arrow-down class="w-3 h-3" />
                                    @endif
                                    <span>{{ abs($comparison['percentage_change']) }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Comparison Summary Section --}}
        @if($hasComparison)
            <x-filament::card class="mb-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Comparison Analysis
                        </h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            vs {{ $data['comparison_period']['label'] ?? 'Previous Period' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {{-- Total Completions Comparison --}}
                        @if(isset($comparisonAnalysis['total_completions']))
                            @php $comp = $comparisonAnalysis['total_completions']; @endphp
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                <div class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Total Completions</div>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <div class="text-2xl font-bold">{{ $comp['current'] }}</div>
                                    <div class="text-sm text-gray-500">vs {{ $comp['previous'] }}</div>
                                </div>
                                <div class="mt-1 flex items-center gap-1 text-sm">
                                    <span class="font-medium {{ $comp['difference'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ $comp['difference'] }}
                                    </span>
                                    <span class="text-gray-500 dark:text-gray-400">({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ $comp['percentage_change'] }}%)</span>
                                </div>
                            </div>
                        @endif

                        {{-- On-Time Rate Comparison --}}
                        @if(isset($comparisonAnalysis['on_time_rate']))
                            @php $comp = $comparisonAnalysis['on_time_rate']; @endphp
                            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                <div class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">On-Time Rate</div>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <div class="text-2xl font-bold">{{ number_format($comp['current'], 1) }}%</div>
                                    <div class="text-sm text-gray-500">vs {{ number_format($comp['previous'], 1) }}%</div>
                                </div>
                                <div class="mt-1 flex items-center gap-1 text-sm">
                                    <span class="font-medium {{ $comp['difference'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 1) }}pp
                                    </span>
                                    <span class="text-gray-500 dark:text-gray-400">({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ $comp['percentage_change'] }}%)</span>
                                </div>
                            </div>
                        @endif

                        {{-- Average Delay Comparison --}}
                        @if(isset($comparisonAnalysis['avg_delay_minutes']))
                            @php $comp = $comparisonAnalysis['avg_delay_minutes']; @endphp
                            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                                <div class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Avg Delay (Late WOs)</div>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <div class="text-2xl font-bold">{{ $comp['current'] }} min</div>
                                    <div class="text-sm text-gray-500">vs {{ $comp['previous'] }} min</div>
                                </div>
                                <div class="mt-1 flex items-center gap-1 text-sm">
                                    <span class="font-medium {{ $comp['difference'] < 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $comp['difference'] > 0 ? '+' : '' }}{{ $comp['difference'] }} min
                                    </span>
                                    <span class="text-gray-500 dark:text-gray-400">({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ $comp['percentage_change'] }}%)</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </x-filament::card>
        @endif

        {{-- Date Range Information --}}
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
            <span>
                @if(isset($data['primary_period']['start_date']))
                    Period: {{ $data['primary_period']['start_date'] }} to {{ $data['primary_period']['end_date'] }}
                @endif
            </span>
            <span class="flex items-center gap-2">
                <x-heroicon-o-arrow-path class="w-3 h-3 animate-spin" wire:loading wire:target="getProductionScheduleData" />
                <span>Last Updated: {{ now()->format('M d, Y H:i:s') }}</span>
            </span>
        </div>
    </div>
</x-filament::card>
