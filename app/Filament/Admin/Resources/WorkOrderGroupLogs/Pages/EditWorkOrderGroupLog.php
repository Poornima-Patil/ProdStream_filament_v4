<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupLogs\Pages;

use App\Filament\Admin\Resources\WorkOrderGroupLogs\WorkOrderGroupLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrderGroupLog extends EditRecord
{
    protected static string $resource = WorkOrderGroupLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
