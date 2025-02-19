<?php

namespace App\Filament\Admin\Resources\MachineGroupResource\Pages;

use App\Filament\Admin\Resources\MachineGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMachineGroups extends ListRecords
{
    protected static string $resource = MachineGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
