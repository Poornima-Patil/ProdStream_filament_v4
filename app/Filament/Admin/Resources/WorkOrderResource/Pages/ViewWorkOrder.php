<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use App\Filament\Admin\Resources\WorkOrderResource\Widgets\WorkOrderProgress;
use App\Filament\Admin\Resources\WorkOrderResource\Widgets\WorkOrderQtyTrendChart;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewWorkOrder extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        $user = Auth::user();
        $isAdminOrManager = $user && in_array($user->role, ['manager', 'admin']);

        return $schema->components([
            // Section 1: Work Order KPI
            Section::make('Work Order KPI')
                ->collapsible()->columnSpanFull()
                ->collapsed()
                ->columns([
                    'sm' => 1,
                    'md' => 2,
                ])
                ->schema([
                    // Work Order Progress Section
                    Section::make('')
                        ->columnSpan([
                            'sm' => 'full',
                            'md' => 1,
                        ])
                        ->schema([
                            TextEntry::make('progress_header')
                                ->label('')
                                ->getStateUsing(fn () => new HtmlString('
                                    <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                        <h4 class="font-bold text-black dark:text-white">Work Order Progress</h4>
                                    </div>
                                '))->html(),
                            Livewire::make(WorkOrderProgress::class),
                        ]),

                    // Work Order Quantity Trend Chart Section
                    Section::make('')
                        ->columnSpan([
                            'sm' => 'full',
                            'md' => 1,
                        ])
                        ->schema([
                            TextEntry::make('trend_header')
                                ->label('')
                                ->getStateUsing(fn () => new HtmlString('
                                    <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                        <h4 class="font-bold text-black dark:text-white">Work Order Quantity Trend Chart</h4>
                                    </div>
                                '))->html(),
                            Livewire::make(WorkOrderQtyTrendChart::class, ['workOrder' => $this->record]),
                        ]),

                    // Production Throughput Section - only show for completed or closed status
                    TextEntry::make('production_throughput_section')
                        ->label('')
                        ->columnSpan([
                            'sm' => 'full',
                            'md' => 1,
                        ])
                        ->visible(fn ($record) => in_array(strtolower($record->status ?? ''), ['completed', 'closed']))
                        ->getStateUsing(function ($record) {
                            // Get the completion log for this work order
                            $completionLog = $record->workOrderLogs()
                                ->whereIn('status', ['Completed', 'Closed'])
                                ->orderBy('updated_at', 'desc')
                                ->first();

                            if (! $completionLog) {
                                return new HtmlString('
                                    <div class="mt-4">
                                        <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                            <h4 class="font-bold text-black dark:text-white">Net Production Throughput (Efficiency Oriented) 1</h4>
                                        </div>
                                        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                            <div class="text-center text-gray-500 dark:text-gray-400">
                                                No completion data available
                                            </div>
                                        </div>
                                    </div>
                                ');
                            }

                            // Get the first Start log entry for this work order
                            $startLog = $record->workOrderLogs()
                                ->where('status', 'Start')
                                ->orderBy('created_at', 'asc')
                                ->first();

                            // If no Start log found, show appropriate message
                            if (! $startLog) {
                                return new HtmlString('
                                    <div class="mt-4">
                                        <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                            <h4 class="font-bold text-black dark:text-white">Net Production Throughput (Efficiency Oriented) 1</h4>
                                        </div>
                                        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                            <div class="text-center text-yellow-500 dark:text-yellow-400">
                                                No production start log available
                                            </div>
                                        </div>
                                    </div>
                                ');
                            }

                            // Calculate time period (first Start log to completion log)
                            $startedAt = Carbon::parse($startLog->created_at);
                            $completedAt = Carbon::parse($completionLog->created_at);

                            // Handle edge cases where completion time might be before start time
                            $hours = $startedAt->diffInHours($completedAt, false); // false = can be negative

                            // If negative hours, it might be a data issue - use absolute value or show as data error
                            if ($hours <= 0) {
                                $hours = abs($hours);
                                $dataNote = ' (Data inconsistency detected)';
                            } else {
                                $dataNote = '';
                            }

                            // Get units produced
                            $units = $record->ok_qtys ?? 0;

                            // Calculate throughput
                            $throughputPerHour = $hours > 0 ? round($units / $hours, 3) : 0;
                            $throughputPerDay = round($throughputPerHour * 24, 1);

                            return new HtmlString('
                                <div class="mt-4">
                                    <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                        <h4 class="font-bold text-black dark:text-white">Net Production Throughput (Efficiency Oriented) 1</h4>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Hourly Rate: '.$throughputPerHour.' units/hr'.$dataNote.'</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">'.$units.' units in '.number_format($hours, 1).' hrs</span>
                                        </div>
                                        <div class="flex justify-between items-center mb-4">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Daily Rate: '.$throughputPerDay.' units/day</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">24-hour equivalent</span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                                            <div>Production Started: '.$startedAt->format('Y-m-d H:i:s').'</div>
                                            <div>Completed: '.$completedAt->format('Y-m-d H:i:s').'</div>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),

                    // Net Production Throughput (Efficiency Oriented) 2 - Excludes Hold Periods
                    TextEntry::make('net_production_throughput_v2')
                        ->label('')
                        ->columnSpan([
                            'sm' => 'full',
                            'md' => 1,
                        ])
                        ->visible(fn ($record) => in_array(strtolower($record->status ?? ''), ['completed', 'closed', 'hold']))
                        ->getStateUsing(function ($record) {
                            // Get all Start, Hold, Completed, and Closed logs in chronological order
                            $logs = $record->workOrderLogs()
                                ->whereIn('status', ['Start', 'Hold', 'Completed', 'Closed'])
                                ->orderBy('created_at', 'asc')
                                ->get();

                            if ($logs->isEmpty()) {
                                return new HtmlString('
                                    <div class="mt-4">
                                        <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                            <h4 class="font-bold text-black dark:text-white">Net Production Throughput (Efficiency Oriented) 2</h4>
                                        </div>
                                        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                            <div class="text-center text-gray-500 dark:text-gray-400">
                                                No production log data available
                                            </div>
                                        </div>
                                    </div>
                                ');
                            }

                            // Calculate net production time (sum of all Start-to-Hold/Completed/Closed periods)
                            $netProductionHours = 0;
                            $lastStartTime = null;
                            $productionPeriods = [];

                            foreach ($logs as $log) {
                                if ($log->status === 'Start') {
                                    // Mark the start of a production period
                                    $lastStartTime = Carbon::parse($log->created_at);
                                } elseif (in_array($log->status, ['Hold', 'Completed', 'Closed']) && $lastStartTime !== null) {
                                    // End of a production period - calculate duration
                                    $endTime = Carbon::parse($log->created_at);
                                    $periodHours = $lastStartTime->diffInHours($endTime, true);

                                    // Add this production period to the total
                                    $netProductionHours += $periodHours;

                                    // Track periods for display
                                    $productionPeriods[] = [
                                        'start' => $lastStartTime,
                                        'end' => $endTime,
                                        'hours' => $periodHours,
                                    ];

                                    // Reset start time (production paused/ended)
                                    $lastStartTime = null;
                                }
                            }

                            // Get units produced from work_orders table
                            $units = $record->ok_qtys ?? 0;

                            // Calculate throughput
                            $throughputPerHour = $netProductionHours > 0 ? round($units / $netProductionHours, 3) : 0;
                            $throughputPerDay = round($throughputPerHour * 24, 1);
                            $periodCount = count($productionPeriods);

                            return new HtmlString('
                                <div class="mt-4">
                                    <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                        <h4 class="font-bold text-black dark:text-white">Net Production Throughput (Efficiency Oriented) 2</h4>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Hourly Rate: '.$throughputPerHour.' units/hr</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">'.$units.' units in '.number_format($netProductionHours, 1).' net hrs</span>
                                        </div>
                                        <div class="flex justify-between items-center mb-4">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Daily Rate: '.$throughputPerDay.' units/day</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">24-hour equivalent</span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                                            <div class="font-semibold mb-1">Production Periods: '.$periodCount.'</div>
                                            <div class="text-xs italic">Excludes hold/pause time</div>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),

                    // Gross Production Throughput (Raw Output-Oriented) 1
                    TextEntry::make('gross_production_throughput')
                        ->label('')
                        ->columnSpan([
                            'sm' => 'full',
                            'md' => 1,
                        ])
                        ->visible(fn ($record) => in_array(strtolower($record->status ?? ''), ['completed', 'closed', 'hold']))
                        ->getStateUsing(function ($record) {
                            // Get the completion/hold log for this work order
                            $endLog = null;

                            if (strtolower($record->status) === 'hold') {
                                $endLog = $record->workOrderLogs()
                                    ->where('status', 'Hold')
                                    ->orderBy('created_at', 'desc')
                                    ->first();
                            } else {
                                $endLog = $record->workOrderLogs()
                                    ->whereIn('status', ['Completed', 'Closed'])
                                    ->orderBy('created_at', 'desc')
                                    ->first();
                            }

                            if (! $endLog) {
                                return new HtmlString('
                                    <div class="mt-4">
                                        <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                            <h4 class="font-bold text-black dark:text-white">Gross Production Throughput (Raw Output-Oriented) 1</h4>
                                        </div>
                                        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                            <div class="text-center text-gray-500 dark:text-gray-400">
                                                No completion/hold data available
                                            </div>
                                        </div>
                                    </div>
                                ');
                            }

                            // Get the first Start log entry for this work order
                            $startLog = $record->workOrderLogs()
                                ->where('status', 'Start')
                                ->orderBy('created_at', 'asc')
                                ->first();

                            // If no Start log found, show appropriate message
                            if (! $startLog) {
                                return new HtmlString('
                                    <div class="mt-4">
                                        <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                            <h4 class="font-bold text-black dark:text-white">Gross Production Throughput (Raw Output-Oriented) 1</h4>
                                        </div>
                                        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                            <div class="text-center text-yellow-500 dark:text-yellow-400">
                                                No production start log available
                                            </div>
                                        </div>
                                    </div>
                                ');
                            }

                            // Calculate time period (first Start log to end log)
                            $startedAt = Carbon::parse($startLog->created_at);
                            $endedAt = Carbon::parse($endLog->created_at);

                            // Handle edge cases where end time might be before start time
                            $hours = $startedAt->diffInHours($endedAt, false); // false = can be negative

                            // If negative hours, use absolute value (data inconsistency)
                            if ($hours <= 0) {
                                $hours = abs($hours);
                                $dataNote = ' (Data inconsistency detected)';
                            } else {
                                $dataNote = '';
                            }

                            // Get GROSS units (OK + Scrapped) from work_orders table
                            $okQty = $record->ok_qtys ?? 0;
                            $scrappedQty = $record->scrapped_qtys ?? 0;
                            $grossUnits = $okQty + $scrappedQty;

                            // Calculate throughput
                            $throughputPerHour = $hours > 0 ? round($grossUnits / $hours, 3) : 0;
                            $throughputPerDay = round($throughputPerHour * 24, 1);

                            return new HtmlString('
                                <div class="mt-4">
                                    <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                        <h4 class="font-bold text-black dark:text-white">Gross Production Throughput (Raw Output-Oriented) 1</h4>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Hourly Rate: '.$throughputPerHour.' units/hr'.$dataNote.'</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">'.$grossUnits.' total units in '.number_format($hours, 1).' hrs</span>
                                        </div>
                                        <div class="flex justify-between items-center mb-4">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Daily Rate: '.$throughputPerDay.' units/day</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">24-hour equivalent</span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                                            <div>Production Started: '.$startedAt->format('Y-m-d H:i:s').'</div>
                                            <div>Ended: '.$endedAt->format('Y-m-d H:i:s').'</div>
                                            <div class="mt-2 text-xs italic">Total Output: '.$okQty.' OK + '.$scrappedQty.' Scrapped</div>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),

                    // Gross Production Throughput (Raw Output-Oriented) 2 - Excludes Hold Periods
                    TextEntry::make('gross_production_throughput_v2')
                        ->label('')
                        ->columnSpan([
                            'sm' => 'full',
                            'md' => 1,
                        ])
                        ->visible(fn ($record) => in_array(strtolower($record->status ?? ''), ['completed', 'closed', 'hold']))
                        ->getStateUsing(function ($record) {
                            // Get all Start, Hold, Completed, and Closed logs in chronological order
                            $logs = $record->workOrderLogs()
                                ->whereIn('status', ['Start', 'Hold', 'Completed', 'Closed'])
                                ->orderBy('created_at', 'asc')
                                ->get();

                            if ($logs->isEmpty()) {
                                return new HtmlString('
                                    <div class="mt-4">
                                        <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                            <h4 class="font-bold text-black dark:text-white">Gross Production Throughput (Raw Output-Oriented) 2</h4>
                                        </div>
                                        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                            <div class="text-center text-gray-500 dark:text-gray-400">
                                                No production log data available
                                            </div>
                                        </div>
                                    </div>
                                ');
                            }

                            // Calculate net production time (sum of all Start-to-Hold/Completed/Closed periods)
                            $netProductionHours = 0;
                            $lastStartTime = null;
                            $productionPeriods = [];

                            foreach ($logs as $log) {
                                if ($log->status === 'Start') {
                                    // Mark the start of a production period
                                    $lastStartTime = Carbon::parse($log->created_at);
                                } elseif (in_array($log->status, ['Hold', 'Completed', 'Closed']) && $lastStartTime !== null) {
                                    // End of a production period - calculate duration
                                    $endTime = Carbon::parse($log->created_at);
                                    $periodHours = $lastStartTime->diffInHours($endTime, true);

                                    // Add this production period to the total
                                    $netProductionHours += $periodHours;

                                    // Track periods for display
                                    $productionPeriods[] = [
                                        'start' => $lastStartTime,
                                        'end' => $endTime,
                                        'hours' => $periodHours,
                                    ];

                                    // Reset start time (production paused/ended)
                                    $lastStartTime = null;
                                }
                            }

                            // Get GROSS units (OK + Scrapped) from work_orders table
                            $okQty = $record->ok_qtys ?? 0;
                            $scrappedQty = $record->scrapped_qtys ?? 0;
                            $grossUnits = $okQty + $scrappedQty;

                            // Calculate throughput
                            $throughputPerHour = $netProductionHours > 0 ? round($grossUnits / $netProductionHours, 3) : 0;
                            $throughputPerDay = round($throughputPerHour * 24, 1);
                            $periodCount = count($productionPeriods);

                            return new HtmlString('
                                <div class="mt-4">
                                    <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                        <h4 class="font-bold text-black dark:text-white">Gross Production Throughput (Raw Output-Oriented) 2</h4>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Hourly Rate: '.$throughputPerHour.' units/hr</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">'.$grossUnits.' total units in '.number_format($netProductionHours, 1).' net hrs</span>
                                        </div>
                                        <div class="flex justify-between items-center mb-4">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Daily Rate: '.$throughputPerDay.' units/day</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">24-hour equivalent</span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                                            <div class="font-semibold mb-1">Production Periods: '.$periodCount.'</div>
                                            <div class="text-xs italic">Excludes hold/pause time</div>
                                            <div class="mt-2 text-xs italic">Total Output: '.$okQty.' OK + '.$scrappedQty.' Scrapped</div>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),

                    // Scrap Rate Section - only show for completed, hold, or closed status
                    TextEntry::make('scrap_rate_section')
                        ->label('')
                        ->columnSpan([
                            'sm' => 'full',
                            'md' => 1,
                        ])
                        ->visible(fn ($record) => in_array(strtolower($record->status ?? ''), ['completed', 'hold', 'closed']))
                        ->getStateUsing(function ($record) {
                            $totalQty = $record->qty ?? 0;
                            $scrappedQty = $record->scrapped_qtys ?? 0;
                            $scrapRate = $totalQty > 0 ? ($scrappedQty / $totalQty) * 100 : 0;
                            $goodRate = 100 - $scrapRate;

                            return new HtmlString('
                                <div class="mt-4">
                                    <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                        <h4 class="font-bold text-black dark:text-white">Scrap Rate</h4>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Scrap Rate: '.number_format($scrapRate, 1).'%</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">'.$scrappedQty.' / '.$totalQty.'</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-6 overflow-hidden">
                                            <div class="flex h-full">
                                                <div class="bg-green-500 dark:bg-green-600 transition-all duration-500" style="width: '.$goodRate.'%"></div>
                                                <div class="bg-red-500 dark:bg-red-600 transition-all duration-500" style="width: '.$scrapRate.'%"></div>
                                            </div>
                                        </div>
                                        <div class="flex justify-between mt-2 text-xs">
                                            <span class="text-green-600 dark:text-green-400">✓ Good: '.number_format($goodRate, 1).'%</span>
                                            <span class="text-red-600 dark:text-red-400">✗ Scrapped: '.number_format($scrapRate, 1).'%</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),

                    // Work Order Aging Section - exclude Hold status
                    TextEntry::make('work_order_aging_section')
                        ->label('')
                        ->columnSpan([
                            'sm' => 'full',
                            'md' => 1,
                        ])
                        ->visible(fn ($record) => strtolower($record->status ?? '') !== 'hold')
                        ->getStateUsing(function ($record) {
                            $currentDate = Carbon::now();
                            $status = $record->status;

                            // Determine the reference date based on work order status
                            if ($status === 'Assigned') {
                                // For Assigned status, use work_orders table created_at
                                $referenceDate = Carbon::parse($record->created_at);
                                $referenceText = 'Work Order Created';
                            } elseif ($status === 'Start') {
                                // For Start status, get the FIRST Start status log for this work order
                                $statusLog = $record->workOrderLogs()
                                    ->where('status', 'Start')
                                    ->orderBy('created_at', 'asc')
                                    ->first();

                                if ($statusLog) {
                                    $referenceDate = Carbon::parse($statusLog->created_at);
                                    $referenceText = 'First Start Status';
                                } else {
                                    // Fallback to work order created_at if no Start log found
                                    $referenceDate = Carbon::parse($record->created_at);
                                    $referenceText = 'Work Order Created (No Start Log)';
                                }
                            } elseif (in_array($status, ['Completed', 'Closed'])) {
                                // For Completed/Closed status, get the created_at from work_order_logs
                                $statusLog = $record->workOrderLogs()
                                    ->where('status', $status)
                                    ->orderBy('created_at', 'desc')
                                    ->first();

                                if ($statusLog) {
                                    $referenceDate = Carbon::parse($statusLog->created_at);
                                    $referenceText = 'Status Changed to '.$status;
                                } else {
                                    // Fallback to work order created_at if no status log found
                                    $referenceDate = Carbon::parse($record->created_at);
                                    $referenceText = 'Work Order Created (No '.$status.' Log)';
                                }
                            } else {
                                // For any other statuses, get the created_at from work_order_logs when status changed
                                $statusLog = $record->workOrderLogs()
                                    ->where('status', $status)
                                    ->orderBy('created_at', 'asc')
                                    ->first();

                                if ($statusLog) {
                                    $referenceDate = Carbon::parse($statusLog->created_at);
                                    $referenceText = 'Status Changed to '.$status;
                                } else {
                                    // Fallback to work order created_at if no status log found
                                    $referenceDate = Carbon::parse($record->created_at);
                                    $referenceText = 'Work Order Created (No Status Log)';
                                }
                            }

                            // Calculate aging and round to nearest hour
                            $agingInHours = round($referenceDate->diffInHours($currentDate));
                            $agingInDays = round($referenceDate->diffInDays($currentDate));

                            // Format aging display
                            $agingText = '';
                            if ($agingInDays > 0) {
                                $remainingHours = round($agingInHours % 24);
                                $agingText = $agingInDays.' day'.($agingInDays > 1 ? 's' : '').
                                           ($remainingHours > 0 ? ', '.$remainingHours.' hr'.($remainingHours > 1 ? 's' : '') : '');
                            } else {
                                $agingText = $agingInHours.' hr'.($agingInHours > 1 ? 's' : '');
                            }

                            // Remove aging status classification - no status display needed

                            return new HtmlString('
                                <div class="mt-4">
                                    <div class="bg-primary-500 dark:bg-primary-700 text-white px-4 py-2 rounded-t-lg">
                                        <h4 class="font-bold text-black dark:text-white">Work Order Aging</h4>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-b-lg p-4">
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Age:</span>
                                                <span class="text-sm font-bold text-gray-700 dark:text-gray-300">'.$agingText.'</span>
                                            </div>

                                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                                <div class="text-xs text-gray-500 dark:text-gray-500">
                                                    <div>Reference: '.$referenceText.'</div>
                                                    <div>Since: '.$referenceDate->format('Y-m-d H:i:s').'</div>
                                                    <div>Current Status: '.ucfirst($status).'</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),

                ]),

            // Section 2: General Information
            Section::make('General Information')
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('general_information_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $bom = $record->bom->unique_id ?? 'N/A';
                            $qty = $record->qty ?? 'N/A';
                            $machine = $record->machine
                                ? $record->machine->assetId.' - '.$record->machine->name
                                : 'No Machine';
                            $operator = $record->operator->user->first_name ?? 'N/A';

                            return new HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">BOM</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Quantity</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Machine</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Operator</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($bom).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($qty).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($machine).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($operator).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4 overflow-hidden">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        General Information
                                    </div>
                                    <div class="p-4 space-y-3 max-w-full overflow-x-auto">
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">BOM:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($bom).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Quantity:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($qty).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Machine:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($machine).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Operator:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($operator).'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            // Section 3: Details
            Section::make('Details')
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('details_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $uniqueId = $record->unique_id ?? 'N/A';
                            $partNumber = $record->bom->purchaseorder->partnumber->partnumber ?? 'N/A';
                            $revision = $record->bom->purchaseorder->partnumber->revision ?? 'N/A';
                            $status = $record->status ?? 'N/A';
                            $endTimeRaw = $record->end_time;
                            $startTime = $record->start_time ? Carbon::parse($record->start_time)->format('Y-m-d H:i') : 'N/A';
                            $endTime = $record->end_time ? Carbon::parse($record->end_time)->format('Y-m-d H:i') : 'N/A';
                            $endTimeCell = htmlspecialchars($endTime);
                            if ($record->bom && $record->bom->lead_time && $endTimeRaw) {
                                $plannedEnd = Carbon::parse($endTimeRaw);
                                $bomLead = Carbon::parse($record->bom->lead_time)->endOfDay();
                                if ($plannedEnd->greaterThan($bomLead)) {
                                    $bomLeadFormatted = Carbon::parse($record->bom->lead_time)->format('d M Y');
                                    $endTimeCell = '<span class="bg-red-100 dark:bg-red-900 dark:text-red-200" style="cursor:pointer;" title="BOM Target Completion Time: '.$bomLeadFormatted.'">'.htmlspecialchars($endTime).'</span>';
                                }
                            }
                            $okQty = $record->ok_qtys ?? 'N/A';
                            $scrapQty = $record->scrapped_qtys ?? 'N/A';
                            $materialBatch = $record->material_batch ?? 'N/A';

                            return new HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Unique ID</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Part Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Revision</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Status</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Planned Start Time</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Planned End Time</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">OK Quantities</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Scrapped Quantities</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Material Batch ID</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($uniqueId).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($partNumber).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($revision).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($status).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($startTime).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$endTimeCell.'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-green-600 dark:text-green-400">'.htmlspecialchars($okQty).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-red-600 dark:text-red-400">'.htmlspecialchars($scrapQty).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($materialBatch).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4 overflow-hidden">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Details
                                    </div>
                                    <div class="p-4 space-y-3 max-w-full overflow-x-auto">
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Unique ID:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($uniqueId).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Part Number:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($partNumber).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Revision:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($revision).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Status:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($status).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Planned Start Time:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($startTime).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Planned End Time:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.$endTimeCell.'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">OK Quantities:</span>
                                            <span class="text-green-600 dark:text-green-400 break-all sm:text-right sm:ml-2">'.htmlspecialchars($okQty).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Scrapped Quantities:</span>
                                            <span class="text-red-600 dark:text-red-400 break-all sm:text-right sm:ml-2">'.htmlspecialchars($scrapQty).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Material Batch ID:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($materialBatch).'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            // Section 4: Documents
            Section::make('Documents')
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('documents_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $requirementLinks = 'No BOM associated';
                            $flowchartLinks = 'No BOM associated';

                            if ($record->bom) {
                                $requirementMedia = $record->bom->getMedia('requirement_pkg');
                                $flowchartMedia = $record->bom->getMedia('process_flowchart');

                                $requirementLinks = $requirementMedia->isEmpty()
                                    ? 'No files uploaded'
                                    : $requirementMedia->map(function ($media) {
                                        return "<a href='{$media->getUrl()}' target='_blank' class='block text-blue-500 dark:text-blue-400 underline'>{$media->file_name}</a>";
                                    })->implode('<br>');

                                $flowchartLinks = $flowchartMedia->isEmpty()
                                    ? 'No files uploaded'
                                    : $flowchartMedia->map(function ($media) {
                                        return "<a href='{$media->getUrl()}' target='_blank' class='block text-blue-500 dark:text-blue-400 underline'>{$media->file_name}</a>";
                                    })->implode('<br>');
                            }

                            return new HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-left bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Requirement Package</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Process Flowchart</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 align-top">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700">'.$requirementLinks.'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700">'.$flowchartLinks.'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Documents
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Requirement Package: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.$requirementLinks.'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Process Flowchart: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.$flowchartLinks.'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            // Section 5: Work Order Logs
            Section::make('Work Order Logs')
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('work_order_logs_table')
                        ->label('Work Order Logs')
                        ->getStateUsing(function ($record) {
                            $record->load([
                                'workOrderLogs' => function ($query) {
                                    $query->orderBy('created_at', 'asc');
                                },
                                'workOrderLogs.user',
                                'quantities',
                            ]);
                            $quantities = $record->quantities;
                            $quantitiesIndex = 0;

                            $htmlRows = '';
                            foreach ($record->workOrderLogs as $log) {
                                $status = $log->status;
                                $user = $log->user ? $log->user->first_name.' '.$log->user->last_name : 'N/A';
                                $timestamp = $log->created_at->format('Y-m-d H:i:s');
                                $okQty = '';
                                $scrappedQty = '';
                                $remainingQty = '';
                                $scrappedReason = '';
                                $fpy = $log->fpy !== null ? number_format($log->fpy, 2) : '';
                                $documents = '';

                                if (in_array($status, ['Hold', 'Completed'])) {
                                    if (isset($quantities[$quantitiesIndex])) {
                                        $quantity = $quantities[$quantitiesIndex];
                                        $okQty = $quantity->ok_quantity;
                                        $scrappedQty = $quantity->scrapped_quantity;

                                        $cumulativeOkQty = 0;
                                        $cumulativeScrappedQty = 0;
                                        for ($i = 0; $i <= $quantitiesIndex; $i++) {
                                            if (isset($quantities[$i])) {
                                                $cumulativeOkQty += $quantities[$i]->ok_quantity;
                                                $cumulativeScrappedQty += $quantities[$i]->scrapped_quantity;
                                            }
                                        }
                                        $remainingQty = $record->qty - ($cumulativeOkQty + $cumulativeScrappedQty);

                                        if ($quantity->scrapped_quantity > 0) {
                                            $scrappedReason = $quantity->reason->description;
                                        }

                                        $qrCodeMedia = $quantity->getMedia('qr_code')->first();
                                        if ($qrCodeMedia) {
                                            $documents .= "<a href='{$qrCodeMedia->getUrl()}' download='qr_code.png' class='text-blue-500 dark:text-blue-400 underline'>Download QR Code</a>";
                                        }

                                        $quantitiesIndex++;
                                    }
                                }

                                $htmlRows .= '
                                    <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($status).'</td>
                                        <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($user).'</td>
                                        <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($timestamp).'</td>
                                        <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-green-600 dark:text-green-400">'.e($okQty).'</td>
                                        <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-red-600 dark:text-red-400">'.e($scrappedQty).'</td>
                                        <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($remainingQty).'</td>
                                        <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($scrappedReason).'</td>
                                        <td class="border border-gray-300 dark:border-gray-700 px-2 py-1">'.$documents.'</td>
                                        <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($fpy).'</td>
                                    </tr>';
                            }

                            // Mobile logs rendering
                            $mobileLogs = '';
                            $quantitiesIndex = 0;
                            foreach ($record->workOrderLogs as $index => $log) {
                                $status = $log->status;
                                $user = $log->user ? $log->user->first_name.' '.$log->user->last_name : 'N/A';
                                $timestamp = $log->created_at->format('Y-m-d H:i:s');
                                $okQty = '';
                                $scrappedQty = '';
                                $remainingQty = '';
                                $scrappedReason = '';
                                $fpy = $log->fpy !== null ? number_format($log->fpy, 2) : '';
                                $documents = '';
                                if (in_array($status, ['Hold', 'Completed'])) {
                                    if (isset($record->quantities[$quantitiesIndex])) {
                                        $quantity = $record->quantities[$quantitiesIndex];
                                        $okQty = $quantity->ok_quantity;
                                        $scrappedQty = $quantity->scrapped_quantity;
                                        $cumulativeOkQty = 0;
                                        $cumulativeScrappedQty = 0;
                                        for ($i = 0; $i <= $quantitiesIndex; $i++) {
                                            if (isset($record->quantities[$i])) {
                                                $cumulativeOkQty += $record->quantities[$i]->ok_quantity;
                                                $cumulativeScrappedQty += $record->quantities[$i]->scrapped_quantity;
                                            }
                                        }
                                        $remainingQty = $record->qty - ($cumulativeOkQty + $cumulativeScrappedQty);
                                        if ($quantity->scrapped_quantity > 0) {
                                            $scrappedReason = $quantity->reason->description;
                                        }
                                        $qrCodeMedia = $quantity->getMedia('qr_code')->first();
                                        if ($qrCodeMedia) {
                                            $documents .= "<a href='{$qrCodeMedia->getUrl()}' download='qr_code.png' class='text-blue-500 dark:text-blue-400 underline'>Download QR Code</a>";
                                        }
                                        $quantitiesIndex++;
                                    }
                                }
                                $mobileLogs .= '
                                    <div class="border-b border-gray-200 dark:border-gray-700 pb-2 mb-2">
                                        <div><span class="font-bold">Status:</span> <span>'.htmlspecialchars($status).'</span></div>
                                        <div><span class="font-bold">User:</span> <span>'.htmlspecialchars($user).'</span></div>
                                        <div><span class="font-bold">Timestamp:</span> <span>'.htmlspecialchars($timestamp).'</span></div>
                                        <div><span class="font-bold">OK QTY:</span> <span class="text-green-600 dark:text-green-400">'.htmlspecialchars($okQty).'</span></div>
                                        <div><span class="font-bold">Scrapped QTY:</span> <span class="text-red-600 dark:text-red-400">'.htmlspecialchars($scrappedQty).'</span></div>
                                        <div><span class="font-bold">Remaining QTY:</span> <span>'.htmlspecialchars($remainingQty).'</span></div>
                                        <div><span class="font-bold">Scrapped Reason:</span> <span>'.htmlspecialchars($scrappedReason).'</span></div>
                                        <div><span class="font-bold">Documents:</span> <span>'.$documents.'</span></div>
                                        <div><span class="font-bold">FPY (%):</span> <span>'.htmlspecialchars($fpy).'</span></div>
                                    </div>
                                ';
                            }
                            if (empty($mobileLogs)) {
                                $mobileLogs = '<span class="text-gray-900 dark:text-gray-100">No logs found.</span>';
                            }

                            return new HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="table-auto w-full text-left border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">Status</th>
                                                <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">User</th>
                                                <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">Timestamp</th>
                                                <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">OK QTY</th>
                                                <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">Scrapped QTY</th>
                                                <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">Remaining QTY</th>
                                                <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">Scrapped Reason</th>
                                                <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">Documents</th>
                                                <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">FPY (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            '.$htmlRows.'
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Work Order Logs
                                    </div>
                                    <div class="p-4 space-y-3">
                                        '.$mobileLogs.'
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            // Section 6: Work Order Info Messages
            Section::make('Work Order Info Messages')
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('info_messages_table')
                        ->label('Info Messages')
                        ->getStateUsing(function ($record) {
                            $record->load('infoMessages.user');
                            $messages = $record->infoMessages->map(function ($message) {
                                return [
                                    'user' => $message->user->getFilamentname() ?? 'N/A',
                                    'message' => $message->message,
                                    'priority' => ucfirst($message->priority),
                                    'sent_at' => $message->created_at->format('Y-m-d H:i:s'),
                                ];
                            });

                            $htmlRows = '';
                            foreach ($messages as $message) {
                                $priorityClass = $message['priority'] === 'High'
                                    ? 'text-red-500 dark:text-red-400'
                                    : ($message['priority'] === 'Medium'
                                        ? 'text-yellow-500 dark:text-yellow-400'
                                        : 'text-green-600 dark:text-green-400');
                                $htmlRows .= '
                        <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($message['user']).'</td>
                            <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($message['message']).'</td>
                            <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold '.$priorityClass.'">'.e($message['priority']).'</td>
                            <td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($message['sent_at']).'</td>
                        </tr>';
                            }

                            // Mobile card rendering
                            $mobileRows = '';
                            foreach ($messages as $message) {
                                $priorityClass = $message['priority'] === 'High'
                                    ? 'text-red-500 dark:text-red-400'
                                    : ($message['priority'] === 'Medium'
                                        ? 'text-yellow-500 dark:text-yellow-400'
                                        : 'text-green-600 dark:text-green-400');
                                $mobileRows .= '
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-2 mb-2">
                            <div><span class="font-bold">User:</span> <span>'.htmlspecialchars($message['user']).'</span></div>
                            <div><span class="font-bold">Message:</span> <span>'.htmlspecialchars($message['message']).'</span></div>
                            <div><span class="font-bold">Priority:</span> <span class="'.$priorityClass.'">'.htmlspecialchars($message['priority']).'</span></div>
                            <div><span class="font-bold">Sent At:</span> <span>'.htmlspecialchars($message['sent_at']).'</span></div>
                        </div>
                    ';
                            }
                            if (empty($mobileRows)) {
                                $mobileRows = '<span class="text-gray-900 dark:text-gray-100">No info messages found.</span>';
                            }

                            return new HtmlString('
                    <!-- Desktop Table -->
                    <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                        <table class="table-auto w-full text-left border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                            <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                <tr>
                                    <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">User</th>
                                    <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">Message</th>
                                    <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">Priority</th>
                                    <th class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold text-black dark:text-white">Sent At</th>
                                </tr>
                            </thead>
                            <tbody>
                                '.$htmlRows.'
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile Card -->
                    <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                        <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                            Info Messages
                        </div>
                        <div class="p-4 space-y-3">
                            '.$mobileRows.'
                        </div>
                    </div>
                ');
                        })->html(),
                ]),
        ]);
    }
}
