<?php

namespace App\Filament\Admin\Resources\CustomerInformationResource\Pages;

use App\Filament\Admin\Resources\CustomerInformationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerInformation extends ListRecords
{
    protected static string $resource = CustomerInformationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
