<?php

namespace App\Filament\Admin\Resources\PurchaseorderResource\Pages;

use App\Filament\Admin\Resources\PurchaseorderResource;
use App\Models\PurchaseOrder;
use Filament\Infolists\Components\Progress;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use App\Enums\BomStatus;


class ViewPurchaseorder extends ViewRecord
{
    protected static string $resource = PurchaseorderResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Sale Order Details')
                ->schema([
                    TextEntry::make('po_details_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            $completedQty = $record->boms
                                ? $record->boms->flatMap->workOrders->sum('ok_qtys')
                                : 0;

                            $scrappedQty = $record->boms
                                ? $record->boms->flatMap->workOrders->sum('scrapped_qtys')
                                : 0;

                            $requestedQty = $record->QTY ?? 0;
                            $progressPercent = $requestedQty > 0 ? round(($completedQty / $requestedQty) * 100) : 0;

                            // Determine progress bar color
                            $progressColor = $progressPercent >= 50 ? 'bg-emerald-500 dark:bg-emerald-400' : 'bg-amber-500 dark:bg-amber-400';
                            $textColor = 'text-white dark:text-gray-900';

                            return new \Illuminate\Support\HtmlString('
                                <div class="overflow-x-auto rounded-lg shadow">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">SO Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Customer</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Part Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Target Completion Date</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Requested Quantity</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Completed Quantity</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Scrapped Quantity</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Progress</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->unique_id).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->customer->name ?? '-').'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->partNumber->partnumber ?? '-').'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">
                                                    '.htmlspecialchars($record->delivery_target_date ? \Carbon\Carbon::parse($record->delivery_target_date)->format('Y-m-d') : '-').'
                                                </td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($requestedQty).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($completedQty).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($scrappedQty).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700">
                                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                                                        <div class="h-4 '.$progressColor.' '.$textColor.' rounded-full text-xs font-medium flex items-center justify-center transition-all duration-300" style="width:'.$progressPercent.'%; min-width: 2rem;">
                                                            '.$progressPercent.'%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($record->status).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            ');
                        }),
                ]),

            Section::make('Bills of Materials')
                ->schema([
                    TextEntry::make('boms_table')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (! $record->boms || $record->boms->isEmpty()) {
                                return '<div class="text-gray-500 dark:text-gray-400">No BOMs found.</div>';
                            }

                            $table = '
                                <div class="overflow-x-auto rounded-lg shadow-md">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">BOM Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Description</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Status</th>
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

                            return $table;
                        })->html(),
                ]),

            Section::make('Work Orders')
                ->schema([
                    TextEntry::make('workOrders')
                        ->hiddenLabel()
                        ->getStateUsing(function ($record) {
                            if (! $record->workOrders || $record->workOrders->isEmpty()) {
                                return '<div class="text-gray-500 dark:text-gray-400">No work orders found.</div>';
                            }

                            $table = '
                                <div class="overflow-x-auto rounded-lg shadow-md">
                                    <div class="hidden md:block">
                                        <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                            <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                                <tr>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">WO Number</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">BOM Number</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Qty</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Machine ID</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Start Time</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">End Time</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Status</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">OK Qty</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Scrapped Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>';

                            foreach ($record->workOrders as $workOrder) {
                                $statusClass = match ($workOrder->status) {
                                    'Completed' => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300',
                                    'Start' => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300',
                                    'Hold' => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300',
                                    'Assigned' => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300',
                                    default => 'bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-300',
                                };

                                $startTime = $workOrder->start_time ? \Carbon\Carbon::parse($workOrder->start_time)->format('Y-m-d H:i') : '-';
                                $endTime = $workOrder->end_time ? \Carbon\Carbon::parse($workOrder->end_time)->format('Y-m-d H:i') : '-';

                                $table .= '
                                    <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($workOrder->unique_id).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">BOM-'.htmlspecialchars($workOrder->bom_id).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($workOrder->qty).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($workOrder->machine_id).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($startTime).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($endTime).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700"><span class="px-2 py-1 text-xs font-semibold rounded-full '.$statusClass.'">'.htmlspecialchars($workOrder->status).'</span></td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-green-600 dark:text-green-400">'.htmlspecialchars($workOrder->ok_qtys).'</td>
                                        <td class="p-2 border border-gray-300 dark:border-gray-700 text-red-600 dark:text-red-400">'.htmlspecialchars($workOrder->scrapped_qtys).'</td>
                                    </tr>';
                            }

                            $table .= '</tbody></table></div></div>';

                            return $table;
                        })->html(),
                ]),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        \Log::info('Initial Purchase Order Data:', [
            'po_id' => $data['id'],
            'has_boms' => isset($data['boms']),
            'boms_count' => isset($data['boms']) ? count($data['boms']) : 0,
        ]);

        $purchaseOrder = PurchaseOrder::with([
            'boms.workOrders.operator.user',
            'boms.workOrders.machine',
            'boms.workOrders.quantities',
            'partNumber',
            'factory',
            'customer',
        ])->find($data['id']);

        $data['workOrders'] = $purchaseOrder->workOrders->toArray();

        return $data;
    }

    protected function afterMount(): void
    {
        parent::afterMount();

        $purchaseOrder = PurchaseOrder::with([
            'boms.workOrders.operator.user',
            'boms.workOrders.machine',
            'boms.workOrders.quantities',
            'partNumber',
            'factory',
            'customer',
        ])->find($this->record->id);

        \Log::info('Purchase Order loaded in afterMount:', [
            'po_id' => $purchaseOrder->id,
            'po_unique_id' => $purchaseOrder->unique_id,
            'work_orders_count' => $purchaseOrder->workOrders->count(),
            'boms_count' => $purchaseOrder->boms->count(),
        ]);
    }
}
