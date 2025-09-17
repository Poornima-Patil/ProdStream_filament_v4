<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupLogs\Pages;

use App\Filament\Admin\Resources\WorkOrderGroupLogs\WorkOrderGroupLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrderGroupLogs extends ListRecords
{
    protected static string $resource = WorkOrderGroupLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
