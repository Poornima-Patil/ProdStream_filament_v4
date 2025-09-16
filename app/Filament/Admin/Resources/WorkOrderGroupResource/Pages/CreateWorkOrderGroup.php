<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupResource\Pages;

use App\Filament\Admin\Resources\WorkOrderGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkOrderGroup extends CreateRecord
{
    protected static string $resource = WorkOrderGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['factory_id'] = \Filament\Facades\Filament::getTenant()->id;

        return $data;
    }
}
