<div class="space-y-4">
    @if($workOrderData && $qualityData)
        <!-- Chart and Summary Combined Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left side - Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <div class="w-6 h-6 bg-purple-100 dark:bg-purple-900/20 rounded-md flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-base font-medium text-gray-900 dark:text-white">Status Distribution</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            All time data
                        </p>
                    </div>
                </div>

                <!-- Donut Chart Container -->
                <div class="relative" style="height: 300px;">
                    @php
                        $statusData = $workOrderData['statusData'] ?? [];
                        $total = $workOrderData['totalOrders'] ?? 0;
                        $statusColors = $workOrderData['statusColors'] ?? [];
                        $chartId = 'donut-chart-' . uniqid();
                    @endphp

                    <!-- Always show canvas and let JavaScript handle the display -->
                    <canvas id="{{ $chartId }}" style="width: 100%; height: 300px;"></canvas>


                    <!-- Total Summary Below Chart -->
                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Orders:</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($total) }}</span>
                        </div>
                    </div>
                </div>

                <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
                <script>
                (function() {
                    let chartInstance_{{ str_replace('-', '_', $chartId) }} = null;
                    const chartId = '{{ $chartId }}';

                    function createChart() {
                        console.log('🔄 Starting chart creation for:', chartId);

                        const ctx = document.getElementById(chartId);
                        if (!ctx) {
                            console.error('❌ Canvas element not found:', chartId);
                            return;
                        }

                        if (typeof Chart === 'undefined') {
                            console.error('❌ Chart.js not loaded');
                            return;
                        }

                        console.log('✅ Canvas found, Chart.js loaded');

                        // Destroy existing chart
                        if (chartInstance_{{ str_replace('-', '_', $chartId) }}) {
                            console.log('🗑️ Destroying existing chart');
                            chartInstance_{{ str_replace('-', '_', $chartId) }}.destroy();
                            chartInstance_{{ str_replace('-', '_', $chartId) }} = null;
                        }

                        // Also check Chart.js registry
                        const existingChart = Chart.getChart(ctx);
                        if (existingChart) {
                            console.log('🗑️ Destroying chart from registry');
                            existingChart.destroy();
                        }

                        // Prepare chart data - using the same source as debug panel
                        const workOrderData = @json($workOrderData);
                        const statusData = workOrderData?.statusData || {};
                        const statusColors = workOrderData?.statusColors || {};
                        const total = workOrderData?.totalOrders || 0;

                        console.log('📊 FULL workOrderData received by JavaScript:', workOrderData);
                        console.log('📊 Chart Data - Total:', total, 'StatusData:', statusData);
                        console.log('📊 StatusColors:', statusColors);

                        if (total === 0) {
                            console.log('⚠️ No data (total = 0), showing empty message');
                            const context = ctx.getContext('2d');
                            context.clearRect(0, 0, ctx.width, ctx.height);
                            context.save();
                            context.font = '16px sans-serif';
                            context.fillStyle = '#6b7280';
                            context.textAlign = 'center';
                            context.textBaseline = 'middle';
                            context.fillText('No work orders found', ctx.width / 2, ctx.height / 2 - 10);
                            context.fillText('for the selected date range', ctx.width / 2, ctx.height / 2 + 10);
                            context.restore();
                            return;
                        }

                        const labels = ['Assigned', 'Start', 'Hold', 'Completed', 'Closed'];
                        const data = [
                            statusData.Assigned?.count || 0,
                            statusData.Start?.count || 0,
                            statusData.Hold?.count || 0,
                            statusData.Completed?.count || 0,
                            statusData.Closed?.count || 0
                        ];
                        const colors = [
                            statusColors.assigned || '#6b7280',
                            statusColors.start || '#3b82f6',
                            statusColors.hold || '#f59e0b',
                            statusColors.completed || '#10b981',
                            statusColors.closed || '#8b5cf6'
                        ];

                        console.log('📈 Creating chart with labels:', labels, 'data:', data);

                        try {
                            chartInstance_{{ str_replace('-', '_', $chartId) }} = new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        data: data,
                                        backgroundColor: colors,
                                        borderWidth: 2,
                                        borderColor: '#fff',
                                        hoverOffset: 10
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    cutout: '60%',
                                    plugins: {
                                        tooltip: {
                                            enabled: true,
                                            callbacks: {
                                                label: function(context) {
                                                    const value = context.raw || 0;
                                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                                    return context.label + ': ' + value + ' (' + percentage + '%)';
                                                }
                                            }
                                        },
                                        legend: {
                                            position: 'right',
                                            labels: {
                                                boxWidth: 15,
                                                padding: 12,
                                                font: {
                                                    size: 12
                                                }
                                            }
                                        }
                                    },
                                    animation: {
                                        onComplete: function() {
                                            console.log('🎨 Chart animation complete, drawing center text');
                                            const chart = this;
                                            const ctx = chart.ctx;

                                            // Calculate center
                                            const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                                            const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;

                                            ctx.save();
                                            ctx.font = 'bold 24px sans-serif';

                                            // Detect dark mode
                                            const isDark = document.documentElement.classList.contains('dark');
                                            ctx.fillStyle = isDark ? '#fff' : '#111';

                                            ctx.textAlign = 'center';
                                            ctx.textBaseline = 'middle';
                                            ctx.fillText(total.toString(), centerX, centerY);
                                            ctx.restore();
                                        }
                                    }
                                }
                            });

                            console.log('✅ Chart created successfully!');

                        } catch (error) {
                            console.error('❌ Error creating chart:', error);
                        }
                    }

                    // Multiple ways to trigger chart creation
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', createChart);
                    } else {
                        // DOM already loaded, create immediately
                        setTimeout(createChart, 50);
                    }

                    // Listen for Livewire updates
                    document.addEventListener('livewire:updated', function() {
                        console.log('🔄 Livewire updated, recreating chart in 200ms');
                        setTimeout(createChart, 200);
                    });

                    // Cleanup
                    window.addEventListener('beforeunload', function() {
                        if (chartInstance_{{ str_replace('-', '_', $chartId) }}) {
                            chartInstance_{{ str_replace('-', '_', $chartId) }}.destroy();
                        }
                    });

                    // Export for debugging
                    window.debugChart_{{ str_replace('-', '_', $chartId) }} = createChart;

                })();
                </script>
            </div>

            <!-- Right side - Summary Cards -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Work Order Summary Section -->
            <div class="space-y-3">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-6 h-6 bg-blue-100 dark:bg-blue-900/20 rounded-md flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-base font-medium text-gray-900 dark:text-white">Work Order Summary</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $workOrderData['totalOrders'] }} orders (All time)</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <div class="text-center p-2 rounded-lg" style="background-color: {{ $workOrderData['statusColors']['assigned'] ?? '#6b7280' }}20;">
                        <div class="text-lg font-bold" style="color: {{ $workOrderData['statusColors']['assigned'] ?? '#6b7280' }};">{{ $workOrderData['statusData']['Assigned']['percentage'] }}%</div>
                        <div class="text-xs text-gray-600 dark:text-gray-300">Assigned</div>
                        <div class="text-xs text-gray-500">({{ $workOrderData['statusData']['Assigned']['count'] }})</div>
                    </div>

                    <div class="text-center p-2 rounded-lg" style="background-color: {{ $workOrderData['statusColors']['start'] ?? '#6b7280' }}20;">
                        <div class="text-lg font-bold" style="color: {{ $workOrderData['statusColors']['start'] ?? '#6b7280' }};">{{ $workOrderData['statusData']['Start']['percentage'] }}%</div>
                        <div class="text-xs text-gray-600 dark:text-gray-300">Started</div>
                        <div class="text-xs text-gray-500">({{ $workOrderData['statusData']['Start']['count'] }})</div>
                    </div>

                    <div class="text-center p-2 rounded-lg" style="background-color: {{ $workOrderData['statusColors']['hold'] ?? '#6b7280' }}20;">
                        <div class="text-lg font-bold" style="color: {{ $workOrderData['statusColors']['hold'] ?? '#6b7280' }};">{{ $workOrderData['statusData']['Hold']['percentage'] }}%</div>
                        <div class="text-xs text-gray-600 dark:text-gray-300">Hold</div>
                        <div class="text-xs text-gray-500">({{ $workOrderData['statusData']['Hold']['count'] }})</div>
                    </div>

                    <div class="text-center p-2 rounded-lg" style="background-color: {{ $workOrderData['statusColors']['completed'] ?? '#6b7280' }}20;">
                        <div class="text-lg font-bold" style="color: {{ $workOrderData['statusColors']['completed'] ?? '#6b7280' }};">{{ $workOrderData['statusData']['Completed']['percentage'] }}%</div>
                        <div class="text-xs text-gray-600 dark:text-gray-300">Completed</div>
                        <div class="text-xs text-gray-500">({{ $workOrderData['statusData']['Completed']['count'] }})</div>
                    </div>

                    <div class="text-center p-2 rounded-lg col-span-2 sm:col-span-1" style="background-color: {{ $workOrderData['statusColors']['closed'] ?? '#6b7280' }}20;">
                        <div class="text-lg font-bold" style="color: {{ $workOrderData['statusColors']['closed'] ?? '#6b7280' }};">{{ $workOrderData['statusData']['Closed']['percentage'] }}%</div>
                        <div class="text-xs text-gray-600 dark:text-gray-300">Closed</div>
                        <div class="text-xs text-gray-500">({{ $workOrderData['statusData']['Closed']['count'] }})</div>
                    </div>
                </div>
            </div>

            <!-- Quality Rate Section -->
            <div class="space-y-3">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-6 h-6 bg-green-100 dark:bg-green-900/20 rounded-md flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <h4 class="text-base font-medium text-gray-900 dark:text-white">Quality Rate</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Completed/Closed Orders (All time)</p>
                    </div>
                </div>

                @if($qualityData['totalProduced'] > 0)
                    <div class="grid grid-cols-2 gap-2">
                        <div class="text-center p-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                            <div class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ number_format($qualityData['totalProduced']) }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-300">Produced Qty</div>
                        </div>

                        <div class="text-center p-2 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                            <div class="text-lg font-bold text-green-600 dark:text-green-400">{{ number_format($qualityData['totalOk']) }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-300">Ok Qty</div>
                        </div>

                        <div class="text-center p-2 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                            <div class="text-lg font-bold text-red-600 dark:text-red-400">{{ number_format($qualityData['totalScrapped']) }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-300">Scrapped Qty</div>
                        </div>

                        <div class="text-center p-2 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800">
                            <div class="text-xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($qualityData['qualityRate'], 1) }}%</div>
                            <div class="text-xs text-gray-600 dark:text-gray-300">Quality Rate</div>
                        </div>
                    </div>
                @else
                    <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                        <p class="text-yellow-800 dark:text-yellow-200 text-xs">No completed orders found for this machine group</p>
                    </div>
                @endif
                </div>
            </div>
        </div>
    @else
        <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
            <p class="text-red-800 dark:text-red-200 text-xs">No data available</p>
        </div>
    @endif

    <!-- Loading Indicator -->
    <div wire:loading class="absolute inset-0 bg-white bg-opacity-75 dark:bg-gray-800 dark:bg-opacity-75 flex items-center justify-center rounded-lg">
        <div class="flex items-center space-x-2">
            <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm text-gray-600 dark:text-gray-400">Loading...</span>
        </div>
    </div>
</div>