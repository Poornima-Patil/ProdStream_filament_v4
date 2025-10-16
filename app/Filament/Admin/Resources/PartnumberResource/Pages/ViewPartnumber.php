<?php

namespace App\Filament\Admin\Resources\PartnumberResource\Pages;

use App\Filament\Admin\Resources\PartNumberResource;
use App\Filament\Admin\Resources\PartnumberResource\Widgets\PartNumberStatusChart;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewPartnumber extends ViewRecord
{
    protected static string $resource = PartNumberResource::class;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    protected $listeners = ['kpi-date-type-changed' => 'refreshKpis'];

    public function mount($record): void
    {
        parent::mount($record);

        // Set default date range to last 30 days if not provided
        if (! $this->dateFrom || ! $this->dateTo) {
            $this->dateTo = Carbon::now()->format('Y-m-d');
            $this->dateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Removed chart widget from header - will be included in the section
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Moved date filter action to section
        ];
    }

    public function getDateRange(): array
    {
        return [$this->dateFrom, $this->dateTo];
    }

    public function refreshKpis(): void
    {
        // Refresh the component to recalculate KPIs with new date type
        $this->dispatch('$refresh');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Work Order Analytics & Distribution
            Section::make('Work Order Analytics & Distribution')
                ->collapsible()
                ->columnSpanFull()
                ->description('Filter work orders and view analytics for this part number')
                ->headerActions([
                    Action::make('filterByDate')
                        ->label('Filter by Date Range')
                        ->icon('heroicon-o-calendar')
                        ->color('primary')
                        ->form([
                            DatePicker::make('date_from')
                                ->label('From Date')
                                ->default($this->dateFrom)
                                ->required(),
                            DatePicker::make('date_to')
                                ->label('To Date')
                                ->default($this->dateTo)
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            $this->dateFrom = $data['date_from'];
                            $this->dateTo = $data['date_to'];

                            // Dispatch event to update the chart widget
                            $this->dispatch('dateRangeUpdated', dateFrom: $this->dateFrom, dateTo: $this->dateTo);

                            // Show notification
                            \Filament\Notifications\Notification::make()
                                ->title('Date range updated')
                                ->body('Data filtered from ' . $this->dateFrom . ' to ' . $this->dateTo)
                                ->success()
                                ->send();
                        }),
                ])
                ->schema([
                    // Combined content with chart and summary side by side
                    TextEntry::make('work_order_analytics')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (! $record) {
                                return new \Illuminate\Support\HtmlString('<div class="text-gray-500 dark:text-gray-400">No Part Number Found</div>');
                            }

                            // Get KPI date filter type from settings
                            $kpiDateType = session('kpi_date_type', 'created_at');

                            // Get work order status distribution for ALL statuses (for Summary)
                            // For part numbers, we need complex join: part_numbers -> purchase_orders -> boms -> work_orders
                            $summaryQuery = \Illuminate\Support\Facades\DB::table('part_numbers')
                                ->join('purchase_orders', 'part_numbers.id', '=', 'purchase_orders.part_number_id')
                                ->join('boms', 'purchase_orders.id', '=', 'boms.purchase_order_id')
                                ->join('work_orders', 'boms.id', '=', 'work_orders.bom_id')
                                ->where('part_numbers.id', $record->id)
                                ->where('work_orders.factory_id', \Filament\Facades\Filament::getTenant()->id);

                            // Apply date range filter based on KPI date type
                            if ($this->dateFrom && $this->dateTo) {
                                if ($kpiDateType === 'start_time') {
                                    // Only include work orders that have a Start log entry
                                    $summaryQuery->whereExists(function ($query) use ($summaryQuery) {
                                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                                            ->from('work_order_logs')
                                            ->whereColumn('work_order_logs.work_order_id', 'work_orders.id')
                                            ->where('work_order_logs.status', 'Start')
                                            ->whereBetween('work_order_logs.created_at', [
                                                Carbon::parse($this->dateFrom)->startOfDay(),
                                                Carbon::parse($this->dateTo)->endOfDay(),
                                            ])
                                            ->orderBy('work_order_logs.created_at', 'asc')
                                            ->limit(1);
                                    });
                                } else {
                                    // Default: use created_at
                                    $summaryQuery->whereBetween('work_orders.created_at', [
                                        Carbon::parse($this->dateFrom)->startOfDay(),
                                        Carbon::parse($this->dateTo)->endOfDay(),
                                    ]);
                                }
                            }

                            $statusDistribution = $summaryQuery->selectRaw('work_orders.status, COUNT(*) as count')
                                ->groupBy('work_orders.status')
                                ->get()
                                ->keyBy('status');

                            $totalOrders = $statusDistribution->sum('count');
                            $statusColors = config('work_order_status');

                            // Calculate percentages for ALL statuses
                            $statuses = ['Assigned', 'Start', 'Hold', 'Completed', 'Closed']; // All statuses for summary
                            $statusData = [];

                            foreach ($statuses as $status) {
                                $count = $statusDistribution->get($status)?->count ?? 0;
                                $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
                                $statusData[$status] = [
                                    'count' => $count,
                                    'percentage' => $percentage,
                                ];
                            }

                            // Get quality data for completed/closed work orders with date range filter
                            $qualityQuery = \Illuminate\Support\Facades\DB::table('part_numbers')
                                ->join('purchase_orders', 'part_numbers.id', '=', 'purchase_orders.part_number_id')
                                ->join('boms', 'purchase_orders.id', '=', 'boms.purchase_order_id')
                                ->join('work_orders', 'boms.id', '=', 'work_orders.bom_id')
                                ->where('part_numbers.id', $record->id)
                                ->where('work_orders.factory_id', \Filament\Facades\Filament::getTenant()->id)
                                ->whereIn('work_orders.status', ['Completed', 'Closed']);

                            // Apply date range filter based on KPI date type
                            if ($this->dateFrom && $this->dateTo) {
                                if ($kpiDateType === 'start_time') {
                                    // Only include work orders that have a Start log entry
                                    $qualityQuery->whereExists(function ($query) {
                                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                                            ->from('work_order_logs')
                                            ->whereColumn('work_order_logs.work_order_id', 'work_orders.id')
                                            ->where('work_order_logs.status', 'Start')
                                            ->whereBetween('work_order_logs.created_at', [
                                                Carbon::parse($this->dateFrom)->startOfDay(),
                                                Carbon::parse($this->dateTo)->endOfDay(),
                                            ])
                                            ->orderBy('work_order_logs.created_at', 'asc')
                                            ->limit(1);
                                    });
                                } else {
                                    // Default: use created_at
                                    $qualityQuery->whereBetween('work_orders.created_at', [
                                        Carbon::parse($this->dateFrom)->startOfDay(),
                                        Carbon::parse($this->dateTo)->endOfDay(),
                                    ]);
                                }
                            }

                            $qualityData = $qualityQuery->selectRaw('
                                    SUM(work_orders.ok_qtys) as total_ok_qtys,
                                    SUM(work_orders.scrapped_qtys) as total_scrapped_qtys,
                                    SUM(work_orders.ok_qtys + work_orders.scrapped_qtys) as total_produced
                                ')
                                ->first();

                            $totalOk = $qualityData->total_ok_qtys ?? 0;
                            $totalScrapped = $qualityData->total_scrapped_qtys ?? 0;
                            $totalProduced = $qualityData->total_produced ?? 0;

                            // Calculate quality rate: ((Produced - Scrapped) / Produced) × 100%
                            $qualityRate = 0;
                            if ($totalProduced > 0) {
                                $qualityRate = (($totalProduced - $totalScrapped) / $totalProduced) * 100;
                            }

                            // Count excluded non-started work orders when using start_time filter
                            $excludedCount = 0;
                            if ($kpiDateType === 'start_time' && $this->dateFrom && $this->dateTo) {
                                $excludedCount = \Illuminate\Support\Facades\DB::table('part_numbers')
                                    ->join('purchase_orders', 'part_numbers.id', '=', 'purchase_orders.part_number_id')
                                    ->join('boms', 'purchase_orders.id', '=', 'boms.purchase_order_id')
                                    ->join('work_orders', 'boms.id', '=', 'work_orders.bom_id')
                                    ->where('part_numbers.id', $record->id)
                                    ->where('work_orders.factory_id', \Filament\Facades\Filament::getTenant()->id)
                                    ->whereNotExists(function ($query) {
                                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                                            ->from('work_order_logs')
                                            ->whereColumn('work_order_logs.work_order_id', 'work_orders.id')
                                            ->where('work_order_logs.status', 'Start');
                                    })
                                    ->whereBetween('work_orders.created_at', [
                                        Carbon::parse($this->dateFrom)->startOfDay(),
                                        Carbon::parse($this->dateTo)->endOfDay(),
                                    ])
                                    ->count();
                            }

                            return new \Illuminate\Support\HtmlString('
                                <div class="space-y-6">
                                    '.($excludedCount > 0 ? '
                                    <!-- Info banner for excluded non-started work orders -->
                                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0">
                                                <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-blue-800 dark:text-blue-200">
                                                    <strong>Production Start Date Filter Active:</strong> '.$excludedCount.' work order'.($excludedCount > 1 ? 's' : '').' not yet started '.($excludedCount > 1 ? 'are' : 'is').' excluded from these metrics.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    ' : '').'

                                    <!-- Top Row: Work Order Summary and Quality Metrics Side by Side -->
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        <!-- Work Order Summary - Left Half -->
                                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                            <div class="flex items-center justify-between mb-4">
                                                <div class="flex items-center">
                                                    <div class="w-6 h-6 bg-blue-100 dark:bg-blue-900/20 rounded-md flex items-center justify-center mr-3">
                                                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h3 class="text-base font-medium text-gray-900 dark:text-white">Work Order Summary</h3>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Total: '.$totalOrders.' orders</p>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Period:</p>
                                                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300">'.($this->dateFrom && $this->dateTo ? Carbon::parse($this->dateFrom)->format('M j, Y').' - '.Carbon::parse($this->dateTo)->format('M j, Y') : 'All Time').'</p>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-2">
                                                <div class="text-center p-2 rounded-lg" style="background-color: '.$statusColors['assigned'].'20;">
                                                    <div class="text-lg font-bold" style="color: '.$statusColors['assigned'].';">'.$statusData['Assigned']['percentage'].'%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300 font-medium">Assigned</div>
                                                    <div class="text-xs text-gray-500">('.$statusData['Assigned']['count'].')</div>
                                                </div>

                                                <div class="text-center p-2 rounded-lg" style="background-color: '.$statusColors['start'].'20;">
                                                    <div class="text-lg font-bold" style="color: '.$statusColors['start'].';">'.$statusData['Start']['percentage'].'%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300 font-medium">Started</div>
                                                    <div class="text-xs text-gray-500">('.$statusData['Start']['count'].')</div>
                                                </div>

                                                <div class="text-center p-2 rounded-lg" style="background-color: '.$statusColors['hold'].'20;">
                                                    <div class="text-lg font-bold" style="color: '.$statusColors['hold'].';">'.$statusData['Hold']['percentage'].'%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300 font-medium">Hold</div>
                                                    <div class="text-xs text-gray-500">('.$statusData['Hold']['count'].')</div>
                                                </div>

                                                <div class="text-center p-2 rounded-lg" style="background-color: '.$statusColors['completed'].'20;">
                                                    <div class="text-lg font-bold" style="color: '.$statusColors['completed'].';">'.$statusData['Completed']['percentage'].'%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300 font-medium">Completed</div>
                                                    <div class="text-xs text-gray-500">('.$statusData['Completed']['count'].')</div>
                                                </div>

                                                <div class="text-center p-2 rounded-lg col-span-2 lg:col-span-3 xl:col-span-1" style="background-color: '.$statusColors['closed'].'20;">
                                                    <div class="text-lg font-bold" style="color: '.$statusColors['closed'].';">'.$statusData['Closed']['percentage'].'%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300 font-medium">Closed</div>
                                                    <div class="text-xs text-gray-500">('.$statusData['Closed']['count'].')</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Quality Metrics - Right Half -->
                                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                            <div class="flex items-center mb-4">
                                                <div class="w-6 h-6 bg-green-100 dark:bg-green-900/20 rounded-md flex items-center justify-center mr-3">
                                                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h4 class="text-base font-medium text-gray-900 dark:text-white">Quality Metrics</h4>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Based on Completed/Closed Orders</p>
                                                </div>
                                            </div>

                                            '.($totalProduced > 0 ? '
                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="text-center p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                                    <div class="text-lg font-bold text-blue-600 dark:text-blue-400">'.number_format($totalProduced).'</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Produced</div>
                                                </div>

                                                <div class="text-center p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                                                    <div class="text-lg font-bold text-green-600 dark:text-green-400">'.number_format($totalOk).'</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">OK Qty</div>
                                                </div>

                                                <div class="text-center p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                                                    <div class="text-lg font-bold text-red-600 dark:text-red-400">'.number_format($totalScrapped).'</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Scrapped</div>
                                                </div>

                                                <div class="text-center p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800">
                                                    <div class="text-xl font-bold text-purple-600 dark:text-purple-400">'.number_format($qualityRate, 1).'%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Quality Rate</div>
                                                </div>
                                            </div>
                                            ' : '
                                            <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800 text-center">
                                                <p class="text-yellow-800 dark:text-yellow-200 text-sm">No completed orders found for selected period</p>
                                            </div>
                                            ').'
                                        </div>
                                    </div>

                                    <!-- Bottom Row: Chart Full Width - Placeholder for Widget -->
                                    <div id="part-number-chart-container-' . $record->id . '"></div>
                                </div>
                            ');
                        })->html(),

                    // Include the chart widget as a hidden component that will render into the div above
                    Livewire::make(PartNumberStatusChart::class, [
                        'record' => $this->record,
                        'dateFrom' => $this->dateFrom,
                        'dateTo' => $this->dateTo,
                    ])->key('part-number-status-chart-' . $this->record->id),
                ]),

            Section::make('View Part Number')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('View PartNumber')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (!$record) {
                                return '<div class="text-gray-500 dark:text-gray-400">No Part Number Found</div>';
                            }

                            $Partnumber = $record->partnumber ?? '';
                            $Revision = $record->revision ?? '';
                            $Description = $record->description ?? '';

                            // Format cycle_time as MM:SS
                            $seconds = intval($record->cycle_time);
                            $minutes = floor($seconds / 60);
                            $remainingSeconds = $seconds % 60;
                            $Cycle_time = sprintf('%02d:%02d', $minutes, $remainingSeconds);

                            return new \Illuminate\Support\HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Part Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Revision</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Description</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Cycle Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Partnumber).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Revision).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Description).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$Cycle_time.'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Part Number Details
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Part Number: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Partnumber).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Revision: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Revision).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Description: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Description).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Cycle Time: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.$Cycle_time.'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),
        ]);
    }
}
