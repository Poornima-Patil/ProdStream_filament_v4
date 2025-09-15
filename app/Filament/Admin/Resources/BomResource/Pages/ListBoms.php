<?php

namespace App\Filament\Admin\Resources\BomResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\BomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoms extends ListRecords
{
    protected static string $resource = BomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
