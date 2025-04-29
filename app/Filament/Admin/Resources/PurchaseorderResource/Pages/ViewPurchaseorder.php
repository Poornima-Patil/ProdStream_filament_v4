<?php

namespace App\Filament\Admin\Resources\PurchaseorderResource\Pages;

use App\Filament\Admin\Resources\PurchaseorderResource;
use App\Models\PurchaseOrder;
use Filament\Infolists\Components\Progress;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

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
                            $progressColor = $progressPercent >= 50 ? 'bg-emerald-500' : 'bg-amber-500';
                            $textColor = 'text-white';

                            return new \Illuminate\Support\HtmlString('
                                <div class="overflow-x-auto rounded-lg shadow">
                                    <table class="w-full text-sm border border-gray-300 text-center">
                                        <thead  class="bg-primary-500 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300">SO Number</th>
                                                <th class="p-2 border border-gray-300">Customer</th>
                                                <th class="p-2 border border-gray-300">Part Number</th>
                                                <th class="p-2 border border-gray-300">Requested Quantity</th>
                                                <th class="p-2 border border-gray-300">Completed Quantity</th>
                                                <th class="p-2 border border-gray-300">Scrapped Quantity</th>
                                                <th class="p-2 border border-gray-300">Progress</th>
                                                <th class="p-2 border border-gray-300">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white hover:bg-gray-50">
                                                <td class="p-2 border border-gray-300">'.htmlspecialchars($record->unique_id).'</td>
                                                <td class="p-2 border border-gray-300">'.htmlspecialchars($record->customer->name ?? '-').'</td>
                                                <td class="p-2 border border-gray-300">'.htmlspecialchars($record->partNumber->partnumber ?? '-').'</td>
                                                <td class="p-2 border border-gray-300">'.htmlspecialchars($requestedQty).'</td>
                                                <td class="p-2 border border-gray-300">'.htmlspecialchars($completedQty).'</td>
                                                <td class="p-2 border border-gray-300">'.htmlspecialchars($scrappedQty).'</td>
                                                <td class="p-2 border border-gray-300">
                                                    <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                                                        <div class="h-4 '.$progressColor.' '.$textColor.' rounded-full text-xs font-medium flex items-center justify-center transition-all duration-300" style="width:'.$progressPercent.'%; min-width: 2rem;">
                                                            '.$progressPercent.'%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="p-2 border border-gray-300">'.htmlspecialchars($record->status).'</td>
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
                                return '<div class="text-gray-500">No BOMs found.</div>';
                            }

                            $table = '
                                <div class="overflow-x-auto rounded-lg shadow-md">
                                    <table class="w-full text-sm border border-gray-300 text-center">
                                        <thead class="bg-primary-500 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300">BOM Number</th>
                                                <th class="p-2 border border-gray-300">Description</th>
                                                <th class="p-2 border border-gray-300">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>';

                            foreach ($record->boms as $bom) {
                                $statusClass = match ($bom->status) {
                                    'Completed' => 'text-green-600',
                                    'Start' => 'text-yellow-600',
                                    'Hold' => 'text-red-600',
                                    default => 'text-gray-600',
                                };

                                $table .= '
                                    <tr class="bg-white hover:bg-gray-50">
                                        <td class="p-2 border border-gray-300">'.htmlspecialchars($bom->unique_id).'</td>
                                        <td class="p-2 border border-gray-300">'.htmlspecialchars($bom->description).'</td>
                                        <td class="p-2 border border-gray-300 '.$statusClass.'">'.htmlspecialchars($bom->status).'</td>
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
                                return '<div class="text-gray-500">No work orders found.</div>';
                            }

                            $table = '
                                <div class="overflow-x-auto rounded-lg shadow-md">
                                    <div class="hidden md:block">
                                        <table class="w-full text-sm border border-gray-300 text-center">
                                            <thead class="bg-primary-500 text-white">
                                                <tr>
                                                    <th class="p-2 border border-gray-300">WO Number</th>
                                                    <th class="p-2 border border-gray-300">BOM Number</th>
                                                    <th class="p-2 border border-gray-300">Qty</th>
                                                    <th class="p-2 border border-gray-300">Machine ID</th>
                                                    <th class="p-2 border border-gray-300">Start Time</th>
                                                    <th class="p-2 border border-gray-300">End Time</th>
                                                    <th class="p-2 border border-gray-300">Status</th>
                                                    <th class="p-2 border border-gray-300">OK Qty</th>
                                                    <th class="p-2 border border-gray-300">Scrapped Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>';

                            foreach ($record->workOrders as $workOrder) {
                                $statusClass = match ($workOrder->status) {
                                    'Completed' => 'bg-green-100 text-green-800',
                                    'Start' => 'bg-yellow-100 text-yellow-800',
                                    'Hold' => 'bg-red-100 text-red-800',
                                    'Assigned' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-gray-100 text-gray-800',
                                };

                                $startTime = $workOrder->start_time ? \Carbon\Carbon::parse($workOrder->start_time)->format('Y-m-d H:i') : '-';
                                $endTime = $workOrder->end_time ? \Carbon\Carbon::parse($workOrder->end_time)->format('Y-m-d H:i') : '-';

                                $table .= '
                                    <tr class="bg-white hover:bg-gray-50">
                                        <td class="p-2 border border-gray-300">'.htmlspecialchars($workOrder->unique_id).'</td>
                                        <td class="p-2 border border-gray-300">BOM-'.htmlspecialchars($workOrder->bom_id).'</td>
                                        <td class="p-2 border border-gray-300">'.htmlspecialchars($workOrder->qty).'</td>
                                        <td class="p-2 border border-gray-300">'.htmlspecialchars($workOrder->machine_id).'</td>
                                        <td class="p-2 border border-gray-300">'.htmlspecialchars($startTime).'</td>
                                        <td class="p-2 border border-gray-300">'.htmlspecialchars($endTime).'</td>
                                        <td class="p-2 border border-gray-300"><span class="px-2 py-1 text-xs font-semibold rounded-full '.$statusClass.'">'.htmlspecialchars($workOrder->status).'</span></td>
                                        <td class="p-2 border border-gray-300 text-green-600">'.htmlspecialchars($workOrder->ok_qtys).'</td>
                                        <td class="p-2 border border-gray-300 text-red-600">'.htmlspecialchars($workOrder->scrapped_qtys).'</td>
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
