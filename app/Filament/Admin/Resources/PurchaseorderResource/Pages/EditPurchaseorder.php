<?php

namespace App\Filament\Admin\Resources\PurchaseorderResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use App\Filament\Admin\Resources\PurchaseorderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseorder extends EditRecord
{
    protected static string $resource = PurchaseorderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
