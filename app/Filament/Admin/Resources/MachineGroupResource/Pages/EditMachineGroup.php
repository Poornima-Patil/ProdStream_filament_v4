<?php

namespace App\Filament\Admin\Resources\MachineGroupResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Admin\Resources\MachineGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMachineGroup extends EditRecord
{
    protected static string $resource = MachineGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
