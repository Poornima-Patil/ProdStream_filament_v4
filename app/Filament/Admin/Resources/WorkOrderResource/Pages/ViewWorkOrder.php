<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use App\Filament\Admin\Resources\WorkOrderResource\Widgets\WorkOrderProgress;
use App\Filament\Admin\Resources\WorkOrderResource\Widgets\WorkOrderQtyTrendChart;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ViewWorkOrder extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    public function infoList(Infolist $infoList): Infolist
    {
        $user = Auth::user();
        $isAdminOrManager = $user && in_array($user->role, ['manager', 'admin']);

        return $infoList
            ->schema([
                // Section 1: BOM, Quantity, Machines, Operator
                Section::make('General Information')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('general_information_table')
                            ->hiddenLabel()
                            ->getStateUsing(function ($record) {
                                $bom = $record->bom->unique_id ?? 'N/A';
                                $qty = $record->qty ?? 'N/A';
                                $machine = $record->machine
                                    ? $record->machine->assetId.' - '.$record->machine->name
                                    : 'No Machine';
                                $operator = $record->operator->user->first_name ?? 'N/A';

                                return '
                                <div class="overflow-x-auto rounded-lg shadow-md">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">BOM</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Quantity</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Machine</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Operator</th>
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
                                </div>';
                            })
                            ->html(),
                    ]),

                // Section 2: Remaining fields
                Section::make('Details')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('details_table')
                            ->hiddenLabel()
                            ->getStateUsing(function ($record) {
                                $uniqueId = $record->unique_id ?? 'N/A';
                                $partNumber = $record->bom->purchaseorder->partnumber->partnumber ?? 'N/A';
                                $revision = $record->bom->purchaseorder->partnumber->revision ?? 'N/A';
                                $status = $record->status ?? 'N/A';
                                $endTimeRaw = $record->end_time;
                                $startTime = $record->start_time ? \Carbon\Carbon::parse($record->start_time)->format('Y-m-d H:i') : 'N/A';
                                $endTime = $record->end_time ? \Carbon\Carbon::parse($record->end_time)->format('Y-m-d H:i') : 'N/A';
                                $endTimeCell = '<td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($endTime).'</td>';
                                if ($record->bom && $record->bom->lead_time && $endTimeRaw) {
                                    $plannedEnd = \Carbon\Carbon::parse($endTimeRaw);
                                    $bomLead = \Carbon\Carbon::parse($record->bom->lead_time)->endOfDay();
                                    if ($plannedEnd->greaterThan($bomLead)) {
                                        $bomLeadFormatted = \Carbon\Carbon::parse($record->bom->lead_time)->format('d M Y');
$endTimeCell = '<td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 bg-red-100 dark:bg-red-900 dark:text-red-200" style="cursor:pointer;" title="BOM Target Completion Time: '.$bomLeadFormatted.'">'.htmlspecialchars($endTime).'</td>';                                    }
                                }
                                $okQty = $record->ok_qtys ?? 'N/A';
                                $scrapQty = $record->scrapped_qtys ?? 'N/A';
                                $materialBatch = $record->material_batch ?? 'N/A';

                                return '
                                <div class="overflow-x-auto rounded-lg shadow-md">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Unique ID</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Part Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Revision</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Status</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Planned Start Time</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Planned End Time</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">OK Quantities</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Scrapped Quantities</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Material Batch ID</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($uniqueId).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($partNumber).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($revision).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($status).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($startTime).'</td>
                                                '.$endTimeCell.'
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-green-600 dark:text-green-400">'.htmlspecialchars($okQty).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-red-600 dark:text-red-400">'.htmlspecialchars($scrapQty).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($materialBatch).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>';
                            })
                            ->html(),
                    ]),

                Section::make('Documents')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('documents_table')
                            ->hiddenLabel()
                            ->getStateUsing(function ($record) {
                                // Requirement Package links
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

                                return '
                                    <div class="overflow-x-auto rounded-lg shadow-md">
                                        <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-left bg-white dark:bg-gray-900">
                                            <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                                <tr>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Requirement Package</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Process Flowchart</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 align-top">
                                                    <td class="p-2 border border-gray-300 dark:border-gray-700">'.$requirementLinks.'</td>
                                                    <td class="p-2 border border-gray-300 dark:border-gray-700">'.$flowchartLinks.'</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>';
                            })
                            ->html(),
                    ]),

                Section::make('Work Order Logs')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('work_order_logs_table')
                            ->label('Work Order Logs')
                            ->state(function ($record) {
                                // Load necessary relationships
                                $record->load(['workOrderLogs.user', 'quantities']);
                                $quantities = $record->quantities;
                                $quantitiesIndex = 0;

                                // Create the HTML table
                                $html = '<table class="table-auto w-full text-left border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">';
                                $html .= '<thead class="bg-primary-500 dark:bg-primary-700 text-white"><tr>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">Status</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">User</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">Timestamp</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">OK QTY</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">Scrapped QTY</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">Remaining QTY</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">Scrapped Reason</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">Documents</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">FPY (%)</th>';
                                $html .= '</tr></thead><tbody>';

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

                                            // Calculate cumulative quantities up to this cycle
                                            $cumulativeOkQty = 0;
                                            $cumulativeScrappedQty = 0;

                                            // Sum up quantities from previous cycles
                                            for ($i = 0; $i <= $quantitiesIndex; $i++) {
                                                if (isset($quantities[$i])) {
                                                    $cumulativeOkQty += $quantities[$i]->ok_quantity;
                                                    $cumulativeScrappedQty += $quantities[$i]->scrapped_quantity;
                                                }
                                            }

                                            // Calculate remaining quantity based on cumulative quantities
                                            $remainingQty = $record->qty - ($cumulativeOkQty + $cumulativeScrappedQty);

                                            // Add detailed logging
                                            \Log::info('Work Order Quantity Calculation', [
                                                'work_order_id' => $record->id,
                                                'cycle_index' => $quantitiesIndex,
                                                'total_qty' => $record->qty,
                                                'current_ok_qty' => $okQty,
                                                'current_scrapped_qty' => $scrappedQty,
                                                'cumulative_ok_qty' => $cumulativeOkQty,
                                                'cumulative_scrapped_qty' => $cumulativeScrappedQty,
                                                'calculated_remaining_qty' => $remainingQty,
                                                'timestamp' => $timestamp,
                                                'status' => $status,
                                            ]);

                                            if ($quantity->scrapped_quantity > 0) {
                                                $scrappedReason = $quantity->reason->description;
                                            }

                                            // Get QR code and PDF links
                                            $qrCodeMedia = $quantity->getMedia('qr_code')->first();

                                            if ($qrCodeMedia) {
                                                $documents .= "<a href='{$qrCodeMedia->getUrl()}' download='qr_code.png' class='text-blue-500 dark:text-blue-400 underline'>Download QR Code</a>";
                                            }

                                            $quantitiesIndex++;
                                        }
                                    }

                                    $html .= '<tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($status).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($user).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($timestamp).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-green-600 dark:text-green-400">'.e($okQty).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-red-600 dark:text-red-400">'.e($scrappedQty).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($remainingQty).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($scrappedReason).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1">'.$documents.'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($fpy).'</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table>';

                                return $html;
                            })
                            ->html(),
                    ])
                    ->columns(1),

                Section::make('Work Order Info Messages')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('info_messages_table')
                            ->label('Info Messages')
                            ->state(function ($record) {
                                // Ensure info messages are loaded with user details
                                $record->load('infoMessages.user');

                                $messages = $record->infoMessages->map(function ($message) {
                                    return [
                                        'user' => $message->user->getFilamentname() ?? 'N/A',
                                        'message' => $message->message,
                                        'priority' => ucfirst($message->priority),
                                        'sent_at' => $message->created_at->format('Y-m-d H:i:s'),
                                    ];
                                });

                                // Create the HTML table
                                $html = '<table class="table-auto w-full text-left border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">';
                                $html .= '<thead class="bg-primary-500 dark:bg-primary-700 text-white"><tr>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">User</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">Message</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">Priority</th>';
                                $html .= '<th class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">Sent At</th>';
                                $html .= '</tr></thead><tbody>';

                                foreach ($messages as $message) {
                                    $priorityClass = $message['priority'] === 'High'
                                        ? 'text-red-500 dark:text-red-400'
                                        : ($message['priority'] === 'Medium'
                                            ? 'text-yellow-500 dark:text-yellow-400'
                                            : 'text-green-600 dark:text-green-400');
                                    $html .= '<tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($message['user']).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($message['message']).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 font-bold '.$priorityClass.'">'.e($message['priority']).'</td>';
                                    $html .= '<td class="border border-gray-300 dark:border-gray-700 px-2 py-1 text-gray-900 dark:text-gray-100">'.e($message['sent_at']).'</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table>';

                                return $html;
                            })
                            ->html(),
                    ])
                    ->columns(1),

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
