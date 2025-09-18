<?php

namespace App\Filament\Admin\Resources\OperatorResource\Pages;

use App\Filament\Admin\Resources\OperatorResource;
use App\Filament\Admin\Resources\OperatorResource\Widgets\OperatorStatusChart;
use App\Livewire\Calendar\Operators\OperatorScheduleCalendar;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

class ViewOperator extends ViewRecord
{
    protected static string $resource = OperatorResource::class;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

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

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Work Order Analytics & Distribution
            Section::make('Work Order Analytics & Distribution')
                ->collapsible()
                ->columnSpanFull()
                ->description('Filter work orders and view analytics for this operator')
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
                                return new \Illuminate\Support\HtmlString('<div class="text-gray-500 dark:text-gray-400">No Operator Found</div>');
                            }

                            // Get work order status distribution for ALL statuses (for Summary)
                            $summaryQuery = \Illuminate\Support\Facades\DB::table('work_orders')
                                ->where('operator_id', $record->id)
                                ->where('factory_id', \Filament\Facades\Filament::getTenant()->id);

                            // Apply date range filter
                            if ($this->dateFrom && $this->dateTo) {
                                $summaryQuery->whereBetween('created_at', [
                                    Carbon::parse($this->dateFrom)->startOfDay(),
                                    Carbon::parse($this->dateTo)->endOfDay(),
                                ]);
                            }

                            $statusDistribution = $summaryQuery->selectRaw('status, COUNT(*) as count')
                                ->groupBy('status')
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
                            $qualityQuery = \Illuminate\Support\Facades\DB::table('work_orders')
                                ->where('operator_id', $record->id)
                                ->where('factory_id', \Filament\Facades\Filament::getTenant()->id)
                                ->whereIn('status', ['Completed', 'Closed']);

                            // Apply date range filter
                            if ($this->dateFrom && $this->dateTo) {
                                $qualityQuery->whereBetween('created_at', [
                                    Carbon::parse($this->dateFrom)->startOfDay(),
                                    Carbon::parse($this->dateTo)->endOfDay(),
                                ]);
                            }

                            $qualityData = $qualityQuery->selectRaw('
                                    SUM(ok_qtys) as total_ok_qtys,
                                    SUM(scrapped_qtys) as total_scrapped_qtys,
                                    SUM(ok_qtys + scrapped_qtys) as total_produced
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

                            return new \Illuminate\Support\HtmlString('
                                <div class="space-y-6">
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
                                    <div id="operator-chart-container-' . $record->id . '"></div>
                                </div>
                            ');
                        })->html(),

                    // Include the chart widget as a hidden component that will render into the div above
                    Livewire::make(OperatorStatusChart::class, [
                        'record' => $this->record,
                        'dateFrom' => $this->dateFrom,
                        'dateTo' => $this->dateTo,
                    ])->key('operator-status-chart-' . $this->record->id),
                ]),

            Section::make('Operator Information')
                ->hiddenLabel()
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('View Operator')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (! $record) {
                                return '<div class="text-gray-500 dark:text-gray-400">NO Operators found</div>';
                            }
                            $Proficiency = $record->operator_proficiency->proficiency;
                            $OperatorFirstName = $record->user->first_name;
                            $OperatorLastName = $record->user->last_name;
                            $OperatorFullName = trim($OperatorFirstName.' '.$OperatorLastName);
                            $Shift = $record->shift->name;
                            $ShiftHours = $record->shift->start_time.' - '.$record->shift->end_time;

                            return new \Illuminate\Support\HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block overflow-x-auto rounded-lg shadow">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Operator</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Proficiency</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Shift</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Shift Hours</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($OperatorFullName).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Proficiency).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Shift).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-blue-600 dark:text-blue-400 font-medium">'.htmlspecialchars($ShiftHours).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4 overflow-hidden">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Operator Information
                                    </div>
                                    <div class="p-4 space-y-3 max-w-full overflow-x-auto">
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Operator:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($OperatorFullName).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Proficiency:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($Proficiency).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Shift:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($Shift).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Shift Hours:</span>
                                            <span class="text-blue-600 dark:text-blue-400 font-medium break-all sm:text-right sm:ml-2">'.htmlspecialchars($ShiftHours).'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            Section::make('Operator Schedule Gantt Chart')
                ->columnSpanFull()
                ->schema([
                    Livewire::make(OperatorScheduleCalendar::class, ['operator' => $this->record])
                        ->key('operator-calendar-'.$this->record->id),
                ])
                ->collapsible()
                ->persistCollapsed()
                ->id('operator-schedule-section')
                ->extraAttributes([
                    'class' => 'hidden lg:block', // Hide on small/medium devices, show on large and above
                ]),
        ]);
    }
}
