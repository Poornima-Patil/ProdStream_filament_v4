<?php

namespace App\Filament\Admin\Resources\MachineGroupResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\MachineGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMachineGroups extends ListRecords
{
    protected static string $resource = MachineGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
