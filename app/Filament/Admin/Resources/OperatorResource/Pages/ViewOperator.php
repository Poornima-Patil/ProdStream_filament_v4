<?php

namespace App\Filament\Admin\Resources\OperatorResource\Pages;

use App\Filament\Admin\Resources\OperatorResource;
use App\Filament\Admin\Resources\OperatorResource\Widgets\OperatorStatusChart;
use App\Livewire\Calendar\Operators\OperatorScheduleCalendar;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Livewire;
use Illuminate\Support\HtmlString;
use Filament\Resources\Pages\ViewRecord;
use Carbon\Carbon;

class ViewOperator extends ViewRecord
{
    protected static string $resource = OperatorResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            OperatorStatusChart::class
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Operator Summary Section
            Section::make('Operator Summary')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('operator_summary')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (!$record) {
                                return new \Illuminate\Support\HtmlString('<div class="text-gray-500 dark:text-gray-400">No Operator Found</div>');
                            }

                            // Get work order status distribution for this operator (all-time)
                            $statusDistribution = \Illuminate\Support\Facades\DB::table('work_orders')
                                ->where('operator_id', $record->id)
                                ->where('factory_id', \Filament\Facades\Filament::getTenant()->id)
                                ->selectRaw('status, COUNT(*) as count')
                                ->groupBy('status')
                                ->get()
                                ->keyBy('status');

                            $totalOrders = $statusDistribution->sum('count');
                            $statusColors = config('work_order_status');

                            // Calculate percentages for each status
                            $statuses = ['Assigned', 'Start', 'Hold', 'Completed', 'Closed'];
                            $statusData = [];

                            foreach ($statuses as $status) {
                                $count = $statusDistribution->get($status)?->count ?? 0;
                                $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
                                $statusData[$status] = [
                                    'count' => $count,
                                    'percentage' => $percentage
                                ];
                            }

                            // Get quality data for completed/closed work orders (all-time)
                            $qualityData = \Illuminate\Support\Facades\DB::table('work_orders')
                                ->where('operator_id', $record->id)
                                ->where('factory_id', \Filament\Facades\Filament::getTenant()->id)
                                ->whereIn('status', ['Completed', 'Closed'])
                                ->selectRaw('
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
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                    <!-- Main Container with Side-by-Side Layout -->
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
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total: ' . $totalOrders . ' orders</p>
                                                </div>
                                            </div>
                                            
                                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                <div class="text-center p-2 rounded-lg" style="background-color: ' . $statusColors['assigned'] . '20;">
                                                    <div class="text-lg font-bold" style="color: ' . $statusColors['assigned'] . ';">' . $statusData['Assigned']['percentage'] . '%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Assigned</div>
                                                    <div class="text-xs text-gray-500">(' . $statusData['Assigned']['count'] . ')</div>
                                                </div>

                                                <div class="text-center p-2 rounded-lg" style="background-color: ' . $statusColors['start'] . '20;">
                                                    <div class="text-lg font-bold" style="color: ' . $statusColors['start'] . ';">' . $statusData['Start']['percentage'] . '%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Start</div>
                                                    <div class="text-xs text-gray-500">(' . $statusData['Start']['count'] . ')</div>
                                                </div>

                                                <div class="text-center p-2 rounded-lg" style="background-color: ' . $statusColors['hold'] . '20;">
                                                    <div class="text-lg font-bold" style="color: ' . $statusColors['hold'] . ';">' . $statusData['Hold']['percentage'] . '%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Hold</div>
                                                    <div class="text-xs text-gray-500">(' . $statusData['Hold']['count'] . ')</div>
                                                </div>

                                                <div class="text-center p-2 rounded-lg" style="background-color: ' . $statusColors['completed'] . '20;">
                                                    <div class="text-lg font-bold" style="color: ' . $statusColors['completed'] . ';">' . $statusData['Completed']['percentage'] . '%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Completed</div>
                                                    <div class="text-xs text-gray-500">(' . $statusData['Completed']['count'] . ')</div>
                                                </div>

                                                <div class="text-center p-2 rounded-lg" style="background-color: ' . $statusColors['closed'] . '20;">
                                                    <div class="text-lg font-bold" style="color: ' . $statusColors['closed'] . ';">' . $statusData['Closed']['percentage'] . '%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Closed</div>
                                                    <div class="text-xs text-gray-500">(' . $statusData['Closed']['count'] . ')</div>
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
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Completed/Closed Orders</p>
                                                </div>
                                            </div>
                                            
                                            ' . ($totalProduced > 0 ? '
                                            <div class="grid grid-cols-2 gap-2">
                                                <div class="text-center p-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                                    <div class="text-lg font-bold text-blue-600 dark:text-blue-400">' . number_format($totalProduced) . '</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Produced Qty</div>
                                                </div>
                                                
                                                <div class="text-center p-2 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                                                    <div class="text-lg font-bold text-green-600 dark:text-green-400">' . number_format($totalOk) . '</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Ok Qty</div>
                                                </div>
                                                
                                                <div class="text-center p-2 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                                                    <div class="text-lg font-bold text-red-600 dark:text-red-400">' . number_format($totalScrapped) . '</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Scrapped Qty</div>
                                                </div>
                                                
                                                <div class="text-center p-2 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800">
                                                    <div class="text-xl font-bold text-purple-600 dark:text-purple-400">' . number_format($qualityRate, 1) . '%</div>
                                                    <div class="text-xs text-gray-600 dark:text-gray-300">Quality Rate</div>
                                                </div>
                                            </div>
                                            ' : '
                                            <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                                                <p class="text-yellow-800 dark:text-yellow-200 text-xs">No completed orders found</p>
                                            </div>
                                            ') . '
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
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
