<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
