<?php

namespace App\Exports;

use Carbon\Carbon;
use App\Models\Bom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BomExport implements FromCollection, WithHeadings
{
    public function __construct(public array $filters) {}

    public function collection(): Collection
    {
        $factoryId = Auth::user()->factory_id;
        Log::info('BOM Export - Factory ID: ', ['factory_id' => $factoryId]);

        $query = Bom::with([
            'purchaseOrder.partNumber',
            'purchaseOrder.customerInformation',
            'machineGroup',
            'operatorProficiency',
            'workOrders.machine',
            'workOrders.operator.user',
            'workOrders.holdReason',
        ])->where('factory_id', $factoryId);

        // Apply date range filter
        if (! empty($this->filters['date_from']) && ! empty($this->filters['date_to'])) {
            $query->whereBetween('created_at', [$this->filters['date_from'], $this->filters['date_to']]);
        }

        // Apply machine group filter
        if (! empty($this->filters['machine_group'])) {
            $query->where('machine_group_id', $this->filters['machine_group']);
        }

        // Apply operator proficiency filter
        if (! empty($this->filters['operator_proficiency'])) {
            $query->where('operator_proficiency_id', $this->filters['operator_proficiency']);
        }

        // Apply status filter
        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        // Apply target completion date filter
        if (! empty($this->filters['target_completion_date'])) {
            $query->whereDate('lead_time', '<=', $this->filters['target_completion_date']);
        }

        $data = $query->get()->flatMap(function ($bom) {
            $bomData = [
                'BOM No' => $bom->unique_id ?? ('BOM-'.$bom->id),
                'Purchase Order' => optional($bom->purchaseOrder)->unique_id,
                'Part Number' => optional($bom->purchaseOrder?->partNumber)->partnumber,
                'Revision' => optional($bom->purchaseOrder?->partNumber)->revision,
                'Description' => optional($bom->purchaseOrder?->partNumber)->description,
                'Customer' => optional($bom->purchaseOrder?->customerInformation)->name,
                'Machine Group' => optional($bom->machineGroup)->group_name,
                'Operator Proficiency' => optional($bom->operatorProficiency)->proficiency,
                'Lead Time' => $bom->lead_time ? (Carbon::parse($bom->lead_time)->format('Y-m-d H:i:s')) : '',
                'Status' => $bom->status ? 'Active' : 'Inactive',
                'BOM Created At' => $bom->created_at->format('Y-m-d H:i:s'),
                'BOM Updated At' => $bom->updated_at->format('Y-m-d H:i:s'),
            ];

            // If there are no work orders, return just the BOM data
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

            // Return one row per work order with BOM data repeated
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

        Log::info('BOM Export Collection Count: ', ['count' => $data->count()]);

        return $data;
    }

    public function headings(): array
    {
        return [
            'BOM No',
            'Purchase Order',
            'Part Number',
            'Revision',
            'Description',
            'Customer',
            'Machine Group',
            'Operator Proficiency',
            'Lead Time',
            'Status',
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
