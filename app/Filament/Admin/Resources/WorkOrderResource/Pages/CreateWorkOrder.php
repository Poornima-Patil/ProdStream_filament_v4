<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use App\Models\Bom;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the current date in MMDDYY format (Month, Day, Year)
        $currentDate = Carbon::now();
        $dateFormat = $currentDate->format('mdy'); // MMDDYY format

        // Get the related Bom's unique_id
        $bom = Bom::find($data['bom_id']);
        $bomUniqueId = $bom ? $bom->unique_id : 'UNKNOWN'; // Get the Bom's unique_id

        // Get the latest WorkOrder created in the current month to determine the next sequential number
        $lastWorkOrder = WorkOrder::withTrashed()->whereDate('created_at', 'like', $currentDate->format('Y-m').'%') // Filter by year and month
            ->orderByDesc('unique_id') // Sort by unique_id to get the last one
            ->first();

        // Generate the sequence number (reset to 1 if no record is found for the current month)
        $sequenceNumber = 1;
        if ($lastWorkOrder) {
            // Extract the current sequence number from the last unique_id (WXXXX)
            $sequenceNumber = (int) substr($lastWorkOrder->unique_id, 1, 4) + 1;
        }

        // Pad the sequence number to 4 digits (e.g., 0001, 0002, ...)
        $sequenceNumber = str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);

        // Modify the unique_id format to WXXXX_MMDDYY_BOMUNIQUE_ID
        // Ensure to use MMDDYY format for the date
        $data['unique_id'] = 'W'.$sequenceNumber.'_'.$dateFormat.'_'.$bomUniqueId.'_D';
        $data['machine_id'] = Bom::find($this->data['bom_id'])->machine_id;
       // dd($data);

     
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
