<?php

namespace App\Filament\Admin\Resources\BomResource\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;
use Carbon\Carbon;
use App\Filament\Admin\Resources\BomResource;
use App\Enums\BomStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;

class ViewBom extends ViewRecord
{
    protected static string $resource = BomResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Sale Order Details')
                ->collapsible()->columnSpanFull()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('po_details_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $Unique_ID     = $record->purchaseOrder->unique_id ?? '';
                            $Sales_Order   = $record->purchaseOrder->partNumber->description ?? '';
                            $Part_Number   = $record->purchaseOrder->partNumber->partnumber ?? '';
                            $Revision      = $record->purchaseOrder->partNumber->revision ?? '';
                            $Machine_Group = $record->machine_group_id ?? '';

                            return new HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block w-full overflow-x-auto shadow rounded-lg">
                                    <table class="w-full min-w-full text-sm text-center bg-white dark:bg-gray-900 border-collapse rounded-lg overflow-hidden table-fixed">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Unique ID</th>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Sales Order</th>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Part Number</th>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Revision</th>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Machine Group</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Unique_ID).'</td>
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Sales_Order).'</td>
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Part_Number).'</td>
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Revision).'</td>
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Machine_Group).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-white dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Sale Order Details
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div><span class="font-bold text-black dark:text-white">Unique ID: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Unique_ID).'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Sales Order: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Sales_Order).'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Part Number: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Part_Number).'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Revision: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Revision).'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Machine Group: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Machine_Group).'</span></div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            Section::make('Operational Information')
                ->collapsible()->columnSpanFull()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('Operators_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $leadTime = $record->lead_time ? Carbon::parse($record->lead_time) : null;
                            $deliveryTarget = $record->purchaseOrder && $record->purchaseOrder->delivery_target_date
                                ? Carbon::parse($record->purchaseOrder->delivery_target_date)->endOfDay()
                                : null;

                            $deliveryDate = $record->purchaseOrder->delivery_target_date ?? null;
                            $leadTimeFormatted = $leadTime ? $leadTime->format('d M Y') : '-';

                            $deliveryDateStyle = '';
                            $deliveryDateTooltip = '';
                            if ($leadTime && $deliveryTarget && $leadTime->greaterThan($deliveryTarget)) {
                                $deliveryDateStyle = 'color: #dc2626; font-weight: bold;';
                                $deliveryDateTooltip = 'Sales Order Line Target Completion Date : ' . htmlspecialchars($deliveryDate);
                            }

                            $statusLabel = BomStatus::tryFrom($record->status)?->label() ?? $record->status;

                            return new HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block w-full overflow-x-auto shadow rounded-lg">
                                    <table class="w-full min-w-full text-sm text-center bg-white dark:bg-gray-900 border-collapse rounded-lg overflow-hidden table-fixed">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Proficiency</th>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Target Completion Time</th>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->operatorProficiency->description ?? '').'</td>
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100" style="'.$deliveryDateStyle.'" title="'.$deliveryDateTooltip.'">'.$leadTimeFormatted.'</td>
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($statusLabel).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-white dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Operational Information
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div><span class="font-bold text-black dark:text-white">Proficiency: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->operatorProficiency->description ?? '').'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Target Completion Time: </span><span class="text-gray-900 dark:text-gray-100" style="'.$deliveryDateStyle.'" title="'.$deliveryDateTooltip.'">'.$leadTimeFormatted.'</span></div>
                                        <div><span class="font-bold text-black dark:text-white">Status: </span><span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($statusLabel).'</span></div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            Section::make('Documents')
                ->collapsible()->columnSpanFull()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('documents_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $requirementLinks = $record->getMedia('requirement_pkg')->map(function ($media) {
                                return "<a href='{$media->getUrl()}' target='_blank' class='block text-blue-500 underline'>{$media->file_name}</a>";
                            })->implode('<br>') ?: 'No Files';

                            $flowchartLinks = $record->getMedia('process_flowchart')->map(function ($media) {
                                return "<a href='{$media->getUrl()}' target='_blank' class='block text-blue-500 underline'>{$media->file_name}</a>";
                            })->implode('<br>') ?: 'No Files';

                            return new HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block w-full overflow-x-auto shadow rounded-lg">
                                    <table class="w-full min-w-full text-sm text-center bg-white dark:bg-gray-900 border-collapse rounded-lg overflow-hidden table-fixed">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Requirement Package</th>
                                                <th class="p-2 font-bold border border-white dark:border-gray-700 text-black dark:text-white">Process Flowchart</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$requirementLinks.'</td>
                                                <td class="p-2 border border-white dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$flowchartLinks.'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-white dark:border-gray-700 mt-4">
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
        ]);
    }
}
