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
        // Get the factory_id from the data or current tenant
        $factoryId = $data['factory_id'] ?? \Filament\Facades\Filament::getTenant()?->id ?? 1;

        // Get the globally highest customer_id to ensure uniqueness across all factories
        $lastCustomer = CustomerInformation::withTrashed()->latest('customer_id')->first();

        // Generate the next customer ID globally, or use factory-based starting range if no customers exist
        $lastId = $lastCustomer ? (int) $lastCustomer->customer_id : (2000 + ($factoryId * 1000));
        $data['customer_id'] = (string) ($lastId + 10);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
