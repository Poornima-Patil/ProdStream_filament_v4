<?php

namespace App\Filament\Admin\Resources\CustomerInformationResource\Pages;

use App\Filament\Admin\Resources\CustomerInformationResource;
use App\Models\CustomerInformation;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerInformation extends CreateRecord
{
    protected static string $resource = CustomerInformationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the latest customer record to determine the next unique ID
        $lastCustomer = CustomerInformation::withTrashed()->latest('customer_id')->first();

        // Generate the next customer ID by adding 10 to the last one or starting at 1010
        $lastId = $lastCustomer ? (int) $lastCustomer->customer_id : 1000;
        $data['customer_id'] = (string) ($lastId + 10);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
