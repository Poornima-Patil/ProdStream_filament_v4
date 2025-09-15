<?php

namespace App\Filament\Admin\Resources\OperatorProficiencyResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\OperatorProficiencyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOperatorProficiencies extends ListRecords
{
    protected static string $resource = OperatorProficiencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
