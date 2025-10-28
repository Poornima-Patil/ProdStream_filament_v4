<?php

namespace App\Filament\Admin\Resources\PartnumberResource\Pages;

use App\Filament\Admin\Resources\PartnumberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartnumbers extends ListRecords
{
    protected static string $resource = PartnumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
