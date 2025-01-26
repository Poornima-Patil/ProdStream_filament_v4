<?php

namespace App\Filament\Admin\Resources\PurchaseorderResource\Pages;

use App\Filament\Admin\Resources\PurchaseorderResource;
use Filament\Resources\Pages\CreateRecord;
use Carbon\Carbon;
use App\Models\PurchaseOrder;
use App\Models\PartNumber;
use App\Models\CustomerInformation;
class CreatePurchaseorder extends CreateRecord
{
    protected static string $resource = PurchaseorderResource::class;


    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the current date in MMYY format
        $currentDate = Carbon::now();
        $monthYear = $currentDate->format('mY'); // MMYY format

        // Get the related PurchaseOrder's cust_id
       
        $cust_id = $data['cust_id'];
        $custId = CustomerInformation::find($cust_id)->customer_id;

        // Get the related PartNumber's part_number and revision
       
        $partNumber_id = $data['part_number_id'];
        $partnumber =PartNumber::find($partNumber_id)->partnumber;
        $revision = PartNumber::find($partNumber_id)->revision;

        // Get the latest Bom created in the current MMYY to determine the next sequential number
        $lastSO = PurchaseOrder::whereDate('created_at', 'like', $currentDate->format('Y-m').'%') // Filter by year and month
            ->orderByDesc('unique_id') // Sort by unique_id to get the last one
            ->first();

        // Generate the sequence number (reset to 1 if no record is found for the current month)
        $sequenceNumber = 1;
        if ($lastSO) {
            // Extract the current sequence number from the last unique_id (OXXXX)
            $sequenceNumber = (int) substr($lastSO->unique_id, 1, 4) + 1;
        }

        // Pad the sequence number to 4 digits (e.g., 0001, 0002, ...)
        $sequenceNumber = str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);

        // Generate the unique_id in MMYY format
        $data['unique_id'] = 'S'.$sequenceNumber.'_'.$monthYear.'_'.$custId.'_'.$partnumber.'_'.$revision;

        return $data;
    }
}
