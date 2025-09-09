<?php

namespace App\Exports;

use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesOrderExport implements FromCollection, WithHeadings
{
    public function __construct(public array $filters) {}

    public function collection(): Collection
    {
        $factoryId = Auth::user()->factory_id;
        Log::info('Sales Order Export - Factory ID: ', ['factory_id' => $factoryId]);

        $query = PurchaseOrder::with([
            'partNumber',
            'customerInformation',
            'boms.machineGroup',
            'boms.operatorProficiency',
            'boms.workOrders.machine',
            'boms.workOrders.operator.user',
            'boms.workOrders.holdReason',
        ])->where('factory_id', $factoryId);

        // Apply date range filter
        if (! empty($this->filters['date_from']) && ! empty($this->filters['date_to'])) {
            $query->whereBetween('created_at', [$this->filters['date_from'], $this->filters['date_to']]);
        }

        // Apply customer filter
        if (! empty($this->filters['customer'])) {
            $query->where('cust_id', $this->filters['customer']);
        }

        $data = $query->get()->flatMap(function ($salesOrder) {
            $salesOrderData = [
                'Sales Order No' => $salesOrder->unique_id ?? ('SO-'.$salesOrder->id),
                'Part Number' => optional($salesOrder->partNumber)->partnumber,
                'Revision' => optional($salesOrder->partNumber)->revision,
                'Description' => optional($salesOrder->partNumber)->description,
                'Customer' => optional($salesOrder->customerInformation)->name,
                'Quantity' => $salesOrder->QTY,
                'Unit of Measurement' => $salesOrder->{'Unit Of Measurement'},
                'Price' => $salesOrder->price,
                'Delivery Target Date' => $salesOrder->delivery_target_date
                    ? \Carbon\Carbon::parse($salesOrder->delivery_target_date)->format('Y-m-d') : '',
                'SO Created At' => $salesOrder->created_at->format('Y-m-d H:i:s'),
                'SO Updated At' => $salesOrder->updated_at->format('Y-m-d H:i:s'),
            ];

            // Flatten BOMs and their Work Orders
            if ($salesOrder->boms->isEmpty()) {
                return [array_merge($salesOrderData, [
                    'BOM No' => '',
                    'BOM Machine Group' => '',
                    'BOM Operator Proficiency' => '',
                    'BOM Lead Time' => '',
                    'BOM Status' => '',
                    'BOM Created At' => '',
                    'BOM Updated At' => '',
                    'WO No' => '',
                    'WO Machine' => '',
                    'WO Machine Asset ID' => '',
                    'WO Operator' => '',
                    'WO Planned Qty' => '',
                    'WO Status' => '',
                    'WO Start Time' => '',
                    'WO End Time' => '',
                    'WO OK Qty' => '',
                    'WO Scrapped Qty' => '',
                    'WO Total Produced' => '',
                    'WO Progress %' => '',
                    'WO Yield %' => '',
                    'WO Hold Reason' => '',
                    'WO Material Batch' => '',
                    'WO Created At' => '',
                    'WO Updated At' => '',
                ])];
            }

            return $salesOrder->boms->flatMap(function ($bom) use ($salesOrderData) {
                $bomData = array_merge($salesOrderData, [
                    'BOM No' => $bom->unique_id ?? ('BOM-'.$bom->id),
                    'BOM Machine Group' => optional($bom->machineGroup)->group_name,
                    'BOM Operator Proficiency' => optional($bom->operatorProficiency)->proficiency,
                    'BOM Lead Time' => $bom->lead_time ? \Carbon\Carbon::parse($bom->lead_time)->format('Y-m-d H:i:s') : '',
                    'BOM Status' => $bom->status ? 'Active' : 'Inactive',
                    'BOM Created At' => $bom->created_at->format('Y-m-d H:i:s'),
                    'BOM Updated At' => $bom->updated_at->format('Y-m-d H:i:s'),
                ]);

                if ($bom->workOrders->isEmpty()) {
                    return [array_merge($bomData, [
                        'WO No' => '',
                        'WO Machine' => '',
                        'WO Machine Asset ID' => '',
                        'WO Operator' => '',
                        'WO Planned Qty' => '',
                        'WO Status' => '',
                        'WO Start Time' => '',
                        'WO End Time' => '',
                        'WO OK Qty' => '',
                        'WO Scrapped Qty' => '',
                        'WO Total Produced' => '',
                        'WO Progress %' => '',
                        'WO Yield %' => '',
                        'WO Hold Reason' => '',
                        'WO Material Batch' => '',
                        'WO Created At' => '',
                        'WO Updated At' => '',
                    ])];
                }

                return $bom->workOrders->map(function ($wo) use ($bomData) {
                    $totalQty = $wo->qty ?? 0;
                    $producedQty = ($wo->ok_qtys ?? 0) + ($wo->scrapped_qtys ?? 0);
                    $progress = $totalQty > 0 ? round(($producedQty / $totalQty) * 100, 1) : 0;
                    $yield = $producedQty > 0 ? round((($wo->ok_qtys ?? 0) / $producedQty) * 100, 1) : 0;

                    return array_merge($bomData, [
                        'WO No' => $wo->unique_id ?? ('WO-'.$wo->id),
                        'WO Machine' => optional($wo->machine)->name,
                        'WO Machine Asset ID' => optional($wo->machine)->assetId,
                        'WO Operator' => optional($wo->operator?->user)->getFilamentName(),
                        'WO Planned Qty' => $wo->qty,
                        'WO Status' => $wo->status,
                        'WO Start Time' => $wo->start_time ? $wo->start_time->format('Y-m-d H:i:s') : '',
                        'WO End Time' => $wo->end_time ? $wo->end_time->format('Y-m-d H:i:s') : '',
                        'WO OK Qty' => $wo->ok_qtys ?? 0,
                        'WO Scrapped Qty' => $wo->scrapped_qtys ?? 0,
                        'WO Total Produced' => $producedQty,
                        'WO Progress %' => $progress,
                        'WO Yield %' => $yield,
                        'WO Hold Reason' => optional($wo->holdReason)->description,
                        'WO Material Batch' => $wo->material_batch,
                        'WO Created At' => $wo->created_at->format('Y-m-d H:i:s'),
                        'WO Updated At' => $wo->updated_at->format('Y-m-d H:i:s'),
                    ]);
                });
            });
        });

        Log::info('Sales Order Export Collection Count: ', ['count' => $data->count()]);

        return $data;
    }

    public function headings(): array
    {
        return [
            'Sales Order No',
            'Part Number',
            'Revision',
            'Description',
            'Customer',
            'Quantity',
            'Unit of Measurement',
            'Price',
            'Delivery Target Date',
            'SO Created At',
            'SO Updated At',
            'BOM No',
            'BOM Machine Group',
            'BOM Operator Proficiency',
            'BOM Lead Time',
            'BOM Status',
            'BOM Created At',
            'BOM Updated At',
            'WO No',
            'WO Machine',
            'WO Machine Asset ID',
            'WO Operator',
            'WO Planned Qty',
            'WO Status',
            'WO Start Time',
            'WO End Time',
            'WO OK Qty',
            'WO Scrapped Qty',
            'WO Total Produced',
            'WO Progress %',
            'WO Yield %',
            'WO Hold Reason',
            'WO Material Batch',
            'WO Created At',
            'WO Updated At',
        ];
    }
}
