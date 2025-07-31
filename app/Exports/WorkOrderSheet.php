<?php

namespace App\Exports;

use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WorkOrderSheet implements FromCollection, WithHeadings
{
    public function __construct(public string $start, public string $end) {}

    public function collection(): Collection
    {

        $factoryId = Auth::user()->factory_id;
        Log::info('Factory ID: ', ['factory_id' => $factoryId]);
        $data = WorkOrder::with([
            'bom.purchaseOrder.partNumber',
            'machine',
            'operator',
            'okQuantities',
            'scrappedQuantities'
        ])
            ->where('factory_id', $factoryId)
            ->whereBetween('created_at', [$this->start, $this->end])
            ->get()
            ->map(function ($wo) {
                return [
                    'Work Order No'  => $wo->unique_id,
                    'BOM'            => optional($wo->bom)->unique_id,
                    'Part Number'    => optional($wo->bom?->purchaseOrder?->partNumber)->partnumber,
                    'Revision'       => optional($wo->bom?->purchaseOrder?->partNumber)->revision,
                    'Machine'        => optional($wo->machine)->name,
                    'Operator'       => optional($wo->operator)->user->first_name,
                    'Qty'            => $wo->qty,
                    'Status'         => $wo->status,
                    'Start Time'     => $wo->start_time,
                    'End Time'       => $wo->end_time,
                    'OK Qty'         => $wo->ok_qtys,
                    'KO Qty'         => $wo->scrapped_qtys,
                ];
            });
        //\Log::info('start =', $this->start);
        //\Log::info('end =', $this->end);
        Log::info('Export Collection: ', $data->toArray());

        return $data;
    }

    public function headings(): array
    {
        return [
            'Work Order No',
            'BOM',
            'Part Number',
            'Revision',
            'Machine',
            'Operator',
            'Qty',
            'Status',
            'Start Time',
            'End Time',
            'OK Qty',
            'KO Qty',
        ];
    }
}
