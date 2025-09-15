<?php

namespace App\Filament\Admin\Resources\OperatorResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\OperatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOperators extends ListRecords
{
    protected static string $resource = OperatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
