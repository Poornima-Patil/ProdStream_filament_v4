<?php

namespace App\Filament\Admin\Resources\CustomerInformationResource\Pages;

use App\Filament\Admin\Resources\CustomerInformationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerInformation extends ListRecords
{
    protected static string $resource = CustomerInformationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
