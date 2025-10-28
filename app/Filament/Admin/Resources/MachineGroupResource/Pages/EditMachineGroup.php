<?php

namespace App\Filament\Admin\Resources\MachineGroupResource\Pages;

use App\Filament\Admin\Resources\MachineGroupResource;
use Filament\Actions\DeleteAction;
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
