<div>
    @if(!$customer)
        <div class="text-gray-500 dark:text-gray-400">No Customer Found</div>
    @else
        @php
            $statusDistribution = $this->getWorkOrderStatusDistribution();
            $totalOrders = $statusDistribution->sum('count');
            $statusColors = config('work_order_status');

            // Calculate percentages for each status
            $statuses = ['Assigned', 'Start', 'Hold', 'Completed', 'Closed'];
            $statusData = [];
            $chartData = [];

            foreach ($statuses as $status) {
                $count = $statusDistribution->get($status)?->count ?? 0;
                $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
                $statusData[$status] = [
                    'count' => $count,
                    'percentage' => $percentage
                ];
                $chartData[] = $count;
            }

            $chartColors = [
                $statusColors['assigned'],
                $statusColors['start'],
                $statusColors['hold'],
                $statusColors['completed'],
                $statusColors['closed'],
            ];

            $qualityData = $this->getQualityData();
            $totalOk = $qualityData->total_ok_qtys ?? 0;
            $totalScrapped = $qualityData->total_scrapped_qtys ?? 0;
            $totalProduced = $qualityData->total_produced ?? 0;

            // Calculate quality rate: ((Produced - Scrapped) / Produced) × 100%
            $qualityRate = 0;
            if ($totalProduced > 0) {
                $qualityRate = (($totalProduced - $totalScrapped) / $totalProduced) * 100;
            }

            $chartId = 'customer-chart-' . $customer->id;
        @endphp

        <div class="space-y-6">
            {{-- Work Order Status Distribution Section --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Work Order Status Distribution (Total: {{ $totalOrders }} orders)</h3>

                {{-- Use the customer status chart component --}}
                <div style="height: 280px;" wire:key="chart-container-{{ $customer->id }}">
                    @livewire('customer-status-chart', ['record' => $customer, 'fromDate' => $fromDate, 'toDate' => $toDate, 'factoryId' => $factoryId], key('chart-' . $customer->id))
                </div>
            </div>

            {{-- Customer Work Order Analytics Section --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Work Order Analytics</h3>

                {{-- Main Container with Side-by-Side Layout --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Work Order Summary Section --}}
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
                                <p class="text-xs text-gray-500 dark:text-gray-400">Total: {{ $totalOrders }} orders</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @foreach($statusData as $status => $data)
                                @php
                                    $color = $chartColors[array_search($status, $statuses)];
                                @endphp
                                <div class="text-center p-2 rounded-lg" style="background-color: {{ $color }}20;">
                                    <div class="text-lg font-bold" style="color: {{ $color }};">{{ $data['percentage'] }}%</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300">{{ $status }}</div>
                                    <div class="text-xs text-gray-500">({{ $data['count'] }})</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Quality Rate Section --}}
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
                                <h4 class="text-base font-medium text-gray-900 dark:text-white">Quality Metrics</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Completed/Closed Orders</p>
                            </div>
                        </div>

                        @if($totalProduced > 0)
                            <div class="grid grid-cols-2 gap-2">
                                <div class="text-center p-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                    <div class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ number_format($totalProduced) }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300">Produced Qty</div>
                                </div>

                                <div class="text-center p-2 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                                    <div class="text-lg font-bold text-green-600 dark:text-green-400">{{ number_format($totalOk) }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300">Ok Qty</div>
                                </div>

                                <div class="text-center p-2 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                                    <div class="text-lg font-bold text-red-600 dark:text-red-400">{{ number_format($totalScrapped) }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300">Scrapped Qty</div>
                                </div>

                                <div class="text-center p-2 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800">
                                    <div class="text-xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($qualityRate, 1) }}%</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300">Quality Rate</div>
                                </div>
                            </div>
                        @else
                            <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                                <p class="text-yellow-800 dark:text-yellow-200 text-xs">No completed orders found</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Customer Information Section --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Customer Information</h3>

                @php
                    $customerName = $customer->name ?? '';
                    $customerId = $customer->customer_id ?? '';
                    $customerAddress = $customer->address ?? '';
                @endphp

                {{-- Desktop Table --}}
                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                        <thead class="bg-primary-500 dark:bg-primary-700">
                            <tr>
                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Customer ID</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Customer Name</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">{{ $customerId }}</td>
                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">{{ $customerName }}</td>
                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">{{ $customerAddress }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Card --}}
                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                        Customer Details
                    </div>
                    <div class="p-4 space-y-3">
                        <div>
                            <span class="font-bold text-black dark:text-white">Customer ID: </span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customerId }}</span>
                        </div>
                        <div>
                            <span class="font-bold text-black dark:text-white">Customer Name: </span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customerName }}</span>
                        </div>
                        <div>
                            <span class="font-bold text-black dark:text-white">Address: </span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $customerAddress }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @endif
</div>
