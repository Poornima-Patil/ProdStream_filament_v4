<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use App\Models\Bom;
use App\Models\WorkOrder;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the related Bom's unique_id
        $bom = Bom::find($data['bom_id']);
        $bomUniqueId = $bom ? $bom->unique_id : 'UNKNOWN'; // Get the Bom's unique_id

        // Get factory_id from data or current tenant
        $factoryId = $data['factory_id'] ?? \Filament\Facades\Filament::getTenant()?->id ?? 1;

        $data['unique_id'] = WorkOrder::generateUniqueId($factoryId, $bomUniqueId);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
