<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use App\Filament\Admin\Resources\WorkOrderResource\Widgets\WorkOrderProgress;
use App\Filament\Admin\Resources\WorkOrderResource\Widgets\WorkOrderQtyTrendChart;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewWorkOrder extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        $user = Auth::user();
        $isAdminOrManager = $user && in_array($user->role, ['manager', 'admin']);

        return $infolist->schema([
            // Section 1: General Information
            Section::make('General Information')
                ->collapsible()
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

                            return new \Illuminate\Support\HtmlString('
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
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        General Information
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <span class="font-bold text-black dark:text-white">BOM: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($bom).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Quantity: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($qty).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Machine: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($machine).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Operator: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($operator).'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            // Section 2: Details
            Section::make('Details')
                ->collapsible()
                ->schema([
                    TextEntry::make('details_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $uniqueId = $record->unique_id ?? 'N/A';
                            $partNumber = $record->bom->purchaseorder->partnumber->partnumber ?? 'N/A';
                            $revision = $record->bom->purchaseorder->partnumber->revision ?? 'N/A';
                            $status = $record->status ?? 'N/A';
                            $endTimeRaw = $record->end_time;
                            $startTime = $record->start_time ? \Carbon\Carbon::parse($record->start_time)->format('Y-m-d H:i') : 'N/A';
                            $endTime = $record->end_time ? \Carbon\Carbon::parse($record->end_time)->format('Y-m-d H:i') : 'N/A';
                            $endTimeCell = htmlspecialchars($endTime);
                            if ($record->bom && $record->bom->lead_time && $endTimeRaw) {
                                $plannedEnd = \Carbon\Carbon::parse($endTimeRaw);
                                $bomLead = \Carbon\Carbon::parse($record->bom->lead_time)->endOfDay();
                                if ($plannedEnd->greaterThan($bomLead)) {
                                    $bomLeadFormatted = \Carbon\Carbon::parse($record->bom->lead_time)->format('d M Y');
                                    $endTimeCell = '<span class="bg-red-100 dark:bg-red-900 dark:text-red-200" style="cursor:pointer;" title="BOM Target Completion Time: '.$bomLeadFormatted.'">'.htmlspecialchars($endTime).'</span>';
                                }
                            }
                            $okQty = $record->ok_qtys ?? 'N/A';
                            $scrapQty = $record->scrapped_qtys ?? 'N/A';
                            $materialBatch = $record->material_batch ?? 'N/A';

                            return new \Illuminate\Support\HtmlString('
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
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Details
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Unique ID: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($uniqueId).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Part Number: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($partNumber).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Revision: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($revision).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Status: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($status).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Planned Start Time: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($startTime).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Planned End Time: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.$endTimeCell.'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">OK Quantities: </span>
                                            <span class="text-green-600 dark:text-green-400">'.htmlspecialchars($okQty).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Scrapped Quantities: </span>
                                            <span class="text-red-600 dark:text-red-400">'.htmlspecialchars($scrapQty).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Material Batch ID: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($materialBatch).'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            // Section 3: Documents
            Section::make('Documents')
                ->collapsible()
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

                            return new \Illuminate\Support\HtmlString('
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

            // Section 4: Work Order Logs
            Section::make('Work Order Logs')
                ->collapsible()
                ->schema([
                    TextEntry::make('work_order_logs_table')
                        ->label('Work Order Logs')
                        ->getStateUsing(function ($record) {
                            $record->load(['workOrderLogs.user', 'quantities']);
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

                            return new \Illuminate\Support\HtmlString('
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

            // Section 5: Work Order Info Messages
            Section::make('Work Order Info Messages')
    ->collapsible()
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

                return new \Illuminate\Support\HtmlString('
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

    public function getHeaderWidgets(): array
    {
        return [
            WorkOrderProgress::class,
            WorkOrderQtyTrendChart::make(['workOrder' => $this->record]),
        ];
    }
}
