<?php

namespace App\Filament\Admin\Resources\MachineResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\MachineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMachines extends ListRecords
{
    protected static string $resource = MachineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
