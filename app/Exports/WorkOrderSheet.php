<?php

namespace App\Exports;

use App\Models\Machine;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WorkOrderSheet implements FromCollection, WithHeadings
{
    public function __construct(public array $filters) {}

    public function collection(): Collection
    {
        $factoryId = Auth::user()->factory_id;
        Log::info('Factory ID: ', ['factory_id' => $factoryId]);

        $query = WorkOrder::with([
            'bom.purchaseOrder.partNumber',
            'bom.purchaseOrder.customerInformation',
            'machine',
            'operator.user',
            'holdReason',
            'workOrderLogs',
        ])->where('factory_id', $factoryId);

        // Apply date range filter
        if (! empty($this->filters['date_from']) && ! empty($this->filters['date_to'])) {
            $query->whereBetween('created_at', [$this->filters['date_from'], $this->filters['date_to']]);
        }

        // Apply status filter
        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        // Apply machine filter
        if (! empty($this->filters['machine'])) {
            $machine = Machine::where('name', $this->filters['machine'])
                ->where('factory_id', $factoryId)
                ->first();
            if ($machine) {
                $query->where('machine_id', $machine->id);
            }
        }

        // Apply operator filter
        if (! empty($this->filters['operator'])) {
            $query->where('operator_id', $this->filters['operator']);
        }

        $data = $query->get()->map(function ($wo) {
            // Calculate progress
            $totalQty = $wo->qty ?? 0;
            $producedQty = ($wo->ok_qtys ?? 0) + ($wo->scrapped_qtys ?? 0);
            $progress = $totalQty > 0 ? round(($producedQty / $totalQty) * 100, 1) : 0;

            // Calculate yield
            $yield = $producedQty > 0 ? round((($wo->ok_qtys ?? 0) / $producedQty) * 100, 1) : 0;

            // Get customer info
            $customerName = '';
            if ($wo->bom && $wo->bom->purchaseOrder && $wo->bom->purchaseOrder->customerInformation) {
                $customerName = $wo->bom->purchaseOrder->customerInformation->name;
            }

            return [
                'Work Order No' => $wo->unique_id ?? ('WO-'.$wo->id),
                'BOM' => optional($wo->bom)->unique_id,
                'Purchase Order' => optional($wo->bom?->purchaseOrder)->unique_id,
                'Part Number' => optional($wo->bom?->purchaseOrder?->partNumber)->partnumber,
                'Revision' => optional($wo->bom?->purchaseOrder?->partNumber)->revision,
                'Description' => optional($wo->bom?->purchaseOrder?->partNumber)->description,
                'Customer' => $customerName,
                'Machine' => optional($wo->machine)->name,
                'Machine Asset ID' => optional($wo->machine)->assetId,
                'Operator' => optional($wo->operator?->user)->getFilamentName(),
                'Planned Qty' => $wo->qty,
                'Status' => $wo->status,
                'Start Time' => $wo->start_time ? $wo->start_time->format('Y-m-d H:i:s') : '',
                'End Time' => $wo->end_time ? $wo->end_time->format('Y-m-d H:i:s') : '',
                'OK Qty' => $wo->ok_qtys ?? 0,
                'Scrapped Qty' => $wo->scrapped_qtys ?? 0,
                'Total Produced' => $producedQty,
                'Progress %' => $progress,
                'Yield %' => $yield,
                'Hold Reason' => optional($wo->holdReason)->description,
                'Material Batch' => $wo->material_batch,
                'Created At' => $wo->created_at->format('Y-m-d H:i:s'),
                'Updated At' => $wo->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        Log::info('Export Collection Count: ', ['count' => $data->count()]);

        return $data;
    }

    public function headings(): array
    {
        return [
            'Work Order No',
            'BOM',
            'Purchase Order',
            'Part Number',
            'Revision',
            'Description',
            'Customer',
            'Machine',
            'Machine Asset ID',
            'Operator',
            'Planned Qty',
            'Status',
            'Start Time',
            'End Time',
            'OK Qty',
            'Scrapped Qty',
            'Total Produced',
            'Progress %',
            'Yield %',
            'Hold Reason',
            'Material Batch',
            'Created At',
            'Updated At',
        ];
    }
}
