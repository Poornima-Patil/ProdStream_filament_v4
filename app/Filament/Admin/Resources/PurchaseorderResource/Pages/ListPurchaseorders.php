<?php

namespace App\Filament\Admin\Resources\PurchaseorderResource\Pages;

use App\Filament\Admin\Resources\PurchaseorderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseorders extends ListRecords
{
    protected static string $resource = PurchaseorderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
