<?php

namespace App\Filament\Admin\Resources\OperatorProficiencyResource\Pages;

use App\Filament\Admin\Resources\OperatorProficiencyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOperatorProficiencies extends ListRecords
{
    protected static string $resource = OperatorProficiencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
