<x-filament-panels::page>
    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    @endonce

    {{-- Hub View: Category Cards --}}
    @if($viewMode === 'hub')
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">KPI Analytics Hub</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Browse {{  \App\Services\KPI\KPIRegistry::getTotalKPICount() }} KPIs across 6 categories to monitor your production performance
                    </p>
                </div>
            </div>

            {{-- Category Cards Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($this->getCategories() as $categoryKey => $category)
                    <x-filament::card class="hover:shadow-lg transition-shadow cursor-pointer" wire:click="viewCategory('{{ $categoryKey }}')">
                        <div class="space-y-4">
                            {{-- Icon and Title --}}
                            <div class="flex items-start justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-{{ $category['color'] }}-100 dark:bg-{{ $category['color'] }}-900/20">
                                        <x-dynamic-component :component="$category['icon']" class="w-6 h-6 text-{{ $category['color'] }}-600 dark:text-{{ $category['color'] }}-400" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ $category['name'] }}
                                        </h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ \App\Services\KPI\KPIRegistry::getKPICountByCategory($categoryKey) }} KPIs
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- Description --}}
                            <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                {{ $category['description'] }}
                            </p>

                            {{-- View Button --}}
                            <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    Click to explore
                                </span>
                                <x-heroicon-m-arrow-right class="w-5 h-5 text-{{ $category['color'] }}-600 dark:text-{{ $category['color'] }}-400" />
                            </div>
                        </div>
                    </x-filament::card>
                @endforeach
            </div>

            {{-- Quick Stats --}}
            <x-filament::card>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ \App\Services\KPI\KPIRegistry::getTotalKPICount() }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Total KPIs</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ \App\Services\KPI\KPIRegistry::getActiveKPICount() }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Active</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ \App\Services\KPI\KPIRegistry::getTotalKPICount() - \App\Services\KPI\KPIRegistry::getActiveKPICount() }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Planned</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">6</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Categories</div>
                    </div>
                </div>
            </x-filament::card>
        </div>
    @endif

    {{-- Category View: List of KPIs in a category --}}
    @if($viewMode === 'category')
        <div class="space-y-6">
            {{-- Back Button and Header --}}
            <div class="flex items-center space-x-4">
                <button wire:click="backToHub" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                </button>
                <div>
                    @php
                        $categoryInfo = $this->getCategories()[$selectedCategory] ?? null;
                    @endphp
                    @if($categoryInfo)
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $categoryInfo['name'] }}</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $categoryInfo['description'] }}</p>
                    @endif
                </div>
            </div>

            {{-- KPI Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($this->getCategoryKPIs() as $kpi)
                    @php
                        $isActive = $kpi['status'] === 'active';
                        $clickAction = $isActive ? "viewKPI('{$selectedCategory}', '{$kpi['id']}')" : '';
                    @endphp
                    <div
                        class="{{ $isActive ? 'cursor-pointer' : '' }}"
                        {!! $isActive ? "wire:click=\"{$clickAction}\"" : '' !!}
                    >
                    <x-filament::card
                        class="hover:shadow-md transition-shadow {{ $isActive ? '' : 'opacity-60' }}"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center space-x-2">
                                    <x-dynamic-component :component="$kpi['icon']" class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $kpi['name'] }}</h3>
                                </div>
                                @if($kpi['status'] === 'active')
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 rounded">
                                        Active
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400 rounded">
                                        Planned
                                    </span>
                                @endif

                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $kpi['description'] }}</p>
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Tier {{ $kpi['tier'] }}</span>
                                @if($kpi['status'] === 'active')
                                    <span class="text-primary-600 dark:text-primary-400">Click to view →</span>
                                @else
                                    <span>Coming soon</span>
                                @endif
                            </div>
                        </div>
                    </x-filament::card>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- KPI Detail View: Shows the actual KPI with dual-mode functionality --}}
    @if($viewMode === 'kpi-detail')
        @php
            $kpiInfo = $this->getSelectedKPI();
        @endphp

        <div class="space-y-6">
            {{-- Back Button and Header --}}
            <div class="flex items-center space-x-4">
                <button wire:click="backToCategory" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                </button>
                <div class="flex-1">
                    @if($kpiInfo)
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $kpiInfo['name'] }}</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $kpiInfo['description'] }}</p>
                    @endif
                </div>
            </div>

            {{-- Mode Toggle Tabs --}}
            <div class="flex space-x-1 rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
                <button
                    wire:click="setKPIMode('dashboard')"
                    class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors
                        {{ $kpiMode === 'dashboard' ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}">
                    Dashboard Mode
                </button>
                <button
                    wire:click="setKPIMode('analytics')"
                    class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors
                        {{ $kpiMode === 'analytics' ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}">
                    Analytics Mode
                </button>
            </div>

            {{-- Analytics Filters (only show in analytics mode) --}}
            @if($kpiMode === 'analytics')
                <x-filament::card>
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold">Analytics Filters</h3>
                        {{ $this->form }}
                    </div>
                </x-filament::card>
            @endif

            {{-- Machine Status KPI Content (Only active KPI for now) --}}
            @if($selectedKPI === 'machine_status')
                <x-filament::card>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold">Machine Status</h2>
                            @if($kpiMode === 'dashboard')
                                <button
                                    wire:click="refreshData"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                    wire:loading.attr="disabled"
                                >
                                    <x-heroicon-o-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refreshData" />
                                    <span>Refresh</span>
                                </button>
                            @endif
                        </div>

                        @php
                            $data = $this->getMachineStatusData();
                        @endphp

                        {{-- Dashboard Mode Content --}}
                        @if($kpiMode === 'dashboard')
                            {{-- Search and Filter Bar --}}
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6 border border-gray-200 dark:border-gray-700">
                                <div class="flex flex-col md:flex-row gap-4 items-end">
                                    <div class="flex-1">
                                        <label for="search" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Search Machines
                                        </label>
                                        <input
                                            wire:model.live.debounce.500ms="searchQuery"
                                            type="text"
                                            id="search"
                                            placeholder="Search by machine, WO, part, operator, asset ID..."
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label for="statusFilter" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Filter by Status
                                        </label>
                                        <select
                                            wire:model.live="statusFilter"
                                            id="statusFilter"
                                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                                        >
                                            <option value="all">All Statuses</option>
                                            <option value="running">Running Only</option>
                                            <option value="hold">Hold Only</option>
                                            <option value="setup">Setup Only</option>
                                            <option value="scheduled">Scheduled Only</option>
                                            <option value="idle">Idle Only</option>
                                        </select>
                                    </div>
                                    @if($searchQuery || $statusFilter !== 'all')
                                        <button
                                            wire:click="resetFilters"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                                        >
                                            Reset Filters
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @php
                                $donutChartId = 'machine-status-donut';
                                $donutSeries = [
                                    (int) ($data['status_groups']['running']['count'] ?? 0),
                                    (int) ($data['status_groups']['setup']['count'] ?? 0),
                                    (int) ($data['status_groups']['hold']['count'] ?? 0),
                                    (int) ($data['status_groups']['scheduled']['count'] ?? 0),
                                    (int) ($data['status_groups']['idle']['count'] ?? 0),
                                ];
                                $donutLabels = ['Running', 'Setup', 'Hold', 'Scheduled', 'Idle'];
                                $totalMachines = array_sum($donutSeries);
                            @endphp

                            {{-- Debug Info - Remove after testing --}}
                            <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <p class="text-sm font-semibold text-blue-900 dark:text-blue-100">Debug Info:</p>
                                <p class="text-xs text-blue-800 dark:text-blue-200">Chart Data: {{ json_encode($donutSeries) }}</p>
                                <p class="text-xs text-blue-800 dark:text-blue-200">Total Machines: {{ $totalMachines }}</p>
                                <p class="text-xs text-blue-800 dark:text-blue-200">Canvas ID: {{ $donutChartId }}</p>
                            </div>

                            <x-filament::card class="mb-6">
                                <button
                                    type="button"
                                    wire:click="toggleStatusChart"
                                    aria-expanded="{{ $statusChartExpanded ? 'true' : 'false' }}"
                                    aria-controls="machine-status-chart-panel"
                                    class="w-full flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700 text-left hover:text-gray-900 dark:hover:text-white transition-colors"
                                >
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Machine Status Breakdown</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Visual distribution of live machine states</p>
                                    </div>
                                    <x-dynamic-component
                                        :component="$statusChartExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                        class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                    />
                                </button>

                                @if($statusChartExpanded)
                                    <div id="machine-status-chart-panel" class="mt-6">
                                        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded p-6" style="height: 450px;"
                                             wire:key="chart-container-{{ md5(json_encode($donutSeries)) }}"
                                             x-data="{
                                                 chartData: @js($donutSeries),
                                                 chartLabels: @js($donutLabels),
                                                 init() {
                                                     console.log('🎬 Chart Alpine initialized with:', this.chartData, this.chartLabels);
                                                     this.$nextTick(() => {
                                                         this.updateChart();
                                                     });
                                                 },
                                                 updateChart() {
                                                     console.log('📊 updateChart called with:', this.chartData, this.chartLabels);
                                                     if (window.updateMachineStatusChartData) {
                                                         window.updateMachineStatusChartData(this.chartData, this.chartLabels);
                                                     }
                                                 }
                                             }">
                                            <div wire:ignore.self style="height: 100%; width: 100%;">
                                                <canvas id="{{ $donutChartId }}" wire:ignore></canvas>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </x-filament::card>

                            {{-- Machine Status Matrix --}}
                            <x-filament::card class="mb-6">
                                <button
                                    type="button"
                                    wire:click="toggleMatrixSection"
                                    aria-expanded="{{ $matrixExpanded ? 'true' : 'false' }}"
                                    aria-controls="machine-status-matrix-panel"
                                    class="w-full flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700 text-left hover:text-gray-900 dark:hover:text-white transition-colors"
                                >
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Machine Status Matrix</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Color-coded overview of all machines</p>
                                    </div>
                                    <x-dynamic-component
                                        :component="$matrixExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                        class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                    />
                                </button>
                                <div id="machine-status-matrix-panel" class="{{ $matrixExpanded ? 'mt-6' : '' }}">
                                    @if($matrixExpanded)
                                        @php
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

                                            $matrixMachines = collect($data['status_groups'] ?? [])->flatMap(function ($group, $statusKey) {
                                                return collect($group['machines'] ?? [])->map(function ($machine) use ($statusKey) {
                                                    $machine['__status'] = $statusKey;

                                                    return $machine;
                                                });
                                            })->values();
                                        @endphp

                                        <div
                                            wire:key="machine-status-matrix-{{ md5(json_encode([$searchQuery, $statusFilter, array_map(fn ($group) => $group['count'] ?? 0, $data['status_groups'] ?? [])])) }}"
                                        >
                                            @if($matrixMachines->isEmpty())
                                                <div class="py-8 text-center border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">No machines match the current filters.</p>
                                                </div>
                                            @else
                                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                                    @foreach($matrixMachines as $machine)
                                                        @php
                                                            $statusKey = $machine['__status'] ?? 'idle';
                                                            $styles = $statusStyles[$statusKey] ?? [
                                                                'card' => 'bg-gray-50 dark:bg-gray-900/20 border-2 border-gray-200 dark:border-gray-700',
                                                                'icon' => 'heroicon-o-question-mark-circle',
                                                                'icon_class' => 'text-gray-600 dark:text-gray-400',
                                                                'wo_class' => 'text-gray-600 dark:text-gray-300',
                                                            ];
                                                            $woNumber = $machine['wo_number']
                                                                ?? $machine['primary_wo_number']
                                                                ?? $machine['current_work_order']
                                                                ?? $machine['active_work_order']
                                                                ?? null;
                                                            $woDisplay = $woNumber ? \Illuminate\Support\Str::limit($woNumber, 14) : null;
                                                        @endphp
                                                        <a
                                                            href="{{ \App\Filament\Admin\Resources\MachineResource::getUrl('view', ['record' => $machine['id']]) }}"
                                                            wire:navigate
                                                            wire:key="machine-card-{{ $statusKey }}-{{ $machine['id'] }}"
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
                                </div>
                            </x-filament::card>

                            {{-- Summary Cards at Top --}}
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                                <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-green-900 dark:text-green-100 uppercase">Running</div>
                                            <div class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1">
                                                {{ $data['status_groups']['running']['count'] ?? 0 }}
                                            </div>
                                        </div>
                                        <x-heroicon-o-play-circle class="w-10 h-10 text-green-600 dark:text-green-400 opacity-50" />
                                    </div>
                                </div>

                                <div class="bg-yellow-50 dark:bg-yellow-900/20 border-2 border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-yellow-900 dark:text-yellow-100 uppercase">Hold</div>
                                            <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">
                                                {{ $data['status_groups']['hold']['count'] ?? 0 }}
                                            </div>
                                        </div>
                                        <x-heroicon-o-pause-circle class="w-10 h-10 text-yellow-600 dark:text-yellow-400 opacity-50" />
                                    </div>
                                </div>

                                <div class="bg-violet-50 dark:bg-violet-900/20 border-2 border-violet-200 dark:border-violet-800 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-violet-900 dark:text-violet-100 uppercase">Setup</div>
                                            <div class="text-3xl font-bold text-violet-600 dark:text-violet-400 mt-1">
                                                {{ $data['status_groups']['setup']['count'] ?? 0 }}
                                            </div>
                                        </div>
                                        <x-heroicon-o-wrench-screwdriver class="w-10 h-10 text-violet-600 dark:text-violet-400 opacity-50" />
                                    </div>
                                </div>

                                <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-blue-900 dark:text-blue-100 uppercase">Scheduled</div>
                                            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                                                {{ $data['status_groups']['scheduled']['count'] ?? 0 }}
                                            </div>
                                        </div>
                                        <x-heroicon-o-clock class="w-10 h-10 text-blue-600 dark:text-blue-400 opacity-50" />
                                    </div>
                                </div>

                                <div class="bg-gray-50 dark:bg-gray-900/20 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-gray-900 dark:text-gray-100 uppercase">Idle</div>
                                            <div class="text-3xl font-bold text-gray-600 dark:text-gray-400 mt-1">
                                                {{ $data['status_groups']['idle']['count'] ?? 0 }}
                                            </div>
                                        </div>
                                        <x-heroicon-o-minus-circle class="w-10 h-10 text-gray-600 dark:text-gray-400 opacity-50" />
                                    </div>
                                </div>
                            </div>

                            {{-- Compact Data Tables by Status --}}
                            <div class="space-y-6">
                                {{-- Running Machines Table --}}
                                @if(!empty($data['status_groups']['running']['machines']))
                                    @php
                                        $runningPagination = $this->getPaginatedMachines($data['status_groups']['running']['machines'], 'running');
                                    @endphp
                                    <div class="border border-green-200 dark:border-green-800 rounded-lg overflow-hidden">
                                        <button
                                            wire:click="toggleSection('running')"
                                            class="w-full bg-green-50 dark:bg-green-900/20 px-4 py-3 border-b border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-sm font-semibold text-green-900 dark:text-green-100 flex items-center gap-2">
                                                    <x-heroicon-o-play-circle class="w-5 h-5" />
                                                    Running Machines ({{ count($data['status_groups']['running']['machines']) }})
                                                </h3>
                                                <x-dynamic-component
                                                    :component="$runningExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                    class="w-5 h-5 text-green-900 dark:text-green-100" />
                                            </div>
                                        </button>
                                        @if($runningExpanded)
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                                        <tr>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Work Order</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Progress</th>
                                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Est. Complete</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                        @foreach($runningPagination['data'] as $machine)
                                                            <tr class="hover:bg-green-50 dark:hover:bg-green-900/10">
                                                                <td class="px-4 py-3">
                                                                    <a href="{{ \App\Filament\Admin\Resources\MachineResource::getUrl('view', ['record' => $machine['id']]) }}"
                                                                       class="font-medium text-sm text-primary-600 dark:text-primary-400 hover:underline"
                                                                       wire:navigate>
                                                                        {{ $machine['name'] }}
                                                                    </a>
                                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $machine['asset_id'] ?? 'N/A' }}</div>
                                                                </td>
                                                                <td class="px-4 py-3">
                                                                    <a href="{{ \App\Filament\Admin\Resources\WorkOrderResource::getUrl('view', ['record' => $machine['wo_id']]) }}"
                                                                       class="text-sm font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                                                       wire:navigate>
                                                                        {{ $machine['wo_number'] }}
                                                                    </a>
                                                                </td>
                                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                                    {{ $machine['part_number'] }}
                                                                </td>
                                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                                    {{ $machine['operator'] }}
                                                                </td>
                                                                <td class="px-4 py-3">
                                                                    <div class="flex items-center gap-2">
                                                                        <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 max-w-24">
                                                                            <div class="bg-green-600 dark:bg-green-500 h-2 rounded-full" style="width: {{ $machine['progress_percentage'] }}%"></div>
                                                                        </div>
                                                                        <span class="text-xs font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                                                            {{ $machine['progress_percentage'] }}%
                                                                        </span>
                                                                    </div>
                                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                        {{ $machine['qty_produced'] }} / {{ $machine['qty_target'] }}
                                                                    </div>
                                                                </td>
                                                                <td class="px-4 py-3 text-sm text-right text-green-700 dark:text-green-300 font-medium">
                                                                    {{ $machine['estimated_completion'] }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <x-machine-table-pagination :pagination="$runningPagination" status="running" />
                                        @endif
                                    </div>
                                @endif

                                {{-- Hold Machines Table --}}
                                @if(!empty($data['status_groups']['hold']['machines']))
                                    @php
                                        $holdPagination = $this->getPaginatedMachines($data['status_groups']['hold']['machines'], 'hold');
                                    @endphp
                                    <div class="border border-yellow-200 dark:border-yellow-800 rounded-lg overflow-hidden">
                                        <button
                                            wire:click="toggleSection('hold')"
                                            class="w-full bg-yellow-50 dark:bg-yellow-900/20 px-4 py-3 border-b border-yellow-200 dark:border-yellow-800 hover:bg-yellow-100 dark:hover:bg-yellow-900/30 transition-colors">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-sm font-semibold text-yellow-900 dark:text-yellow-100 flex items-center gap-2">
                                                    <x-heroicon-o-pause-circle class="w-5 h-5" />
                                                    Machines on Hold ({{ count($data['status_groups']['hold']['machines']) }}) - Requires Attention
                                                </h3>
                                                <x-dynamic-component
                                                    :component="$holdExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                    class="w-5 h-5 text-yellow-900 dark:text-yellow-100" />
                                            </div>
                                        </button>
                                        @if($holdExpanded)
                                            <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Work Order</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Hold Duration</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Hold Reason</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($holdPagination['data'] as $machine)
                                                        <tr class="hover:bg-yellow-50 dark:hover:bg-yellow-900/10">
                                                            <td class="px-4 py-3">
                                                                <a href="{{ \App\Filament\Admin\Resources\MachineResource::getUrl('view', ['record' => $machine['id']]) }}"
                                                                   class="font-medium text-sm text-primary-600 dark:text-primary-400 hover:underline"
                                                                   wire:navigate>
                                                                    {{ $machine['name'] }}
                                                                </a>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $machine['asset_id'] ?? 'N/A' }}</div>
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <a href="{{ \App\Filament\Admin\Resources\WorkOrderResource::getUrl('view', ['record' => $machine['primary_wo_id']]) }}"
                                                                   class="text-sm font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                                                   wire:navigate>
                                                                    {{ $machine['primary_wo_number'] }}
                                                                </a>
                                                                @if($machine['hold_wo_count'] > 1)
                                                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded mt-1">
                                                                        +{{ $machine['hold_wo_count'] - 1 }} more
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                                {{ $machine['part_number'] }}
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                                {{ $machine['operator'] }}
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded">
                                                                    <x-heroicon-o-clock class="w-3 h-3 mr-1" />
                                                                    {{ $machine['hold_duration'] ?? 'Unknown' }}
                                                                </span>
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded">
                                                                    {{ $machine['hold_reason'] }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            </div>
                                            <x-machine-table-pagination :pagination="$holdPagination" status="hold" />
                                        @endif
                                    </div>
                                @endif

                                {{-- Setup Machines Table --}}
                                @if(!empty($data['status_groups']['setup']['machines']))
                                    @php
                                        $setupPagination = $this->getPaginatedMachines($data['status_groups']['setup']['machines'], 'setup');
                                    @endphp
                                    <div class="border border-violet-200 dark:border-violet-800 rounded-lg overflow-hidden">
                                        <button
                                            wire:click="toggleSection('setup')"
                                            class="w-full bg-violet-50 dark:bg-violet-900/20 px-4 py-3 border-b border-violet-200 dark:border-violet-800 hover:bg-violet-100 dark:hover:bg-violet-900/30 transition-colors">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-sm font-semibold text-violet-900 dark:text-violet-100 flex items-center gap-2">
                                                    <x-heroicon-o-wrench-screwdriver class="w-5 h-5" />
                                                    Setup Machines ({{ count($data['status_groups']['setup']['machines']) }})
                                                </h3>
                                                <x-dynamic-component
                                                    :component="$setupExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                    class="w-5 h-5 text-violet-900 dark:text-violet-100" />
                                            </div>
                                        </button>
                                        @if($setupExpanded)
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                                        <tr>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Setup Work Order</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Setup Duration</th>
                                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scheduled Start</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                        @foreach($setupPagination['data'] as $machine)
                                                            <tr class="hover:bg-violet-50 dark:hover:bg-violet-900/10">
                                                                <td class="px-4 py-3">
                                                                    <a href="{{ \App\Filament\Admin\Resources\MachineResource::getUrl('view', ['record' => $machine['id']]) }}"
                                                                       class="font-medium text-sm text-primary-600 dark:text-primary-400 hover:underline"
                                                                       wire:navigate>
                                                                        {{ $machine['name'] }}
                                                                    </a>
                                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $machine['asset_id'] ?? 'N/A' }}</div>
                                                                </td>
                                                                <td class="px-4 py-3">
                                                                    <a href="{{ \App\Filament\Admin\Resources\WorkOrderResource::getUrl('view', ['record' => $machine['primary_wo_id']]) }}"
                                                                       class="text-sm font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                                                       wire:navigate>
                                                                        {{ $machine['primary_wo_number'] }}
                                                                    </a>
                                                                    @if(($machine['setup_wo_count'] ?? 0) > 1)
                                                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200 rounded mt-1">
                                                                            +{{ ($machine['setup_wo_count'] ?? 0) - 1 }} more
                                                                        </span>
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                                    {{ $machine['part_number'] }}
                                                                </td>
                                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                                    {{ $machine['operator'] }}
                                                                </td>
                                                                <td class="px-4 py-3">
                                                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200 rounded">
                                                                        <x-heroicon-o-clock class="w-3 h-3 mr-1" />
                                                                        {{ $machine['setup_duration'] ?? 'Unknown' }}
                                                                    </span>
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                                                    {{ $machine['scheduled_start'] }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <x-machine-table-pagination :pagination="$setupPagination" status="setup" />
                                        @endif
                                    </div>
                                @endif

                                {{-- Scheduled Machines Table --}}
                                @if(!empty($data['status_groups']['scheduled']['machines']))
                                    @php
                                        $scheduledPagination = $this->getPaginatedMachines($data['status_groups']['scheduled']['machines'], 'scheduled');
                                    @endphp
                                    <div class="border border-blue-200 dark:border-blue-800 rounded-lg overflow-hidden">
                                        <button
                                            wire:click="toggleSection('scheduled')"
                                            class="w-full bg-blue-50 dark:bg-blue-900/20 px-4 py-3 border-b border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100 flex items-center gap-2">
                                                    <x-heroicon-o-clock class="w-5 h-5" />
                                                    Scheduled Machines ({{ count($data['status_groups']['scheduled']['machines']) }})
                                                </h3>
                                                <x-dynamic-component
                                                    :component="$scheduledExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                    class="w-5 h-5 text-blue-900 dark:text-blue-100" />
                                            </div>
                                        </button>
                                        @if($scheduledExpanded)
                                            <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Next Work Order</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scheduled Start</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($scheduledPagination['data'] as $machine)
                                                        <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/10">
                                                            <td class="px-4 py-3">
                                                                <a href="{{ \App\Filament\Admin\Resources\MachineResource::getUrl('view', ['record' => $machine['id']]) }}"
                                                                   class="font-medium text-sm text-primary-600 dark:text-primary-400 hover:underline"
                                                                   wire:navigate>
                                                                    {{ $machine['name'] }}
                                                                </a>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $machine['asset_id'] ?? 'N/A' }}</div>
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <a href="{{ \App\Filament\Admin\Resources\WorkOrderResource::getUrl('view', ['record' => $machine['next_wo_id']]) }}"
                                                                   class="text-sm font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                                                   wire:navigate>
                                                                    {{ $machine['next_wo_number'] }}
                                                                </a>
                                                                @if($machine['assigned_wo_count'] > 1)
                                                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded mt-1">
                                                                        +{{ $machine['assigned_wo_count'] - 1 }} queued
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                                {{ $machine['part_number'] }}
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                                {{ $machine['operator'] }}
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-right text-blue-700 dark:text-blue-300 font-medium">
                                                                {{ $machine['scheduled_start'] }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            </div>
                                            <x-machine-table-pagination :pagination="$scheduledPagination" status="scheduled" />
                                        @endif
                                    </div>
                                @endif

                                {{-- Idle Machines - Collapsed by Default --}}
                                @if(!empty($data['status_groups']['idle']['machines']))
                                    @php
                                        $idlePagination = $this->getPaginatedMachines($data['status_groups']['idle']['machines'], 'idle');
                                    @endphp
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                        <button
                                            wire:click="toggleSection('idle')"
                                            class="w-full bg-gray-50 dark:bg-gray-900/20 px-4 py-3 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800/50 transition-colors">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                                    <x-heroicon-o-minus-circle class="w-5 h-5" />
                                                    Idle Machines ({{ count($data['status_groups']['idle']['machines']) }})
                                                </h3>
                                                <x-dynamic-component
                                                    :component="$idleExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                    class="w-5 h-5 text-gray-900 dark:text-gray-100" />
                                            </div>
                                        </button>
                                        @if($idleExpanded)
                                            <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Asset ID</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($idlePagination['data'] as $machine)
                                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                            <td class="px-4 py-3">
                                                                <a href="{{ \App\Filament\Admin\Resources\MachineResource::getUrl('view', ['record' => $machine['id']]) }}"
                                                                   class="font-medium text-sm text-primary-600 dark:text-primary-400 hover:underline"
                                                                   wire:navigate>
                                                                    {{ $machine['name'] }}
                                                                </a>
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                                {{ $machine['asset_id'] ?? 'N/A' }}
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                                No work orders assigned
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            </div>
                                            <x-machine-table-pagination :pagination="$idlePagination" status="idle" />
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
                                <span>Total Machines: {{ $data['total_machines'] ?? 0 }}</span>
                                <span class="flex items-center gap-2">
                                    <x-heroicon-o-arrow-path class="w-3 h-3 animate-spin" wire:loading wire:target="getMachineStatusData" />
                                    <span>Last Updated: {{ \Carbon\Carbon::parse($data['updated_at'] ?? now())->diffForHumans() }}</span>
                                </span>
                            </div>
                        @endif

                        {{-- Analytics Mode Content --}}
                        @if($kpiMode === 'analytics')
                            @include('filament.admin.pages.machine-status-analytics')
                        @endif
                    </div>
                </x-filament::card>
            @endif

            {{-- OLD ANALYTICS CONTENT - TO BE REMOVED --}}
            @if(false && $kpiMode === 'analytics')
                            <div wire:key="analytics-data-{{ md5(json_encode($this->form->getState())) }}">
                            @if(isset($data['primary_period']))
                                {{-- Period Header --}}
                                <div class="mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $data['primary_period']['label'] ?? 'Analytics Period' }}
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $data['primary_period']['start_date'] }} to {{ $data['primary_period']['end_date'] }}
                                    </p>
                                </div>

                                {{-- Charts Section --}}
                                @if(count($data['primary_period']['daily_breakdown']) > 1)
                                    @php
                                        // Prepare chart data for Utilization Trends
                                        $dates = array_map(fn($day) => $day['date'], $data['primary_period']['daily_breakdown']);
                                        $scheduledUtil = array_map(fn($day) => round($day['avg_utilization_rate'] ?? 0, 1), $data['primary_period']['daily_breakdown']);
                                        $activeUtil = array_map(fn($day) => round($day['avg_active_utilization_rate'] ?? 0, 1), $data['primary_period']['daily_breakdown']);

                                        $utilizationSeries = [
                                            [
                                                'name' => 'Scheduled Utilization',
                                                'data' => $scheduledUtil,
                                            ],
                                            [
                                                'name' => 'Active Utilization',
                                                'data' => $activeUtil,
                                            ],
                                        ];

                                        // Add comparison data if enabled
                                        if(isset($data['comparison_period'])) {
                                            $compScheduledUtil = array_map(fn($day) => round($day['avg_utilization_rate'] ?? 0, 1), $data['comparison_period']['daily_breakdown']);
                                            $compActiveUtil = array_map(fn($day) => round($day['avg_active_utilization_rate'] ?? 0, 1), $data['comparison_period']['daily_breakdown']);

                                            $utilizationSeries[] = [
                                                'name' => 'Scheduled Utilization (Previous)',
                                                'data' => $compScheduledUtil,
                                            ];
                                            $utilizationSeries[] = [
                                                'name' => 'Active Utilization (Previous)',
                                                'data' => $compActiveUtil,
                                            ];
                                        }

                                        // Prepare chart data for Production & Time Metrics
                                        $unitsProduced = array_map(fn($day) => $day['units_produced'] ?? 0, $data['primary_period']['daily_breakdown']);
                                        $uptimeHours = array_map(fn($day) => round($day['uptime_hours'] ?? 0, 1), $data['primary_period']['daily_breakdown']);
                                        $downtimeHours = array_map(fn($day) => round($day['downtime_hours'] ?? 0, 1), $data['primary_period']['daily_breakdown']);
                                    @endphp

                                    <div class="space-y-6 mb-6" x-data="{ chartsLoaded: false }" x-intersect="chartsLoaded = true">
                                        {{-- Utilization Trends Chart --}}
                                        <x-filament::card>
                                            <div class="space-y-4">
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                    📊 Utilization Trends
                                                </h3>
                                                <div x-show="chartsLoaded" x-cloak>
                                                    <div
                                                        x-data="window.createUtilizationChart(@js($utilizationSeries), @js($dates))"
                                                        x-ref="utilizationChart"
                                                    ></div>
                                                </div>
                                                <div x-show="!chartsLoaded" class="h-[350px] flex items-center justify-center">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400 animate-pulse">Loading chart...</div>
                                                </div>
                                            </div>
                                        </x-filament::card>

                                        {{-- Production & Time Metrics Chart --}}
                                        <x-filament::card>
                                            <div class="space-y-4">
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                    📊 Production & Time Metrics
                                                </h3>
                                                <div x-show="chartsLoaded" x-cloak>
                                                    <div
                                                        x-data="window.createProductionChart(@js($unitsProduced), @js($uptimeHours), @js($downtimeHours), @js($dates))"
                                                    ></div>
                                                </div>
                                                <div x-show="!chartsLoaded" class="h-[350px] flex items-center justify-center">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400 animate-pulse">Loading chart...</div>
                                                </div>
                                            </div>
                                        </x-filament::card>
                                    </div>
                                @endif

                                {{-- Summary Cards --}}
                                <div class="space-y-6 mb-6">
                                    {{-- Utilization Metrics Row --}}
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        {{-- Scheduled Utilization Card (Factory View) --}}
                                        <x-filament::card>
                                            <div class="space-y-3">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                            Scheduled Utilization
                                                        </h3>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            Factory View - Includes all work time (with holds)
                                                        </p>
                                                    </div>
                                                    <x-heroicon-o-building-office-2 class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                                                </div>
                                                <div class="flex items-baseline gap-2">
                                                    <div class="text-4xl font-bold text-blue-600 dark:text-blue-400">
                                                        {{ number_format($data['primary_period']['summary']['avg_scheduled_utilization'] ?? 0, 1) }}%
                                                    </div>
                                                    @if(isset($data['comparison_analysis']['scheduled_utilization']))
                                                        @php
                                                            $comparison = $data['comparison_analysis']['scheduled_utilization'];
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
                                                    Based on work order scheduled time vs available factory hours
                                                </div>
                                            </div>
                                        </x-filament::card>

                                        {{-- Active Utilization Card (Operator View) --}}
                                        <x-filament::card>
                                            <div class="space-y-3">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                            Active Utilization
                                                        </h3>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            Operator View - Excludes hold periods
                                                        </p>
                                                    </div>
                                                    <x-heroicon-o-user class="w-8 h-8 text-green-600 dark:text-green-400" />
                                                </div>
                                                <div class="flex items-baseline gap-2">
                                                    <div class="text-4xl font-bold text-green-600 dark:text-green-400">
                                                        {{ number_format($data['primary_period']['summary']['avg_active_utilization'] ?? 0, 1) }}%
                                                    </div>
                                                    @if(isset($data['comparison_analysis']['active_utilization']))
                                                        @php
                                                            $comparison = $data['comparison_analysis']['active_utilization'];
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
                                                    Based on actual active production time vs available factory hours
                                                </div>
                                            </div>
                                        </x-filament::card>
                                    </div>

                                    {{-- Other KPI Metrics Row --}}
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                                            <div class="text-xs text-gray-600 dark:text-gray-400">Total Uptime</div>
                                            <div class="flex items-baseline gap-2">
                                                <div class="text-2xl font-bold">{{ number_format($data['primary_period']['summary']['total_uptime_hours'] ?? 0, 1) }}h</div>
                                                @if(isset($data['comparison_analysis']['uptime_hours']))
                                                    @php
                                                        $comparison = $data['comparison_analysis']['uptime_hours'];
                                                        $isPositive = $comparison['difference'] > 0;
                                                    @endphp
                                                    <div class="flex items-center gap-0.5 text-xs font-medium {{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
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
                                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                                            <div class="text-xs text-gray-600 dark:text-gray-400">Total Downtime</div>
                                            <div class="flex items-baseline gap-2">
                                                <div class="text-2xl font-bold">{{ number_format($data['primary_period']['summary']['total_downtime_hours'] ?? 0, 1) }}h</div>
                                                @if(isset($data['comparison_analysis']['downtime_hours']))
                                                    @php
                                                        $comparison = $data['comparison_analysis']['downtime_hours'];
                                                        // For downtime, less is better, so invert the color logic
                                                        $isPositive = $comparison['difference'] < 0;
                                                    @endphp
                                                    <div class="flex items-center gap-0.5 text-xs font-medium {{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
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
                                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                                            <div class="text-xs text-gray-600 dark:text-gray-400">Units Produced</div>
                                            <div class="flex items-baseline gap-2">
                                                <div class="text-2xl font-bold">{{ number_format($data['primary_period']['summary']['total_units_produced'] ?? 0) }}</div>
                                                @if(isset($data['comparison_analysis']['units_produced']))
                                                    @php
                                                        $comparison = $data['comparison_analysis']['units_produced'];
                                                        $isPositive = $comparison['difference'] > 0;
                                                    @endphp
                                                    <div class="flex items-center gap-0.5 text-xs font-medium {{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
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
                                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                                            <div class="text-xs text-gray-600 dark:text-gray-400">Work Orders</div>
                                            <div class="flex items-baseline gap-2">
                                                <div class="text-2xl font-bold">{{ $data['primary_period']['summary']['total_work_orders'] ?? 0 }}</div>
                                                @if(isset($data['comparison_analysis']['work_orders']))
                                                    @php
                                                        $comparison = $data['comparison_analysis']['work_orders'];
                                                        $isPositive = $comparison['difference'] > 0;
                                                    @endphp
                                                    <div class="flex items-center gap-0.5 text-xs font-medium {{ $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
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
                                    </div>
                                </div>

                                {{-- Comparison Summary Section --}}
                                @if(isset($data['comparison_period']) && isset($data['comparison_analysis']))
                                    <x-filament::card class="mb-6">
                                        <div class="space-y-4">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                    Comparison Analysis
                                                </h3>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    vs {{ $data['comparison_period']['label'] }} ({{ $data['comparison_period']['start_date'] }} - {{ $data['comparison_period']['end_date'] }})
                                                </span>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                                {{-- Scheduled Utilization Comparison --}}
                                                @php $comp = $data['comparison_analysis']['scheduled_utilization']; @endphp
                                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                                    <div class="text-xs font-medium text-blue-900 dark:text-blue-100 uppercase mb-2">Scheduled Utilization</div>
                                                    <div class="flex items-center justify-between">
                                                        <div>
                                                            <div class="text-lg font-bold text-gray-900 dark:text-white">
                                                                {{ number_format($comp['current'], 1) }}% → {{ number_format($comp['previous'], 1) }}%
                                                            </div>
                                                            <div class="text-sm {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                                {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 1) }}% ({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ $comp['percentage_change'] }}%)
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center">
                                                            @if($comp['trend'] === 'up')
                                                                <x-heroicon-s-arrow-trending-up class="w-8 h-8 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                                                            @else
                                                                <x-heroicon-s-arrow-trending-down class="w-8 h-8 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Active Utilization Comparison --}}
                                                @php $comp = $data['comparison_analysis']['active_utilization']; @endphp
                                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                                    <div class="text-xs font-medium text-green-900 dark:text-green-100 uppercase mb-2">Active Utilization</div>
                                                    <div class="flex items-center justify-between">
                                                        <div>
                                                            <div class="text-lg font-bold text-gray-900 dark:text-white">
                                                                {{ number_format($comp['current'], 1) }}% → {{ number_format($comp['previous'], 1) }}%
                                                            </div>
                                                            <div class="text-sm {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                                {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference'], 1) }}% ({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ $comp['percentage_change'] }}%)
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center">
                                                            @if($comp['trend'] === 'up')
                                                                <x-heroicon-s-arrow-trending-up class="w-8 h-8 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                                                            @else
                                                                <x-heroicon-s-arrow-trending-down class="w-8 h-8 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Units Produced Comparison --}}
                                                @php $comp = $data['comparison_analysis']['units_produced']; @endphp
                                                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                                                    <div class="text-xs font-medium text-purple-900 dark:text-purple-100 uppercase mb-2">Units Produced</div>
                                                    <div class="flex items-center justify-between">
                                                        <div>
                                                            <div class="text-lg font-bold text-gray-900 dark:text-white">
                                                                {{ number_format($comp['current']) }} → {{ number_format($comp['previous']) }}
                                                            </div>
                                                            <div class="text-sm {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                                {{ $comp['difference'] > 0 ? '+' : '' }}{{ number_format($comp['difference']) }} ({{ $comp['percentage_change'] > 0 ? '+' : '' }}{{ $comp['percentage_change'] }}%)
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center">
                                                            @if($comp['trend'] === 'up')
                                                                <x-heroicon-s-arrow-trending-up class="w-8 h-8 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                                                            @else
                                                                <x-heroicon-s-arrow-trending-down class="w-8 h-8 {{ $comp['status'] === 'improved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </x-filament::card>
                                @endif

                                {{-- Daily Breakdown Table --}}
                                @if(!empty($data['primary_period']['daily_breakdown']))
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-800">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Date</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Utilization %</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Uptime (h)</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Downtime (h)</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Units</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Work Orders</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($data['primary_period']['daily_breakdown'] as $day)
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                        <td class="px-4 py-2 text-sm">{{ $day['date'] ?? 'N/A' }}</td>
                                                        <td class="px-4 py-2 text-sm text-right">{{ number_format($day['utilization_rate'] ?? 0, 1) }}%</td>
                                                        <td class="px-4 py-2 text-sm text-right">{{ number_format($day['uptime_hours'] ?? 0, 1) }}</td>
                                                        <td class="px-4 py-2 text-sm text-right">{{ number_format($day['downtime_hours'] ?? 0, 1) }}</td>
                                                        <td class="px-4 py-2 text-sm text-right">{{ number_format($day['units_produced'] ?? 0) }}</td>
                                                        <td class="px-4 py-2 text-sm text-right">{{ $day['work_orders_count'] ?? 0 }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        No historical data available for the selected period.
                                    </div>
                                @endif
                            @else
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    No analytics data available. Please populate the kpi_machine_daily table.
                                </div>
                            @endif
                            </div>
                        @endif
            {{-- END OF OLD ANALYTICS CONTENT --}}

            {{-- Work Order Status KPI Content --}}
            @if($selectedKPI === 'work_order_status')
                @if($kpiMode === 'analytics')
                    {{-- Analytics Mode: Historical data with comparison support --}}
                    <x-filament::card>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-bold">Work Order Status Distribution - Analytics</h2>
                            </div>

                            @php
                                $data = $this->getWorkOrderStatusData();
                            @endphp

                            @include('filament.admin.pages.work-order-status-analytics')
                        </div>
                    </x-filament::card>
                @else
                    {{-- Dashboard Mode: Real-time data for TODAY --}}
                    <x-filament::card>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-bold">Work Order Status Distribution</h2>
                                <button
                                    wire:click="refreshData"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                    wire:loading.attr="disabled"
                                >
                                    <x-heroicon-o-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refreshData" />
                                    <span>Refresh</span>
                                </button>
                            </div>

                            @php
                                $woData = $this->getWorkOrderStatusData();
                            @endphp

                        {{-- SECTION 1: PLANNED FOR TODAY --}}
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <x-heroicon-o-calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Planned for Today</h3>
                                <span class="text-sm text-gray-500 dark:text-gray-400">(Scheduled to start today)</span>
                            </div>

                            <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-xs font-medium text-blue-900 dark:text-blue-100 uppercase">Assigned</div>
                                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                                            {{ $woData['status_distribution']['assigned']['count'] ?? 0 }}
                                        </div>
                                        <div class="text-xs text-blue-700 dark:text-blue-300 mt-1">Work orders scheduled for today</div>
                                    </div>
                                    <x-heroicon-o-clipboard-document-list class="w-10 h-10 text-blue-600 dark:text-blue-400 opacity-50" />
                                </div>
                            </div>
                        </div>

                        {{-- SECTION 2: REAL-TIME EXECUTION --}}
                        <div class="mb-6">
                            <div class="flex items-center gap-2 mb-4">
                                <x-heroicon-o-bolt class="w-5 h-5 text-red-600 dark:text-red-400" />
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Real-Time Execution</h3>
                                <span class="text-sm text-gray-500 dark:text-gray-400">(Currently active or completed today)</span>
                            </div>

                            {{-- Real-Time Status Cards --}}
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 border-2 border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-yellow-900 dark:text-yellow-100 uppercase">Hold</div>
                                            <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">
                                                {{ $woData['status_distribution']['hold']['count'] ?? 0 }}
                                            </div>
                                            <div class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">Currently on hold</div>
                                        </div>
                                        <x-heroicon-o-pause-circle class="w-10 h-10 text-yellow-600 dark:text-yellow-400 opacity-50" />
                                    </div>
                                </div>

                                <div class="bg-violet-50 dark:bg-violet-900/20 border-2 border-violet-200 dark:border-violet-800 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-violet-900 dark:text-violet-100 uppercase">Setup</div>
                                            <div class="text-3xl font-bold text-violet-600 dark:text-violet-400 mt-1">
                                                {{ $woData['status_distribution']['setup']['count'] ?? 0 }}
                                            </div>
                                            <div class="text-xs text-violet-700 dark:text-violet-300 mt-1">Currently in setup</div>
                                        </div>
                                        <x-heroicon-o-wrench-screwdriver class="w-10 h-10 text-violet-600 dark:text-violet-400 opacity-50" />
                                    </div>
                                </div>

                                <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-green-900 dark:text-green-100 uppercase">Start</div>
                                            <div class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1">
                                                {{ $woData['status_distribution']['start']['count'] ?? 0 }}
                                            </div>
                                            <div class="text-xs text-green-700 dark:text-green-300 mt-1">Currently running</div>
                                        </div>
                                        <x-heroicon-o-play-circle class="w-10 h-10 text-green-600 dark:text-green-400 opacity-50" />
                                    </div>
                                </div>

                                <div class="bg-purple-50 dark:bg-purple-900/20 border-2 border-purple-200 dark:border-purple-800 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-purple-900 dark:text-purple-100 uppercase">Completed</div>
                                            <div class="text-3xl font-bold text-purple-600 dark:text-purple-400 mt-1">
                                                {{ $woData['status_distribution']['completed']['count'] ?? 0 }}
                                            </div>
                                            <div class="text-xs text-purple-700 dark:text-purple-300 mt-1">Completed today</div>
                                        </div>
                                        <x-heroicon-o-check-circle class="w-10 h-10 text-purple-600 dark:text-purple-400 opacity-50" />
                                    </div>
                                </div>

                                <div class="bg-gray-50 dark:bg-gray-900/20 border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-gray-900 dark:text-gray-100 uppercase">Closed</div>
                                            <div class="text-3xl font-bold text-gray-600 dark:text-gray-400 mt-1">
                                                {{ $woData['status_distribution']['closed']['count'] ?? 0 }}
                                            </div>
                                            <div class="text-xs text-gray-700 dark:text-gray-300 mt-1">Closed today</div>
                                        </div>
                                        <x-heroicon-o-lock-closed class="w-10 h-10 text-gray-600 dark:text-gray-400 opacity-50" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Work Order Tables by Status --}}
                        <div class="space-y-8">
                            {{-- PLANNED SECTION: Assigned Table --}}
                            <div>
                                <h4 class="text-md font-semibold text-blue-900 dark:text-blue-100 mb-3 flex items-center gap-2">
                                    <x-heroicon-o-calendar class="w-4 h-4" />
                                    Planned Work Orders
                                </h4>

                                {{-- Assigned Work Orders Table --}}
                                @if(!empty($woData['status_distribution']['assigned']['work_orders']))
                                    @php
                                        $assignedWOPagination = $this->getPaginatedWorkOrders($woData['status_distribution']['assigned']['work_orders'], 'assigned');
                                    @endphp
                                    <div class="border border-blue-200 dark:border-blue-800 rounded-lg overflow-hidden">
                                        <button
                                            wire:click="toggleWOSection('assigned')"
                                            class="w-full bg-blue-50 dark:bg-blue-900/20 px-4 py-3 border-b border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100 flex items-center gap-2">
                                                    <x-heroicon-o-clipboard-document-list class="w-5 h-5" />
                                                    Assigned Work Orders ({{ count($woData['status_distribution']['assigned']['work_orders']) }}) - Scheduled for Today
                                                </h3>
                                                <x-dynamic-component
                                                    :component="$woAssignedExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                    class="w-5 h-5 text-blue-900 dark:text-blue-100" />
                                            </div>
                                        </button>
                                        @if($woAssignedExpanded)
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                                        <tr>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO Number</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scheduled Start</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                        @foreach($assignedWOPagination['data'] as $wo)
                                                            <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/10">
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
                                                                <td class="px-4 py-3 text-sm text-right text-blue-700 dark:text-blue-300 font-medium">
                                                                    {{ $wo['scheduled_start'] }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <x-machine-table-pagination :pagination="$assignedWOPagination" status="assigned" wireMethod="gotoWOPage" />
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- REAL-TIME EXECUTION SECTION --}}
                            <div>
                                <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                                    <x-heroicon-o-bolt class="w-4 h-4" />
                                    Real-Time Execution
                                </h4>
                                <div class="space-y-6">
                                    {{-- Hold Work Orders Table --}}
                            @if(!empty($woData['status_distribution']['hold']['work_orders']))
                                @php
                                    $holdWOPagination = $this->getPaginatedWorkOrders($woData['status_distribution']['hold']['work_orders'], 'hold');
                                @endphp
                                <div class="border border-yellow-200 dark:border-yellow-800 rounded-lg overflow-hidden">
                                    <button
                                        wire:click="toggleWOSection('hold')"
                                        class="w-full bg-yellow-50 dark:bg-yellow-900/20 px-4 py-3 border-b border-yellow-200 dark:border-yellow-800 hover:bg-yellow-100 dark:hover:bg-yellow-900/30 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-sm font-semibold text-yellow-900 dark:text-yellow-100 flex items-center gap-2">
                                                <x-heroicon-o-pause-circle class="w-5 h-5" />
                                                Work Orders on Hold ({{ count($woData['status_distribution']['hold']['work_orders']) }}) - Requires Attention
                                            </h3>
                                            <x-dynamic-component
                                                :component="$woHoldExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                class="w-5 h-5 text-yellow-900 dark:text-yellow-100" />
                                        </div>
                                    </button>
                                    @if($woHoldExpanded)
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Hold Reason</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Hold Duration</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($holdWOPagination['data'] as $wo)
                                                        <tr class="hover:bg-yellow-50 dark:hover:bg-yellow-900/10">
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
                                                            <td class="px-4 py-3">
                                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded">
                                                                    {{ $wo['hold_reason'] }}
                                                                </span>
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded">
                                                                    <x-heroicon-o-clock class="w-3 h-3 mr-1" />
                                                                    {{ $wo['hold_duration'] }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <x-machine-table-pagination :pagination="$holdWOPagination" status="hold" wireMethod="gotoWOPage" />
                                    @endif
                                </div>
                            @endif

                            {{-- Setup Work Orders Table --}}
                            @if(!empty($woData['status_distribution']['setup']['work_orders']))
                                @php
                                    $setupWOPagination = $this->getPaginatedWorkOrders($woData['status_distribution']['setup']['work_orders'], 'setup');
                                @endphp
                                <div class="border border-violet-200 dark:border-violet-800 rounded-lg overflow-hidden">
                                    <button
                                        wire:click="toggleWOSection('setup')"
                                        class="w-full bg-violet-50 dark:bg-violet-900/20 px-4 py-3 border-b border-violet-200 dark:border-violet-800 hover:bg-violet-100 dark:hover:bg-violet-900/30 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-sm font-semibold text-violet-900 dark:text-violet-100 flex items-center gap-2">
                                                <x-heroicon-o-wrench-screwdriver class="w-5 h-5" />
                                                Setup Work Orders ({{ count($woData['status_distribution']['setup']['work_orders']) }})
                                            </h3>
                                            <x-dynamic-component
                                                :component="$woSetupExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                class="w-5 h-5 text-violet-900 dark:text-violet-100" />
                                        </div>
                                    </button>
                                    @if($woSetupExpanded)
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Setup Duration</th>
                                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scheduled Start</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($setupWOPagination['data'] as $wo)
                                                        <tr class="hover:bg-violet-50 dark:hover:bg-violet-900/10">
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
                                                            <td class="px-4 py-3">
                                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200 rounded">
                                                                    <x-heroicon-o-clock class="w-3 h-3 mr-1" />
                                                                    {{ $wo['setup_duration'] ?? 'Unknown' }}
                                                                </span>
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-right text-violet-700 dark:text-violet-300 font-medium">
                                                                {{ $wo['scheduled_start'] ?? 'Not scheduled' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <x-machine-table-pagination :pagination="$setupWOPagination" status="setup" wireMethod="gotoWOPage" />
                                    @endif
                                </div>
                            @endif

                            {{-- Start (Running) Work Orders Table --}}
                            @if(!empty($woData['status_distribution']['start']['work_orders']))
                                @php
                                    $startWOPagination = $this->getPaginatedWorkOrders($woData['status_distribution']['start']['work_orders'], 'start');
                                @endphp
                                <div class="border border-green-200 dark:border-green-800 rounded-lg overflow-hidden">
                                    <button
                                        wire:click="toggleWOSection('start')"
                                        class="w-full bg-green-50 dark:bg-green-900/20 px-4 py-3 border-b border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-sm font-semibold text-green-900 dark:text-green-100 flex items-center gap-2">
                                                <x-heroicon-o-play-circle class="w-5 h-5" />
                                                Running Work Orders ({{ count($woData['status_distribution']['start']['work_orders']) }})
                                            </h3>
                                            <x-dynamic-component
                                                :component="$woStartExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                class="w-5 h-5 text-green-900 dark:text-green-100" />
                                        </div>
                                    </button>
                                    @if($woStartExpanded)
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Progress</th>
                                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Est. Complete</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($startWOPagination['data'] as $wo)
                                                        <tr class="hover:bg-green-50 dark:hover:bg-green-900/10">
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
                                                            <td class="px-4 py-3">
                                                                <div class="flex items-center gap-2">
                                                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 max-w-24">
                                                                        <div class="bg-green-600 dark:bg-green-500 h-2 rounded-full" style="width: {{ $wo['progress_percentage'] }}%"></div>
                                                                    </div>
                                                                    <span class="text-xs font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                                                        {{ $wo['progress_percentage'] }}%
                                                                    </span>
                                                                </div>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                    {{ $wo['qty_produced'] }} / {{ $wo['qty_target'] }}
                                                                </div>
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-right text-green-700 dark:text-green-300 font-medium">
                                                                {{ $wo['estimated_completion'] }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <x-machine-table-pagination :pagination="$startWOPagination" status="start" wireMethod="gotoWOPage" />
                                    @endif
                                </div>
                            @endif

                            {{-- Closed Work Orders Table --}}
                            @if(!empty($woData['status_distribution']['closed']['work_orders']))
                                @php
                                    $closedWOPagination = $this->getPaginatedWorkOrders($woData['status_distribution']['closed']['work_orders'], 'closed');
                                @endphp
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <button
                                        wire:click="toggleWOSection('closed')"
                                        class="w-full bg-gray-50 dark:bg-gray-900/20 px-4 py-3 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800/50 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                                <x-heroicon-o-lock-closed class="w-5 h-5" />
                                                Closed Work Orders ({{ count($woData['status_distribution']['closed']['work_orders']) }})
                                            </h3>
                                            <x-dynamic-component
                                                :component="$woClosedExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                class="w-5 h-5 text-gray-900 dark:text-gray-100" />
                                        </div>
                                    </button>
                                    @if($woClosedExpanded)
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Completion Rate</th>
                                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Closed At</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($closedWOPagination['data'] as $wo)
                                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
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
                                                            <td class="px-4 py-3">
                                                                <div class="flex items-center gap-2">
                                                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 max-w-24">
                                                                        <div class="bg-gray-600 dark:bg-gray-500 h-2 rounded-full" style="width: {{ $wo['completion_rate'] }}%"></div>
                                                                    </div>
                                                                    <span class="text-xs font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                                                        {{ $wo['completion_rate'] }}%
                                                                    </span>
                                                                </div>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                    {{ $wo['qty_produced'] }} / {{ $wo['qty_target'] }}
                                                                </div>
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300 font-medium">
                                                                {{ \Carbon\Carbon::parse($wo['closed_at'])->format('M d, H:i') }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <x-machine-table-pagination :pagination="$closedWOPagination" status="closed" wireMethod="gotoWOPage" />
                                    @endif
                                </div>
                            @endif

                            {{-- Completed Work Orders Table --}}
                            @if(!empty($woData['status_distribution']['completed']['work_orders']))
                                @php
                                    $completedWOPagination = $this->getPaginatedWorkOrders($woData['status_distribution']['completed']['work_orders'], 'completed');
                                @endphp
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <button
                                        wire:click="toggleWOSection('completed')"
                                        class="w-full bg-gray-50 dark:bg-gray-900/20 px-4 py-3 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800/50 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                                <x-heroicon-o-check-circle class="w-5 h-5" />
                                                Completed Work Orders ({{ count($woData['status_distribution']['completed']['work_orders']) }})
                                            </h3>
                                            <x-dynamic-component
                                                :component="$woCompletedExpanded ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                                                class="w-5 h-5 text-gray-900 dark:text-gray-100" />
                                        </div>
                                    </button>
                                    @if($woCompletedExpanded)
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part Number</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Completion Rate</th>
                                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Completed At</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($completedWOPagination['data'] as $wo)
                                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
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
                                                            <td class="px-4 py-3">
                                                                <div class="flex items-center gap-2">
                                                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 max-w-24">
                                                                        <div class="bg-gray-600 dark:bg-gray-500 h-2 rounded-full" style="width: {{ $wo['completion_rate'] }}%"></div>
                                                                    </div>
                                                                    <span class="text-xs font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                                                        {{ $wo['completion_rate'] }}%
                                                                    </span>
                                                                </div>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                    {{ $wo['qty_produced'] }} / {{ $wo['qty_target'] }}
                                                                </div>
                                                            </td>
                                                            <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300 font-medium">
                                                                {{ \Carbon\Carbon::parse($wo['completed_at'])->format('M d, H:i') }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <x-machine-table-pagination :pagination="$completedWOPagination" status="completed" wireMethod="gotoWOPage" />
                                    @endif
                                </div>
                            @endif
                                </div>
                            </div>
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
                            <span>Total Work Orders: {{ $woData['total_work_orders'] ?? 0 }}</span>
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-arrow-path class="w-3 h-3 animate-spin" wire:loading wire:target="getWorkOrderStatusData" />
                                <span>Last Updated: {{ \Carbon\Carbon::parse($woData['updated_at'] ?? now())->diffForHumans() }}</span>
                            </span>
                        </div>
                    </div>
                </x-filament::card>
                @endif
            @endif

            {{-- Setup Time KPI Content --}}
            @if($selectedKPI === 'setup_time')
                <x-filament::card>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold">Setup Time per Machine</h2>
                            <button
                                wire:click="refreshData"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                wire:loading.attr="disabled"
                            >
                                <x-heroicon-o-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refreshData" />
                                <span>Refresh</span>
                            </button>
                        </div>

                        @if($kpiMode === 'dashboard')
                            @php
                                $woData = $this->getWorkOrderStatusData();
                                $setupGroup = $woData['status_distribution']['setup'] ?? ['count' => 0, 'work_orders' => []];
                                $setupWorkOrders = $setupGroup['work_orders'] ?? [];
                                $now = now();
                                $durations = [];

                                foreach ($setupWorkOrders as $woEntry) {
                                    if (! empty($woEntry['setup_since'])) {
                                        $durations[] = \Carbon\Carbon::parse($woEntry['setup_since'])->diffInMinutes($now);
                                    }
                                }

                                $totalSetups = count($setupWorkOrders);
                                $activeSetups = count($durations);
                                $totalDurationMinutes = array_sum($durations);
                                $avgDurationMinutes = $activeSetups > 0 ? round($totalDurationMinutes / $activeSetups) : 0;
                                $longestDurationMinutes = $activeSetups > 0 ? max($durations) : 0;

                                $formatMinutes = static function (int $minutes): string {
                                    if ($minutes < 60) {
                                        return $minutes . ' min';
                                    }

                                    $hours = intdiv($minutes, 60);
                                    $mins = $minutes % 60;

                                    return trim(($hours ? $hours . 'h' : '') . ($mins ? ' ' . $mins . 'm' : ''));
                                };
                            @endphp

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="bg-violet-50 dark:bg-violet-900/20 border-2 border-violet-200 dark:border-violet-800 rounded-lg p-4">
                                    <div class="text-xs font-medium text-violet-900 dark:text-violet-100 uppercase">In Setup Now</div>
                                    <div class="text-3xl font-bold text-violet-600 dark:text-violet-400 mt-1">
                                        {{ $totalSetups }}
                                    </div>
                                    <p class="text-xs text-violet-700 dark:text-violet-300 mt-1">
                                        Work orders currently preparing machines
                                    </p>
                                </div>

                                <div class="bg-violet-50 dark:bg-violet-900/20 border-2 border-violet-200 dark:border-violet-800 rounded-lg p-4">
                                    <div class="text-xs font-medium text-violet-900 dark:text-violet-100 uppercase">Average Duration</div>
                                    <div class="text-3xl font-bold text-violet-600 dark:text-violet-400 mt-1">
                                        {{ $activeSetups > 0 ? $formatMinutes($avgDurationMinutes) : 'N/A' }}
                                    </div>
                                    <p class="text-xs text-violet-700 dark:text-violet-300 mt-1">
                                        Across setups with captured start times
                                    </p>
                                </div>

                                <div class="bg-violet-50 dark:bg-violet-900/20 border-2 border-violet-200 dark:border-violet-800 rounded-lg p-4">
                                    <div class="text-xs font-medium text-violet-900 dark:text-violet-100 uppercase">Longest Active Setup</div>
                                    <div class="text-3xl font-bold text-violet-600 dark:text-violet-400 mt-1">
                                        {{ $activeSetups > 0 ? $formatMinutes($longestDurationMinutes) : 'N/A' }}
                                    </div>
                                    <p class="text-xs text-violet-700 dark:text-violet-300 mt-1">
                                        Since machine entered setup
                                    </p>
                                </div>

                                <div class="bg-violet-50 dark:bg-violet-900/20 border-2 border-violet-200 dark:border-violet-800 rounded-lg p-4">
                                    <div class="text-xs font-medium text-violet-900 dark:text-violet-100 uppercase">Total Active Setup Time</div>
                                    <div class="text-3xl font-bold text-violet-600 dark:text-violet-400 mt-1">
                                        {{ $activeSetups > 0 ? $formatMinutes($totalDurationMinutes) : '0 min' }}
                                    </div>
                                    <p class="text-xs text-violet-700 dark:text-violet-300 mt-1">
                                        Combined for current setups
                                    </p>
                                </div>
                            </div>

                            <div>
                                <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 my-3">
                                    Active Setup Work Orders
                                </h4>

                                @if(!empty($setupWorkOrders))
                                    <div class="border border-violet-200 dark:border-violet-800 rounded-lg overflow-hidden">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-800">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO Number</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Part</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Setup Since</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scheduled Start</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($setupWorkOrders as $wo)
                                                    <tr class="hover:bg-violet-50 dark:hover:bg-violet-900/10">
                                                        <td class="px-4 py-3">
                                                            <a href="{{ \App\Filament\Admin\Resources\WorkOrderResource::getUrl('view', ['record' => $wo['id']]) }}"
                                                               class="text-sm font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                                               wire:navigate>
                                                                {{ $wo['wo_number'] }}
                                                            </a>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                            <div class="font-medium">{{ $wo['machine_name'] ?? 'N/A' }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $wo['machine_asset_id'] ?? 'N/A' }}</div>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                            {{ $wo['part_number'] ?? 'N/A' }}
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                            {{ $wo['operator'] ?? 'Unassigned' }}
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                            @if(!empty($wo['setup_since']))
                                                                @php $since = \Carbon\Carbon::parse($wo['setup_since']); @endphp
                                                                <div>{{ $since->diffForHumans(null, true) }} ago</div>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                    {{ $since->format('M d, H:i') }}
                                                                </div>
                                                            @else
                                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $wo['setup_duration'] ?? 'N/A' }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-right text-violet-700 dark:text-violet-300 font-medium">
                                                            {{ $wo['scheduled_start'] ?? 'Not scheduled' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                                        No machines are currently in setup.
                                    </div>
                                @endif
                            </div>
                        @else
                            @php
                                $setupAnalytics = $this->getSetupTimeAnalyticsData();
                            @endphp

                            @include('filament.admin.pages.setup-time-analytics', ['data' => $setupAnalytics])
                        @endif
                    </div>
                </x-filament::card>
            @endif

            {{-- Defect Rate KPI Content --}}
            @if($selectedKPI === 'defect_rate')
                @php
                    $defectData = $this->getDefectRateData();
                @endphp

                @if($kpiMode === 'dashboard')
                    <x-filament::card>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-bold">Defect Rate</h2>
                                <button
                                    wire:click="refreshData"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                    wire:loading.attr="disabled"
                                >
                                    <x-heroicon-o-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refreshData" />
                                    <span>Refresh</span>
                                </button>
                            </div>

                            @php
                                $defectSummary = $defectData['summary'] ?? [];
                                $defectRows = $defectData['work_orders_paginated'] ?? [];
                                $defectPagination = $defectData['pagination'] ?? null;
                            @endphp

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                <x-filament::card>
                                    <div class="space-y-2">
                                        <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            Defective WOs Today
                                        </h4>
                                        <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                                            {{ number_format($defectSummary['defective_work_orders'] ?? 0) }}
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Running work orders with recorded scrap today
                                        </p>
                                    </div>
                                </x-filament::card>

                                <x-filament::card>
                                    <div class="space-y-2">
                                        <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            Total Scrap Today
                                        </h4>
                                        <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                                            {{ number_format($defectSummary['total_scrap_today'] ?? 0) }}
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Units scrapped across all active WOs
                                        </p>
                                    </div>
                                </x-filament::card>

                                <x-filament::card>
                                    <div class="space-y-2">
                                        <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            Avg Defect Rate
                                        </h4>
                                        <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                                            {{ number_format($defectSummary['avg_defect_rate'] ?? 0, 2) }}%
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Weighted by units produced today
                                        </p>
                                    </div>
                                </x-filament::card>

                                <x-filament::card>
                                    <div class="space-y-2">
                                        <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            Worst Defect Rate
                                        </h4>
                                        <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                                            {{ number_format($defectSummary['worst_defect_rate'] ?? 0, 2) }}%
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Highest defect rate among today&apos;s WOs
                                        </p>
                                    </div>
                                </x-filament::card>

                                <x-filament::card>
                                    <div class="space-y-2">
                                        <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            Total Produced Today
                                        </h4>
                                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                                            {{ number_format($defectSummary['total_produced_today'] ?? 0) }}
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Ok + scrap units logged today
                                        </p>
                                    </div>
                                </x-filament::card>
                            </div>

                            <div class="space-y-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Work Orders With Scrap Recorded Today
                                </h3>

                                @if(!empty($defectRows))
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-800">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Work Order</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operator</th>
                                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scrap Today</th>
                                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produced Today</th>
                                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Defect Rate</th>
                                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cumulative Rate</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Last Scrap</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($defectRows as $row)
                                                    <tr class="hover:bg-red-50 dark:hover:bg-red-900/10">
                                                        <td class="px-4 py-3 text-sm font-mono font-medium text-primary-600 dark:text-primary-400">
                                                            <a href="{{ \App\Filament\Admin\Resources\WorkOrderResource::getUrl('view', ['record' => $row['id']]) }}"
                                                               wire:navigate>
                                                                {{ $row['wo_number'] ?? 'N/A' }}
                                                            </a>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                            <div class="font-medium">{{ $row['machine_name'] ?? 'N/A' }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['machine_asset_id'] ?? 'N/A' }}</div>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                            {{ $row['operator'] ?? 'Unassigned' }}
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                                            {{ number_format($row['scrap_today'] ?? 0) }}
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                                            {{ number_format($row['produced_today'] ?? 0) }}
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-right text-red-600 dark:text-red-400 font-medium">
                                                            {{ number_format($row['defect_rate_today'] ?? 0, 2) }}%
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">
                                                            {{ number_format($row['cumulative_defect_rate'] ?? 0, 2) }}%
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                            @if(!empty($row['last_scrap_at']))
                                                                <div>{{ \Carbon\Carbon::parse($row['last_scrap_at'])->format('M d, H:i') }}</div>
                                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['last_scrap_human'] ?? '' }}</div>
                                                            @else
                                                                <span class="text-xs text-gray-500 dark:text-gray-400">No scrap logged yet</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    @if($defectPagination)
                                        <x-machine-table-pagination :pagination="$defectPagination" status="defect" wireMethod="gotoDefectPage" />
                                    @endif
                                @else
                                    <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                                        No scrap has been recorded yet today.
                                    </div>
                                @endif
                            </div>

                            <div class="text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700 pt-4 flex items-center justify-between">
                                <span>Total Work Orders Listed: {{ number_format($defectSummary['defective_work_orders'] ?? 0) }}</span>
                                <span>Last Updated: {{ \Carbon\Carbon::parse($defectData['updated_at'] ?? now())->diffForHumans() }}</span>
                            </div>
                        </div>
                    </x-filament::card>
                @else
                    <x-filament::card>
                        <div class="space-y-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Defect Rate - Analytics</h2>
                            @include('filament.admin.pages.defect-rate-analytics', ['data' => $defectData])
                        </div>
                    </x-filament::card>
                @endif
            @endif

            {{-- Production Schedule Adherence KPI Content --}}
            @if($selectedKPI === 'production_schedule')
                @if($kpiMode === 'analytics')
                    @include('filament.admin.pages.production-schedule-analytics')
                @else
                    @include('filament.admin.pages.production-schedule')
                @endif
            @endif

            {{-- Machine Utilization Rate KPI Content --}}
            @if($selectedKPI === 'machine_utilization')
                @if($kpiMode === 'dashboard')
                    {{-- Dashboard Mode: Shows TODAY's data only --}}
                    @include('filament.admin.pages.machine-utilization')
                @else
                    {{-- Analytics Mode: Historical data from kpi_machine_daily table --}}
                    <x-filament::card>
                        <div class="space-y-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Machine Utilization Rate - Analytics</h2>
                            @php $data = $this->getMachineUtilizationData(); @endphp
                            @include('filament.admin.pages.machine-utilization-analytics')
                        </div>
                    </x-filament::card>
                @endif
            @endif
        </div>
    @endif

    <script>
        console.log('=== Machine Status Chart Script Loading ===');

        (function() {
            // Store chart instance globally so it can be accessed across Alpine reinits
            if (!window.machineStatusChartInstance) {
                window.machineStatusChartInstance = null;
            }

            // Expose global function for Alpine.js to call
            window.updateMachineStatusChartData = function(newData, newLabels) {
                console.log('🔄 updateMachineStatusChartData called with:', { newData, newLabels });

                const canvas = document.getElementById('machine-status-donut');
                if (!canvas) {
                    console.log('❌ Canvas not found');
                    return;
                }

                if (typeof Chart === 'undefined') {
                    console.log('⏳ Chart.js not loaded yet');
                    return;
                }

                const colors = ['#16a34a', '#7c3aed', '#f59e0b', '#2563eb', '#6b7280'];

                try {
                    // Destroy existing chart if it exists
                    if (window.machineStatusChartInstance) {
                        console.log('🗑️ Destroying existing chart instance');
                        window.machineStatusChartInstance.destroy();
                        window.machineStatusChartInstance = null;
                    }

                    // Always create a new chart
                    console.log('🆕 Creating new chart with data:', newData, 'labels:', newLabels);
                    const ctx = canvas.getContext('2d');

                    window.machineStatusChartInstance = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: newLabels,
                            datasets: [{
                                label: 'Machines',
                                data: newData,
                                backgroundColor: colors,
                                borderColor: '#ffffff',
                                borderWidth: 2,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 15,
                                        font: { size: 12 }
                                    },
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return label + ': ' + value + ' machines (' + percentage + '%)';
                                        },
                                    },
                                },
                            },
                        },
                    });
                    console.log('✅ Chart created successfully!');
                } catch (error) {
                    console.error('❌ Error creating/updating chart:', error);
                }
            };

            // Re-initialize on Livewire navigation
            document.addEventListener('livewire:navigated', function() {
                console.log('🔄 livewire:navigated event fired');
                if (window.machineStatusChartInstance) {
                    window.machineStatusChartInstance.destroy();
                    window.machineStatusChartInstance = null;
                }
            });
        })();
    </script>

    @once
        <script>
            window.machineStatusAnalyticsCharts = window.machineStatusAnalyticsCharts || { donuts: {}, trend: null };

            window.machineStatusAnalyticsDonut = function (config) {
                return {
                    chartId: config.chartId ?? 'primary',
                    labels: config.labels ?? [],
                    data: config.data ?? [],
                    init() {
                        this.render();
                    },
                    render() {
                        this.$nextTick(() => {
                            if (typeof Chart === 'undefined') {
                                console.warn('Chart.js not available for analytics donut.');
                                return;
                            }

                            const canvas = this.$refs.canvas;
                            if (!canvas) {
                                return;
                            }

                            const ctx = canvas.getContext('2d');
                            const chartKey = `donut-${this.chartId}`;

                            if (window.machineStatusAnalyticsCharts.donuts[chartKey]) {
                                window.machineStatusAnalyticsCharts.donuts[chartKey].destroy();
                            }

                            window.machineStatusAnalyticsCharts.donuts[chartKey] = new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: this.labels,
                                    datasets: [{
                                        data: this.data,
                                        backgroundColor: ['#16a34a', '#7c3aed', '#f59e0b', '#2563eb', '#6b7280'],
                                        borderWidth: 1,
                                    }],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: (context) => {
                                                    const label = context.label || '';
                                                    const value = context.raw ?? 0;
                                                    return `${label}: ${value}%`;
                                                },
                                            },
                                        },
                                    },
                                    cutout: '55%',
                                },
                            });
                        });
                    },
                    destroy() {
                        const chartKey = `donut-${this.chartId}`;
                        if (window.machineStatusAnalyticsCharts.donuts[chartKey]) {
                            window.machineStatusAnalyticsCharts.donuts[chartKey].destroy();
                            delete window.machineStatusAnalyticsCharts.donuts[chartKey];
                        }
                    },
                };
            };

            window.machineStatusAnalyticsTrend = function (config) {
                return {
                    labels: config.labels ?? [],
                    primarySeries: config.primarySeries ?? {},
                    comparisonSeries: config.comparisonSeries ?? null,
                    comparisonEnabled: !!config.comparisonEnabled,
                    init() {
                        this.render();
                    },
                    render() {
                        this.$nextTick(() => {
                            if (typeof Chart === 'undefined') {
                                console.warn('Chart.js not available for analytics trend.');
                                return;
                            }

                            const canvas = this.$refs.canvas;
                            if (!canvas) {
                                return;
                            }

                            const ctx = canvas.getContext('2d');

                            if (window.machineStatusAnalyticsCharts.trend) {
                                window.machineStatusAnalyticsCharts.trend.destroy();
                            }

                            const palette = {
                                running: '#16a34a',
                                setup: '#7c3aed',
                                hold: '#f59e0b',
                                scheduled: '#2563eb',
                                idle: '#6b7280',
                            };

                            const datasets = Object.keys(this.primarySeries).map((status) => ({
                                label: `${status.charAt(0).toUpperCase() + status.slice(1)} (%)`,
                                data: this.primarySeries[status] ?? [],
                                borderColor: palette[status],
                                backgroundColor: palette[status],
                                tension: 0.3,
                                fill: false,
                                borderWidth: 2,
                            }));

                            if (this.comparisonEnabled && this.comparisonSeries) {
                                Object.keys(this.comparisonSeries).forEach((status) => {
                                    datasets.push({
                                        label: `${status.charAt(0).toUpperCase() + status.slice(1)} (Comparison)`,
                                        data: this.comparisonSeries[status] ?? [],
                                        borderColor: palette[status],
                                        backgroundColor: palette[status],
                                        tension: 0.3,
                                        fill: false,
                                        borderWidth: 2,
                                        borderDash: [6, 3],
                                    });
                                });
                            }

                            window.machineStatusAnalyticsCharts.trend = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: this.labels,
                                    datasets,
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: {
                                        mode: 'index',
                                        intersect: false,
                                    },
                                    stacked: false,
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: (context) => {
                                                    const label = context.dataset.label || '';
                                                    const value = context.parsed.y ?? 0;
                                                    return `${label}: ${value}%`;
                                                },
                                            },
                                        },
                                    },
                                    scales: {
                                        y: {
                                            min: 0,
                                            max: 100,
                                            ticks: {
                                                callback: (value) => `${value}%`,
                                            },
                                            grid: {
                                                color: 'rgba(148, 163, 184, 0.2)',
                                            },
                                        },
                                        x: {
                                            grid: {
                                                color: 'rgba(148, 163, 184, 0.15)',
                                            },
                                        },
                                    },
                                },
                            });
                        });
                    },
                };
            };
        </script>
    @endonce
</x-filament-panels::page>
