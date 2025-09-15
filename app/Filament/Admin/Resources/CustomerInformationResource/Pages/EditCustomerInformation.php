<?php

namespace App\Filament\Admin\Resources\CustomerInformationResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Admin\Resources\CustomerInformationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerInformation extends EditRecord
{
    protected static string $resource = CustomerInformationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
