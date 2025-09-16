<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupResource\Pages;

use App\Filament\Admin\Resources\WorkOrderGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrderGroups extends ListRecords
{
    protected static string $resource = WorkOrderGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
