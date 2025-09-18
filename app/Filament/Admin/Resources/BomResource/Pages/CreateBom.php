<?php

namespace App\Filament\Admin\Resources\BomResource\Pages;

use App\Models\PurchaseOrder;
use App\Filament\Admin\Resources\BomResource;
use App\Models\Bom;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateBom extends CreateRecord
{
    protected static string $resource = BomResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the current date in MMYY format
        $currentDate = Carbon::now();
        $monthYear = $currentDate->format('mY'); // MMYY format

        // Get the related PurchaseOrder and its details
        $purchaseOrder = PurchaseOrder::find($data['purchase_order_id']);
        $custId = $purchaseOrder->customer->customer_id;
        $factoryId = $purchaseOrder->factory_id; // Get factory_id from purchase order

        // Get the related PartNumber's part_number and revision
        $partNumber = $purchaseOrder->partNumber;
        $partnumber = $partNumber->partnumber;
        $revision = $partNumber->revision;

        // Get the latest Bom for this specific factory to determine the next sequential number
        $lastBom = Bom::where('factory_id', $factoryId) // Filter by factory directly
            ->whereDate('created_at', 'like', $currentDate->format('Y-m').'%') // Filter by year and month
            ->withTrashed()
            ->orderByDesc('unique_id') // Sort by unique_id to get the last one
            ->first();

        // Generate the sequence number (reset to 1 if no record is found for the current month)
        $sequenceNumber = 1;
        if ($lastBom) {
            // Extract the current sequence number from the last unique_id (OXXXX)
            $sequenceNumber = (int) substr($lastBom->unique_id, 1, 4) + 1;
        }

        // Pad the sequence number to 4 digits (e.g., 0001, 0002, ...)
        $sequenceNumber = str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);

        // Generate the unique_id in MMYY format
        $data['unique_id'] = 'O'.$sequenceNumber.'_'.$monthYear.'_'.$custId.'_'.$partnumber.'_'.$revision;

        return $data;
    }
}
