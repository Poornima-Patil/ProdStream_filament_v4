<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupResource\Pages;

use App\Filament\Admin\Resources\WorkOrderGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrderGroup extends EditRecord
{
    protected static string $resource = WorkOrderGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
