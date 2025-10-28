<?php

namespace App\Filament\Admin\Resources\PurchaseorderResource\Pages;

use App\Enums\BomStatus;
use App\Filament\Admin\Resources\PurchaseorderResource;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewPurchaseorder extends ViewRecord
{
    protected static string $resource = PurchaseorderResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([

            /*
             * =========================
             * SALES ORDER DETAILS
             * =========================
             */
            Section::make('Sales Order Details')
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('so_details')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $completedQty = $record->boms?->flatMap->workOrders->sum('ok_qtys') ?? 0;
                            $scrappedQty = $record->boms?->flatMap->workOrders->sum('scrapped_qtys') ?? 0;
                            $requestedQty = $record->QTY ?? 0;
                            $progressPercent = $requestedQty > 0 ? round(($completedQty / $requestedQty) * 100) : 0;

                            $progressColor = $progressPercent >= 50 ? 'bg-emerald-500 dark:bg-emerald-400' : 'bg-amber-500 dark:bg-amber-400';
                            $textColor = 'text-white dark:text-gray-900';

                            return new HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block lg:overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">SO Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Customer</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Part Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Target Completion Date</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Requested Qty</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Completed Qty</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Scrapped Qty</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Progress</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->unique_id).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->customer->name ?? '-').'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->partNumber->partnumber ?? '-').'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.($record->delivery_target_date ? Carbon::parse($record->delivery_target_date)->format('Y-m-d') : '-').'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$requestedQty.'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$completedQty.'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$scrappedQty.'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700">
                                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                                                        <div class="h-4 '.$progressColor.' '.$textColor.' rounded-full text-xs font-medium flex items-center justify-center transition-all duration-300" style="width:'.$progressPercent.'%; min-width:2rem;">'.$progressPercent.'%</div>
                                                    </div>
                                                </td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->status).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Sales Order Details
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div><span class="font-bold text-black dark:text-white">SO Number: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->unique_id).'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Customer: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->customer->name ?? '-').'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Part Number: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->partNumber->partnumber ?? '-').'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Target Completion: </span><span class="text-gray-900 dark:text-gray-100">'.($record->delivery_target_date ? Carbon::parse($record->delivery_target_date)->format('Y-m-d') : '-').'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Requested Qty: </span><span class="text-gray-900 dark:text-gray-100">'.$requestedQty.'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Completed Qty: </span><span class="text-gray-900 dark:text-gray-100">'.$completedQty.'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Scrapped Qty: </span><span class="text-gray-900 dark:text-gray-100">'.$scrappedQty.'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Progress: </span><span class="text-gray-900 dark:text-gray-100">'.$progressPercent.'%</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Status: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->status).'</span></div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            /*
             * =========================
             * BILLS OF MATERIALS
             * =========================
             */
            Section::make('Bills of Materials')
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('boms_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (! $record->boms || $record->boms->isEmpty()) {
                                return '<div class="text-gray-500 dark:text-gray-400">No BOMs found.</div>';
                            }

                            $table = '
                                <!-- Desktop Table -->
                                <div class="hidden lg:block lg:overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">BOM Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Description</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>';

                            foreach ($record->boms as $bom) {
                                $statusClass = match ($bom->status) {
                                    'Completed' => 'text-green-600 dark:text-green-400',
                                    'Start' => 'text-yellow-600 dark:text-yellow-400',
                                    'Hold' => 'text-red-600 dark:text-red-400',
                                    default => 'text-gray-600 dark:text-gray-400',
                                };
                                $statusLabel = BomStatus::tryFrom($bom->status)?->label() ?? $bom->status;

                                $table .= '
                                    <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($bom->unique_id).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->partnumber->description).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 '.$statusClass.'">'.htmlspecialchars($statusLabel).'</td>
                                    </tr>';
                            }

                            $table .= '</tbody></table></div>';

                            // Mobile Cards
                            $cards = '<div class="block lg:hidden space-y-4 mt-4">';
                            foreach ($record->boms as $bom) {
                                $statusLabel = BomStatus::tryFrom($bom->status)?->label() ?? $bom->status;
                                $cards .= '
                                    <div class="bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700">
                                        <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                            BOM Details
                                        </div>
                                        <div class="p-4 space-y-3">
                                            <div><span class="font-bold text-black dark:text-white">BOM Number: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($bom->unique_id).'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">Description: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->partnumber->description).'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">Status: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($statusLabel).'</span></div>
                                        </div>
                                    </div>';
                            }
                            $cards .= '</div>';

                            return $table.$cards;
                        })->html(),
                ]),

            /*
             * =========================
             * WORK ORDERS
             * =========================
             */
            Section::make('Work Orders')
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('workOrders')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (! $record->workOrders || $record->workOrders->isEmpty()) {
                                return '<div class="text-gray-500 dark:text-gray-400">No Work Orders found.</div>';
                            }

                            // Desktop Table
                            $table = '
                                <div class="hidden lg:block lg:overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">WO Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">BOM Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Qty</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Machine ID</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Start Time</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">End Time</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Status</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">OK Qty</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Scrapped Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>';

                            foreach ($record->workOrders as $wo) {
                                $startTime = $wo->start_time ? Carbon::parse($wo->start_time)->format('Y-m-d H:i') : '-';
                                $endTime = $wo->end_time ? Carbon::parse($wo->end_time)->format('Y-m-d H:i') : '-';

                                $table .= '
                                    <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($wo->unique_id).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">BOM-'.htmlspecialchars($wo->bom_id).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($wo->qty).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($wo->machine_id).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$startTime.'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$endTime.'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($wo->status).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-green-600 dark:text-green-400">'.htmlspecialchars($wo->ok_qtys).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-red-600 dark:text-red-400">'.htmlspecialchars($wo->scrapped_qtys).'</td>
                                    </tr>';
                            }

                            $table .= '</tbody></table></div>';

                            // Mobile Cards
                            $cards = '<div class="block lg:hidden space-y-4 mt-4">';
                            foreach ($record->workOrders as $wo) {
                                $startTime = $wo->start_time ? Carbon::parse($wo->start_time)->format('Y-m-d H:i') : '-';
                                $endTime = $wo->end_time ? Carbon::parse($wo->end_time)->format('Y-m-d H:i') : '-';

                                $cards .= '
                                    <div class="bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700">
                                        <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                            Work Order Details
                                        </div>
                                        <div class="p-4 space-y-3">
                                            <div><span class="font-bold text-black dark:text-white">WO Number: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($wo->unique_id).'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">BOM Number: </span><span class="text-gray-900 dark:text-gray-100">BOM-'.htmlspecialchars($wo->bom_id).'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">Qty: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($wo->qty).'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">Machine ID: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($wo->machine_id).'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">Start Time: </span><span class="text-gray-900 dark:text-gray-100">'.$startTime.'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">End Time: </span><span class="text-gray-900 dark:text-gray-100">'.$endTime.'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">Status: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($wo->status).'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">OK Qty: </span><span class="text-green-600 dark:text-green-400">'.htmlspecialchars($wo->ok_qtys).'</span></div>
                                            <div><span class="font-bold text-black dark:text-white">Scrapped Qty: </span><span class="text-red-600 dark:text-red-400">'.htmlspecialchars($wo->scrapped_qtys).'</span></div>
                                        </div>
                                    </div>';
                            }
                            $cards .= '</div>';

                            return $table.$cards;
                        })->html(),
                ]),
        ]);
    }
}
