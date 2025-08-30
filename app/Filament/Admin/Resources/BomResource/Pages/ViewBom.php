<?php

namespace App\Filament\Admin\Resources\BomResource\Pages;

use App\Filament\Admin\Resources\BomResource;
use App\Models\Bom;
use Filament\Infolists\Components\Progress;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use App\Enums\BomStatus;


class ViewBom extends ViewRecord
{
    protected static string $resource = BomResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Sale Order Details')
            ->collapsible()
                ->schema([
                    TextEntry::make('po_details_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $Unique_ID = $record->purchaseOrder->unique_id;
                            $Sales_Order = $record->purchaseOrder->partNumber->description;
                            $Part_Number = $record->purchaseOrder->partNumber->partnumber;
                            $Revision = $record->purchaseOrder->partNumber->revision;
                            $Machine_Group = $record->machine_group_id;

                            return new \Illuminate\Support\HtmlString('
                                <div class="overflow-x-auto rounded-lg shadow">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Unique ID</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Sales Order</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Part Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Revision</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Machine Group</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Unique_ID).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Sales_Order).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Part_Number).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Revision).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Machine_Group).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            ');
                        }),
                ]),

            Section::make('Operational Information')
            ->collapsible()
                ->schema([
                    TextEntry::make('Operators_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
    $leadTime = $record->lead_time ? \Carbon\Carbon::parse($record->lead_time) : null;
                $deliveryTarget = $record->purchaseOrder && $record->purchaseOrder->delivery_target_date
                    ? \Carbon\Carbon::parse($record->purchaseOrder->delivery_target_date)->endOfDay()
                    : null;

                $deliveryDate = $record->purchaseOrder->delivery_target_date ?? null;
                $leadTimeFormatted = $leadTime ? $leadTime->format('d M Y') : '-';

                // Check if lead_time > delivery_target_date
                $deliveryDateStyle = '';
        $deliveryDateTooltip = '';
        if ($leadTime && $deliveryTarget && $leadTime->greaterThan($deliveryTarget)) {
            $deliveryDateStyle = 'color: #dc2626; font-weight: bold;'; // red
            $deliveryDateTooltip = 'Sales Order Line Target Completion Date : ' . htmlspecialchars($deliveryDate);
        }                         
                            $table = '
                                <div class="overflow-x-auto rounded-lg shadow-md">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Proficiency</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Target Completion time</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>';

                            $statusLabel = BomStatus::tryFrom($record->status)?->label() ?? $record->status;
                            $statusClass = '';

                            $table .= '
                                    <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->operatorProficiency->description ?? '').'</td>

     <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100" style="'.$deliveryDateStyle.'" title="'.$deliveryDateTooltip.'">'.htmlspecialchars($leadTimeFormatted).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 '.$statusClass.'">'.htmlspecialchars($statusLabel).'</td>
                                    </tr>';

                            $table .= '</tbody></table></div>';

                            return $table;
                        })->html(),

                ]),

            Section::make('Documents')
    ->collapsible()
    ->schema([
        TextEntry::make('documents_table')
            ->label('')
            ->getStateUsing(function ($record) {
                $requirementLinks = $record->getMedia('requirement_pkg')->map(function ($media) {
                    return "<a href='{$media->getUrl()}' target='_blank' class='block text-blue-500 underline'>{$media->file_name}</a>";
                })->implode('<br>');
                if (empty($requirementLinks)) {
                    $requirementLinks = 'No Files';
                }

                $flowchartLinks = $record->getMedia('process_flowchart')->map(function ($media) {
                    return "<a href='{$media->getUrl()}' target='_blank' class='block text-blue-500 underline'>{$media->file_name}</a>";
                })->implode('<br>');
                if (empty($flowchartLinks)) {
                    $flowchartLinks = 'No Files';
                }

                return new \Illuminate\Support\HtmlString('
                    <div class="overflow-x-auto rounded-lg shadow">
                        <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                            <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                <tr>
                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Download Requirement Package Files</th>
                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Download Process Flowchart Files</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$requirementLinks.'</td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$flowchartLinks.'</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                ');
            })
            ->html(),
    ]),
        ]);
    }
}
